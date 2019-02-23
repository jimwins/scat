<?php
namespace Scat;

class TxnLine extends \Model {
  public function txn() {
    return $this->belongs_to('Txn', 'txn');
  }
}
