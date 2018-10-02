<?php
include '../scat.php';

$amount= (float)$_REQUEST['amount'];

if (!$amount)
  die_jsonp(array("error" => "No amount specified."));

$txn_id= (int)$_REQUEST['txn'];

$card= $_REQUEST['card'];
$card= preg_replace('/^RAW-/', '', $card);

$id= substr($card, 0, 7);
$pin= substr($card, -4);

$card= Model::factory('Giftcard')
         ->where('id', $id)
         ->where('pin', $pin)
         ->find_one();

if (!$card) {
  die_jsonp(array("error" => "Unable to find that card."));
}

$txn= $card->txns()->create();
$txn->amount= $amount;
$txn->card_id= $card->id;
if ($txn_id) $txn->txn_id= $txn_id;
$txn->save();

echo jsonp($card);
