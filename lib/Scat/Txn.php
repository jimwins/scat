<?php
namespace Scat;

class Txn extends \Model implements \JsonSerializable {
  public static function create($options) {
    $txn= \Model::factory('Txn')->create();

    foreach ($options as $key => $value) {
      $txn->$key= $value;
    }

    // Generate number based on transaction type
    $q= "SELECT 1 + MAX(number) AS number FROM txn WHERE type = '{$txn->type}'";
    $res= \ORM::for_table('txn')->raw_query($q)->find_one();
    $txn->number= $res->number;

    $txn->save();

    return $txn;
  }

  public function formatted_number() {
    $created= new \DateTime($this->created);
    return $this->type == 'vendor' ?
      ('') :
      ($created->format("Y") . "-" . $this->number);
  }

  public function items() {
    return $this->has_many('TxnLine', 'txn');
  }

  public function person_id() {
    return $this->person;
  }

  public function owner() {
    return $this->belongs_to('Person', 'person')->find_one();
  }

  function clearItems() {
    $this->orm->get_db()->beginTransaction();
    $this->items()->delete_many();
    $this->filled= null;
    $this->save();
    $this->orm->get_db()->commit();
    return true;
  }

  public function jsonSerialize() {
    $txn= $this->as_array();
    $txn['subtotal']= (float)$txn['subtotal'];
    $txn['total']= (float)$txn['total'];
    $txn['total_paid']= (float)$txn['total_paid'];
    $txn['no_rewards']= (int)$txn['no_rewards'];
    return $txn;
  }
}
