<?php
namespace Scat\Model;

class Address extends \Scat\Model {
  public function is_po_box() {
    return \Scat\Service\Shipping::address_is_po_box($this);
  }

  public function setFromEasypostAddress($easypost_address) {
    $this->easypost_id= $easypost_address->id;
    $this->name= $easypost_address->name;
    $this->company= $easypost_address->company;
    $this->street1= $easypost_address->street1;
    $this->street2= $easypost_address->street2;
    $this->city= $easypost_address->city;
    $this->state= $easypost_address->state;
    $this->zip= $easypost_address->zip;
    $this->country= $easypost_address->country;
    $this->phone= $easypost_address->phone;
    $this->verified= $easypost_address->verifications->delivery->success ? '1' : '0';
    $this->residential= $easypost_address->residential ? '1' : '0';
    if ($this->verified) {
      $this->timezone=
        $easypost_address->verifications->delivery->details->time_zone;
      $this->latitude=
        $easypost_address->verifications->delivery->details->latitude;
      $this->longitude=
        $easypost_address->verifications->delivery->details->longitude;
    }
  }
}
