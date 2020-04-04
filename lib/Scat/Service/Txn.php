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

}
