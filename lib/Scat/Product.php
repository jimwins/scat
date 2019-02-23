<?php
namespace Scat;

class Product extends \Model implements \JsonSerializable {
  public function brand() {
    return $this->belongs_to('Brand');
  }

  public function dept() {
    return $this->belongs_to('Department');
  }

  public function items($only_active= true) {
    return $this->has_many('Item')
                ->select('item.*')
                ->where_gte('active', (int)$only_active);
  }

  public function full_slug() {
    return
      $this->dept()->find_one()->parent()->find_one()->slug . '/' .
      $this->dept()->find_one()->slug . '/' . $this->slug;
  }

  public function jsonSerialize() {
    return $this->asArray();
  }
}
