<?php
namespace Scat;

class Department extends \Model {
  public function parent() {
    return $this->belongs_to('Department', 'parent_id');
  }

  public function departments() {
    return $this->has_many('Department', 'parent_id');
  }

  public function products() {
    return $this->has_many('Product');
  }
}


