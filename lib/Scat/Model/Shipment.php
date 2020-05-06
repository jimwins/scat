<?php
namespace Scat\Model;

class Shipment extends \Scat\Model {
  public function txn() {
    return $this->belongs_to('Txn')->find_one();
  }

}
