<?php
namespace Scat\Model;

class InternalAd extends \Scat\Model {
  public function href() {
    switch ($this->link_type) {
    case 'item':
      return '/catalog/' . $this->item()->code;

    case 'product':
      return '/catalog/' . $this->product()->full_slug();

    case 'url':
      return $this->link_url;

    default:
      throw new \Exception("Don't know href for '{$this->link_type}'");
    }
  }

  public function item() {
    if ($this->link_type == 'item') {
      return $this->belongs_to('Item', 'link_id')->find_one();
    }
    return null; // Should this be an exception?
  }

  public function product() {
    if ($this->link_type == 'product') {
      return $this->belongs_to('Product', 'link_id')->find_one();
    }
    return null; // Should this be an exception?
  }

  public function image() {
    return $this->belongs_to('Image')->find_one();
  }

  public function departmentsUsedBy() {
    return $this->has_many_through('Department');
  }

  public function productsUsedBy() {
    return $this->has_many_through('Product');
  }
}
