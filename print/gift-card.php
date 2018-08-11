<?php

include '../scat.php';
include '../lib/giftcard.php';

$card= $_REQUEST['card'];

$id= substr($card, 0, 7);
$pin= substr($card, -4);

$card= Model::factory('Giftcard')
         ->where('id', $id)
         ->where('pin', $pin)
         ->find_one();

if (!$card) {
  die_jsonp(array("error" => "Unable to find info for that card."));
}

echo $card->getPDF();
