<?php
namespace Scat\Model;

class Loyalty extends \Scat\Model {
  public function person() {
    return $this->belongs_to('Person');
  }
  public function txn() {
    return $this->belongs_to('Txn');
  }

}
