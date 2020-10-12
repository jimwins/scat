<?php
namespace Scat\Model;

class KitItem extends \Scat\Model {
  public function kit() {
    return $this->belongs_to('Item', 'kit_id')->find_one();
  }

  public function item() {
    return $this->belongs_to('Item')->find_one();
  }
}
