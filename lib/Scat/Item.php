<?
namespace Scat;

class Item extends \Model {
  /* XXX Legacy, should get from parent product */
  public function brand() {
    return $this->belongs_to('Brand', 'brand');
  }

  public function product() {
    return $this->belongs_to('Product');
  }

  public function barcodes() {
    return $this->has_many('Barcode', 'item');
  }

  public function full_slug() {
    $product= $this->product()->find_one();
    if ($product)
      $subdept= $product->dept()->find_one();
    if ($subdept)
      $dept= $subdept->parent()->find_one();
    if (!$product || !$subdept || !$dept)
      return "";

    return
      $dept->slug . '/' .
      $subdept->slug . '/' .
      $product->slug . '/' .
      $this->code;
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

  public function stock() {
    return $this->has_many('TxnLine', 'item')
                ->sum('allocated') ?: 0;
  }

  public function prop65_warning() {
    return $this->belongs_to('Prop65_Warning', 'prop65')->find_one();
  }

  public function recent_sales($days= 90) {
    $days= (int)$days; // Make sure we have an integer
    $q= "SELECT SUM(-1 * allocated) AS sold
           FROM txn
           JOIN txn_line ON txn.id = txn_line.txn
          WHERE type = 'customer'
            AND item = {$this->id}
            AND created BETWEEN NOW() - INTERVAL $days DAY AND NOW()";

    $res= \ORM::for_table('txn')->raw_query($q)->find_one();

    error_log(json_encode($res));
    return $res->sold;
  }
}

class Barcode extends \Model {
  public function item() {
    return $this->belongs_to('Item', 'item');
  }
}

class Prop65_Warning extends \Model {
}
