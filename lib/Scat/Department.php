<?php
namespace Scat;

class Department extends \Model implements \JsonSerializable {
  public function parent() {
    return $this->belongs_to('Department', 'parent_id');
  }

  public function departments() {
    return $this->has_many('Department', 'parent_id');
  }

  public function products($only_active= true) {
    return $this->has_many('Product')->where_gte('active', (int)$only_active);
  }

  public function jsonSerialize() {
    $array= $this->as_array();
    return $array;
  }
}
