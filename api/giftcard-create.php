<?php
include '../scat.php';
include '../lib/giftcard.php';

$balance= (float)$_REQUEST['balance'];
$txn_id= (int)$_REQUEST['txn'];
$expires= $_REQUEST['expires'];

ORM::get_db()->beginTransaction();

$card= Model::factory('Giftcard')->create();

$card->set_expr('pin', 'SUBSTR(RAND(), 5, 4)');
if ($expires) {
  $card->expires= $expires . ' 23:59:59';
}
$card->active= 1;

$card->save();

/* Have to reload the card to make sure we have calculated values */
$card= Model::factory('Giftcard')->find_one($card->id);

if ($balance) {
  $txn= $card->txns()->create();
  $txn->amount= $balance;
  $txn->card_id= $card->id;
  if ($txn_id) $txn->txn_id= $txn_id;
  $txn->save();
}

ORM::get_db()->commit();

echo jsonp($card);
