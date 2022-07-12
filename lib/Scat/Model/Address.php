<?php
namespace Scat\Model;

class Address extends \Scat\Model {
  public function is_po_box() {
    return \Scat\Service\Shipping::address_is_po_box($this);
  }
}
