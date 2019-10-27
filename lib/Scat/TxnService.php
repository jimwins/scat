<?php
namespace Scat;

class TxnService
{
  public function __construct() {
  }

  public function create() {
    return \Model::factory('Txn')->create();
  }

  public function fetchById($id) {
    return \Model::factory('Txn')->find_one($id);
  }

}
