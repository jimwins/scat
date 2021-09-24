<?php
namespace Scat\Model;

class Image extends \Scat\Model {
  public function original() {
    return GUMLET_BASE .
           $this->uuid . '.' . $this->ext;
  }

  public function thumbnail() {
    return GUMLET_BASE .
           $this->uuid . '.' . $this->ext .
           '?w=128&h=128&mode=fit&fm=auto';
  }

  public function medium() {
    return GUMLET_BASE .
           $this->uuid . '.' . $this->ext .
           '?w=384&h=384&mode=fit&fm=auto';
  }

  public function large_square() {
    return GUMLET_BASE .
           $this->uuid . '.' . $this->ext .
           '?w=1024&h=1024&mode=fill&fm=auto';
  }

  public function productsUsedBy() {
    return $this->has_many_through('Product');
  }

  public function itemsUsedBy() {
    return $this->has_many_through('Item');
  }
}
