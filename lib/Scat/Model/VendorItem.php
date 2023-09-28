<?
namespace Scat\Model;

class VendorItem extends \Scat\Model {
  public function item() {
    return $this->belongs_to('Item')->find_one();
  }

  public function vendor() {
    return $this->belongs_to('Person', 'vendor_id')->find_one();
  }

  public function set($name, $value= null) {
    if ($name == 'promo_price') {
      if (!strlen($value)) $value= null;
    }
    if ($name == 'dimensions') {
      return $this->setDimensions($value);
    }
    if ($name == 'promo_quantity' || $name == 'weight') {
      if ($value == '') $value= null;
    }

    return parent::set($name, $value);
  }

  public function dimensions() {
    if ($this->length && $this->width && $this->height)
      return $this->length . 'x' .
             $this->width . 'x' .
             $this->height;
  }

  public function setDimensions($dimensions) {
    if ($dimensions == '') {
      list($l, $w, $h)= [ null, null, null ];
    } else {
      list($l, $w, $h)= preg_split('/[^\d.]+/', trim($dimensions));
    }
    $this->length= $l;
    $this->width= $w;
    $this->height= $h;
    return $this;
  }

  public function getFields() {
    $fields= parent::getFields();
    $fields[]= 'dimensions';
    return $fields;
  }
}
