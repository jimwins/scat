<?php
namespace Scat;

class Brand extends \Model {
  public function products() {
    return $this->has_many('Product');
  }
}

