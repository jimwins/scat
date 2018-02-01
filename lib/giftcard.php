<?php

class Giftcard extends Model implements JsonSerializable {
  public function card() {
    return $self->id + $self->pin;
  }

  public function txns() {
    return $this->has_many('Giftcard_Txn', 'card_id');
  }

  public function jsonSerialize() {
    $history= array();
    $balance= 0.00;
    $latest= "";

    $txns= $this->txns()
             ->select('*')
             ->select_expr("IF(type = 'vendor' && YEAR(created) > 2013,
                               CONCAT(SUBSTRING(YEAR(created), 3, 2), number),
                               CONCAT(DATE_FORMAT(created, '%Y-'), number))",
                           'txn_name')
             ->left_outer_join('txn',
                               array('txn.id', '=', 'giftcard_txn.txn_id'))
             ->find_many();

    foreach ($txns as $txn) {
      $history[]= array( 'entered' => $txn->entered,
                         'amount' => $txn->amount,
                         'txn_id' => $txn->txn_id,
                         'txn_name' => $txn->txn_name );
      $balance= bcadd($balance, $txn->amount);
      $latest= $txn->entered;
    }

    return array(
      'id' => $this->id,
      'pin' => $this->pin,
      'card' => $this->id . $this->pin,
      'expires' => $this->expires,
      'history' => $history,
      'balance' => $balance,
      'latest' => $latest,
    );
  }
}

class Giftcard_Txn extends Model {
  public function card() {
    return $this->belongs_to('Giftcard', 'card_id');
  }

  public function txn() {
    return $this->belongs_to('Txn');
  }
}
