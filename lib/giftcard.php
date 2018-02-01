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

    $txns= $this->txns()->find_many();

    foreach ($txns as $txn) {
      $history[]= array( 'entered' => $txn->entered,
                         'amount' => $txn->amount,
                         'txn_id' => $txn->txn_id );
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
