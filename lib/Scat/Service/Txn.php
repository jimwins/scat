<?php
namespace Scat\Service;

class Txn
{
  private $data;

  public function __construct(\Scat\Service\Data $data) {
    $this->data= $data;
  }

  public function create($type, $data= null) {
    $txn= $this->data->factory('Txn')->create();
    if ($data) {
      $txn->hydrate($data);
    }
    $txn->type= $type;

    // Generate number based on transaction type
    $number= $this->data->factory('Txn')
                  ->where('type', $txn->type)
                  ->max('number');
    $txn->number= $number + 1;

    // Could use real UUID() but this is shorter. Hardcoded '0' could be
    // replaced with a server-id to further avoid collisions
    $txn->uuid= sprintf("%08x%02x%s", time(), 0, bin2hex(random_bytes(8)));

    $txn->save();
    return $txn;
  }

  public function fetchById($id) {
    return $this->data->factory('Txn')->find_one($id);
  }

  public function find($type, $page, $limit= 25, $q= null) {
    $res= $this->data->factory('Txn')
                ->select('*')
                ->select_expr('COUNT(*) OVER()', 'records')
                ->order_by_desc('created')
                ->where('type', $type)
                ->limit($limit)->offset($page * $limit);

    if (preg_match('/^online:(\d+)/', $q, $m)) {
      $res= $res->where('online_sale_id', $m[1]);
    }

    if (preg_match('/^uuid:([a-z0-9]+)/', $q, $m)) {
      $res= $res->where('uuid', $m[1]);
    }

    return $res;
  }

  /* Till needs to get payments directly. */
  public function getPayments() {
    return $this->data->factory('Payment');
  }

  public function getShipments() {
    return $this->data->factory('Shipment');
  }

  public function fetchShipmentByTracker($tracker_id) {
    return $this->getShipments()
      ->where('tracker_id', $tracker_id)
      ->find_one();
  }

}
