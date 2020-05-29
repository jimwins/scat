<?php
namespace Scat\Service;

class Giftcard
{
  private $data;

  public function __construct(\Scat\Service\Data $data) {
    $this->data= $data;
  }

  public function check_balance($card) {
    $card= preg_replace('/^RAW-/', '', $card);

    $id= substr($card, 0, 7);
    $pin= substr($card, -4);

    $card= $this->data->factory('Giftcard')
             ->where('id', $id)
             ->where('pin', $pin)
             ->find_one();

    if (!$card) {
      throw new \Exception("Unable to find info for that card.");
    }

    return $card;
  }

  public function add_txn($card, $amount, $txn_id= 0) {
    if (!$amount) {
      throw new \Exception("No amount specified.");
    }

    $card= $this->check_balance($card);

    $txn= $card->txns()->create();
    $txn->amount= $amount;
    $txn->card_id= $card->id;
    if ($txn_id) $txn->txn_id= $txn_id;
    $txn->save();

    return $card;
  }
}
