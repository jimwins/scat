<?php
namespace Scat;

class TxnService
{
  public function __construct() {
  }

  public function create($options) {
    return \Scat\Txn::create($options);
  }

  public function fetchById($id) {
    return \Model::factory('Txn')->find_one($id);
  }

}
