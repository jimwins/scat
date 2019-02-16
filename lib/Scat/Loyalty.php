<?php
namespace Scat;

class Loyalty extends \Model {
  public function person() {
    return $this->belongs_to('Person');
  }
  public function txn() {
    return $this->belongs_to('Txn');
  }
}
