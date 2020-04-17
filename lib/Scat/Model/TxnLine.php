<?php
namespace Scat\Model;

class TxnLine extends \Model {
  public function txn() {
    return $this->belongs_to('Txn')->find_one();
  }

  public function item() {
    return $this->belongs_to('Item', 'item')->find_one();
  }

  // We don't show codes starting with ZZ-, they're special
  public function code() {
    $code= $this->item()->code;
    return preg_match('/^ZZ-/', $code) ? '' : $this->item()->code;
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
            ' / Sale: ' . $this->discount . '% off' :
            ($this->discount_type == 'relative' ?
             ' / Sale: $' . $this->discount . ' off' :
             ''));
  }

  public function sale_price() {
    switch ($this->discount_type) {
    case 'percentage':
      // TODO fix rounding
      return bcmul($this->retail_price,
                   bcdiv(bcsub(100, $this->discount),
                         100));
    case 'relative':
      return bcsub($this->retail_price, $this->discount);
    case 'fixed':
      return $this->discount;
    case '':
    case null:
      return $this->retail_price;
    default:
      throw new Exception('Did not understand discount for item.');
    }
  }

  public function ext_price() {
    return bcmul($this->sale_price(), $this->allocated);
  }

  public function vendor_sku() {
    $vendor_id= $this->txn()->person_id;
    if (!$vendor_id) return '';
    $vendor_items= $this->has_many('VendorItem', 'item', 'item')
                        ->where('vendor', $vendor_id)
                        ->order_by_asc('purchase_quantity')
                        ->find_many();
    if (!$vendor_items) return '';
    $sku= '';
    foreach ($vendor_items as $item) {
      if ($item->purchase_quantity <= abs($this->ordered)) {
        $sku= $item->vendor_sku;
      }
    }
    return $sku;
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
            AND item_id = {$this->item}
            AND filled < '{$txn->created}'";

    $cost= \ORM::for_table('txn')->raw_query($q)->find_one();
    return $cost->cost;
  }
}
