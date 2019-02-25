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
}

class Barcode extends \Model {
  public function item() {
    return $this->belongs_to('Item', 'item');
  }
}
