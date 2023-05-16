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

  public function findByStatus($status, $yesterday= null, $limit= null) {
    $query= $this->data->Factory('Cart');
    if (is_array($status)) {
      $query= $query->where_in('status', $status);
    } elseif ($status) {
      $query= $query->where('status', $status);
    }
    if ($yesterday) {
      $query= $query->where_raw("DATE(created) = DATE(NOW() - INTERVAL 1 DAY)");
    }

    // filter out annoying Google tests
    $query= $query->where_raw("email NOT RLIKE '^(fake.*@fakemail.com|johnsmithstore.*@gmail.com)$'");

    if ($limit) {
      $query= $query->limit($limit)->order_by_desc('id');
    }
    return $query->find_many();
  }
}
