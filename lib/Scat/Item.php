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
}

class Barcode extends \Model {
  public function item() {
    return $this->belongs_to('Item', 'item');
  }
}

