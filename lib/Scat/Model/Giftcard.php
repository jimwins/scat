<?php
namespace Scat\Model;

include dirname(__FILE__).'/../../../extern/php-barcode.php';

class Giftcard extends \Scat\Model {
  public function card() {
    return $this->id . $this->pin;
  }

  public function txns() {
    return $this->has_many('Giftcard_Txn', 'card_id');
  }

  public function created() {
    return $this->txns()->min('entered');
  }

  public function last_seen() {
    return $this->txns()->max('entered');
  }

  public function balance() {
    return $this->txns()->sum('amount');
  }

  public function owner() {
    return $this->has_one('Person')->find_one();
  }

  public function jsonSerialize() {
    $history= array();
    $balance= new \Decimal\Decimal(0);
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
      $balance= $balance + new \Decimal\Decimal($txn->amount);
      $latest= $txn->entered;
    }

    return array(
      'id' => $this->id,
      'pin' => $this->pin,
      'card' => $this->id . $this->pin,
      'expires' => $this->expires,
      'history' => $history,
      'balance' => (string)$balance->round(2),
      'latest' => $latest,
    );
  }

  function add_txn($amount, $txn_id= 0) {
    $txn= $this->txns()->create();
    $txn->amount= $amount;
    $txn->card_id= $this->id;
    if ($txn_id) $txn->txn_id= $txn_id;
    $txn->save();
  }
}

class Giftcard_Txn extends \Scat\Model {
  public function card() {
    return $this->belongs_to('Giftcard', 'card_id')->find_one();
  }

  public function txn() {
    return $this->belongs_to('Txn')->find_one();
  }
}
