<?php
namespace Scat\Model;

class TxnLine extends \Scat\Model {
  public function txn() {
    return $this->belongs_to('Txn')->find_one();
  }

  public function item() {
    return $this->belongs_to('Item')->find_one();
  }

  public function returned_from() {
    return $this->belongs_to('TxnLine', 'returned_from_id')->find_one();
  }

  // We don't show codes starting with ZZ-, they're special
  public function code() {
    $code= $this->item()->code;
    return preg_match('/^ZZ-/', $code) ? '' : $this->item()->code;
  }

  public function data($update= null) {
    if ($update) {
      $this->data= json_encode($update);
    }

    return json_decode($this->data);
  }

  public function name() {
    return $this->override_name ? $this->override_name :
           $this->item()->name;
  }

  public function pricing_detail() {
    if (!$this->discount_type) return '';
    $item= $this->item();
    return ($item->retail_price ? 'MSRP $' : 'List $') .
           $this->retail_price .
           ($this->discount_type == 'percentage' ?
            ' / Sale: ' . sprintf('%.0f', $this->discount) . '% off' :
            ($this->discount_type == 'relative' ?
             ' / Sale: $' . $this->discount . ' off' :
             ''));
  }

  public function sale_price() {
    return $this->calcSalePrice(
      $this->retail_price,
      $this->discount_type,
      $this->discount
    );
  }

  public function ext_price() {
    $price= new \Decimal\Decimal($this->sale_price()) * $this->ordered;
    return (string)$price->round(2);
  }

  public function vendor_item() {
    $vendor_id= $this->txn()->person_id;
    if (!$vendor_id) return '';
    $vendor_items= $this->has_many('VendorItem', 'item_id', 'item_id')
                        ->where('vendor_id', $vendor_id)
                        ->order_by_asc('purchase_quantity')
                        ->find_many();
    if (!$vendor_items) return '';
    $vendor_item= '';
    foreach ($vendor_items as $item) {
      if ($item->purchase_quantity <= abs($this->ordered)) {
        $vendor_item= $item;
      }
    }
    /* Just use the first one if the quantity < all of the purchase_quantity */
    if (!$vendor_item) {
      $vendor_item= $vendor_items[0];
    }
    return $vendor_item;
  }

  public function vendor_sku() {
    $vendor_item= $this->vendor_item();
    return $vendor_item ? $vendor_item->vendor_sku : '';
  }

  public function cost_of_goods() {
    /* To calculate the cost of goods, we take the average of our vendor costs
     * before this transaction. Not great, but okay. */
    $txn= $this->txn();
    $q= "SELECT CAST(IFNULL(ROUND_TO_EVEN(
                    {$this->allocated} * ROUND_TO_EVEN(AVG(tl.retail_price), 2),
                    2), 0.00) AS DECIMAL(9,2)) AS cost
           FROM txn
           JOIN txn_line tl ON txn.id = tl.txn_id
          WHERE type = 'vendor'
            AND item_id = {$this->item_id}
            AND created < '{$txn->created}'";

    $cost= $this->orm->for_table('txn')->raw_query($q)->find_one();
    return $cost->cost;
  }

  public function as_array() {
    $data= parent::as_array();
    $data['sale_price']= $this->sale_price();
    return $data;
  }

  public function getFields() {
    $data= parent::getFields();
    $data[]= 'sale_price';
    return $data;
  }

  public function set($name, $value= null) {
    if ($name == 'sale_price') {
      $item= $this->item();

      if (preg_match('/^([\d.]*)(\/|%)$/', $value, $m)) {
        $discount= $m[1];
        $discount_type= 'percentage';
        $retail_price= ($item->retail_price > 0) ? $item->retail_price : $this->retail_price;
        $discount_manual= 1;
      } elseif (preg_match('/^(-)?\$?(-?\d*\.?\d*)$/', $value, $m)) {
        $value= "$m[1]$m[2]";
        if ($this->txn()->type == 'vendor') {
          $discount_type= null;
          $discount= null;
          $retail_price= $value;
        } else {
          $discount= ($item->retail_price > 0) ? $value : null;
          $discount_type= ($item->retail_price > 0) ? 'fixed' : null;
          $retail_price= ($item->retail_price > 0) ? $item->retail_price : $value;
        }
        $discount_manual= 1;
      } elseif (preg_match('/^(cost)$/', $value)) {
        $discount= $this->item()->vendor_items()->min('net_price');
        $discount_type= 'fixed';
        $retail_price= $item->retail_price;
        $discount_manual= 1;
      } elseif (preg_match('/^(msrp)$/', $value)) {
        $discount= null;
        $discount_type= null;
        $retail_price= $item->retail_price;
        $discount_manual= 1;
      } elseif (preg_match('/^(def|\.\.\.)$/', $value)) {
        $discount= $item->discount;
        $discount_type= $item->discount_type;
        $retail_price= $item->retail_price;
        $discount_manual= 0;
      } else {
        throw new \Exception("Did not understand price.");
      }

      parent::set('retail_price', $retail_price);
      parent::set('discount', $discount);
      parent::set('discount_type', $discount_type);
      parent::set('discount_manual', $discount_manual);
    } elseif ($name == 'data') {
      return parent::set('data', json_encode($value));
    } elseif ($name == 'override_name') {
      /* Reset override_name when given '...' */
      return parent::set('override_name', $value == '...' ? null : $value);
    } else {
      return parent::set($name, $value);
    }
  }

}
