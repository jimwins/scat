<?php
namespace Scat\Model;

class Brand extends \Scat\Model {
  public function products() {
    return $this->has_many('Product');
  }
}

