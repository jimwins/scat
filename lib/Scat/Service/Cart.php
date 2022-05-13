<?php
namespace Scat\Service;

class Cart
{
  private $data;

  public function __construct(\Scat\Service\Data $data) {
    $this->data= $data;
  }

  public function create($data= null) {
    $cart= $this->data->Factory('Cart')->create();
    if ($data) {
      $cart->hydrate($data);
    }

    // Could use real UUID() but this is shorter. Hardcoded '1' could be
    // replaced with a server-id to further avoid collisions
    $cart->uuid= sprintf("%08x%02x%s", time(), 1, bin2hex(random_bytes(8)));

    return $cart;
  }

  public function findByUuid($uuid) {
    return $this->data->Factory('Cart')->where('uuid', $uuid)->find_one();
  }

}