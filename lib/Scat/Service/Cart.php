<?php
namespace Scat\Service;

class Cart
{
  public function __construct(
    private \Scat\Service\Data $data
  ) {
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

  public function findByStatus($status) {
    return $this->data->Factory('Cart')->where('status', $status)->find_many();
  }
}
