<?php
namespace Scat;

class Person extends \Model implements \JsonSerializable {
  public function friendly_name() {
    if ($this->name || $this->company) {
      return $this->name .
             ($this->name && $this->company ? ' / ' : '') .
             $this->company;
    }
    if ($this->email) {
      return $this->email;
    }
    if ($this->phone) {
      return $this->phone;
    }
    return $this->id;
  }

  public function loyalty() {
    return $this->has_many('Loyalty');
  }

  public function jsonSerialize() {
    return $this->asArray();
  }
}
