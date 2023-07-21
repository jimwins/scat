<?php
namespace Scat\Model;

class TxnLine extends \Scat\Model {
  protected $_item;

  public function txn() {
    return $this->belongs_to('Txn')->find_one();
  }

  public function item() {
    // Simple memoization
    if (isset($this->_item)) return $this->_item;
    return ($this->_item= $this->belongs_to('Item')->find_one());
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
    $txn= $this->txn();

    /* XXX Special items have no cost. */
    /* TODO should probably be an exclusion list. */
    if ($this->tic != 0) return 0;

    $direction= $this->allocated > 0 ? '>' : '<';

    $q= "SELECT SUM(allocated) allocated
           FROM txn
           JOIN txn_line tl ON txn.id = tl.txn_id
          WHERE item_id = {$this->item_id}
            AND allocated $direction 0
            AND created < '{$txn->created}'";
    $allocated= $this->orm->for_table('txn')->raw_query($q)->find_one();
    $allocated= abs($allocated->allocated);

    $q= "SELECT allocated, retail_price, discount_type, discount
           FROM txn
           JOIN txn_line tl ON txn.id = tl.txn_id
          WHERE item_id = {$this->item_id}
            AND 0 $direction allocated
            AND created < '{$txn->created}'";
    $in= $this->orm->for_table('txn')->raw_query($q)->find_many();

    $cost= [];
    foreach ($in as $entry) {
      $cost= array_merge($cost, array_fill(0, abs($entry->allocated), $this->calcSalePrice($entry->retail_price, $entry->discount_type, $entry->discount)));
    }

    $allocating= abs($this->allocated);

    /* Not enough costs to find them for this? Use replacement cost */
    if (count($cost) < $allocated + $allocating) {
      /* Useful when there is a sale before a purchase */
      return $this->item()->best_cost() * $this->allocated;
    }

    $total= array_sum(array_slice($cost, $allocated, $allocating));

    return ($this->allocated < 0 ? -1 : 1) * $total;
  }

  public function as_array() {
    $data= parent::as_array();
    $data['sale_price']= $this->sale_price();
    $data['data']= json_decode($this->data);
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
      return parent::set('discount_manual', $discount_manual);
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
