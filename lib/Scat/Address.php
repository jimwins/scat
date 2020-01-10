<?php
namespace Scat;

class Address extends \Model implements \JsonSerializable {
  public function jsonSerialize() {
    return $this->as_array();
  }
}
