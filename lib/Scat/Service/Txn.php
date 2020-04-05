<?php
namespace Scat\Service;

class Txn
{
  public function __construct() {
  }

  public function create($options) {
    return \Scat\Model\Txn::create($options);
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
}
