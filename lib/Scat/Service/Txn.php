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

    $txn->save();
    return $txn;
  }

  public function fetchById($id) {
    return \Model::factory('Txn')->find_one($id);
  }

  public function find($type, $page, $limit= 25) {
    return \Model::factory('Txn')
                ->select('*')
                ->select_expr('COUNT(*) OVER()', 'records')
                ->order_by_desc('created')
                ->where('type', $type)
                ->limit($limit)->offset($page * $limit)
                ->find_many();
  }

  /* Till needs to get payments directly. */
  public function getPayments() {
    return $this->data->factory('Payment');
  }
}
