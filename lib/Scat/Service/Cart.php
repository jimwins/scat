<?php
namespace Scat\Service;

class Cart
{
  private $data;

  public $uuid;

  public function __construct(\Scat\Service\Data $data) {
    $this->data= $data;
  }

  public function is_empty() {
    return true;
  }

}
