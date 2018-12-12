<?php
namespace Scat;

class Txn extends \Model implements \JsonSerializable {
  public function items() {
    return $this->has_many('TxnLine', 'txn');
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

class TxnLine extends \Model {
  public function txn() {
    return $this->belongs_to('Txn', 'txn');
  }
}
