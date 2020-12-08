<?php
include '../scat.php';

$card= $_REQUEST['card'];
$card= preg_replace('/^RAW-/', '', $card);

$id= substr($card, 0, 7);
$pin= substr($card, -4);

$card= \Titi\Model::factory('Giftcard')
         ->where('id', $id)
         ->where('pin', $pin)
         ->find_one();

if (!$card) {
  die_jsonp(array("error" => "Unable to find info for that card."));
}

echo jsonp($card);
