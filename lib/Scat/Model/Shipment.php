<?php
namespace Scat\Model;

class Shipment extends \Scat\Model {
  public function txn() {
    return $this->belongs_to('Txn')->find_one();
  }

  public function dimensions() {
    return "{$this->length}x{$this->width}x{$this->height}";
  }

}
