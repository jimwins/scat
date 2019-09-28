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
      return $this->pretty_phone();
    }
    return $this->id;
  }

  function pretty_phone() {
    if ($this->phone) {
      try {
        $phoneUtil= \libphonenumber\PhoneNumberUtil::getInstance();
        $num= $phoneUtil->parse($this->phone, 'US');
        return $phoneUtil->format($num,
                                  \libphonenumber\PhoneNumberFormat::NATIONAL);
      } catch (Exception $e) {
        // Punt!
        return $this->phone;
      }
    }
  }

  public function loyalty() {
    return $this->has_many('Loyalty');
  }

  public function jsonSerialize() {
    return $this->asArray();
  }
}
