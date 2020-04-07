<?php
namespace Scat\Model;

class Timeclock extends \Model implements \JsonSerializable {
  public function person() {
    return $this->belongs_to('Person');
  }

  public function jsonSerialize() {
    return $this->as_array();
  }
}
