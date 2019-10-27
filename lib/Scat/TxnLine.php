<?php
namespace Scat;

class TxnLine extends \Model {
  public function txn() {
    return $this->belongs_to('Txn', 'txn');
  }

  public function item_id() {
    return $this->item;
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
}
