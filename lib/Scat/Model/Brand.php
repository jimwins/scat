<?php
namespace Scat\Model;

class Brand extends \Model {
  public function products() {
    return $this->has_many('Product');
  }
}

