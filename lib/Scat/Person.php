<?php
namespace Scat;

class Person extends \Model implements \JsonSerializable {

  public function loyalty() {
    return $this->has_many('Loyalty');
  }

  public function jsonSerialize() {
    return $this->asArray();
  }
}
