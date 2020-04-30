<?php
namespace Scat\Model;

class Loyalty extends \Model implements \JsonSerializable {
  public function person() {
    return $this->belongs_to('Person');
  }
  public function txn() {
    return $this->belongs_to('Txn');
  }

  public function jsonSerialize() {
    return $this->asArray();
  }
}
