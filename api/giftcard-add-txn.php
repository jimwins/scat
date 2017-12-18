<?php

include '../scat.php';

$card= $db->real_escape_string($_REQUEST['card']);
$card= preg_replace('/^RAW-/', '', $card);

$amount= (float)$_REQUEST['amount'];

if (!$amount)
  die(jsonp(array("error" => "No amount specified.")));

$q= "SELECT id, active
       FROM giftcard
      WHERE id = SUBSTRING('$card', 1, 7)
        AND pin = SUBSTRING('$card',-4)";
$r= $db->query($q);
if (!$r) die(jsonp(array("error" => "Unable to check card info.",
                         "detail" => $db->error)));
$row= $r->fetch_row();
if (!$r->num_rows || !$row[1]) {
  die(jsonp(array("error" => "No such gift card is active.")));
}
$card= $row[0];

$q= "INSERT INTO giftcard_txn
        SET card_id = $card, amount = $amount, entered = NOW()";
$r= $db->query($q);
if (!$r)
  die(jsonp(array("error" => "Unable to add transaction to card.",
                  "detail" => $db->error)));

$q= "SELECT SUM(amount) FROM giftcard_txn WHERE card_id = $card";
$r= $db->query($q);
if (!$r)
  die(jsonp(array("error" => "Unable to get card balance.",
                  "detail" => $db->error)));
$row= $r->fetch_row();
$balance= $row[0];

echo jsonp(array("success" =>
                   sprintf("Transaction added, balance is now \$%.2f.",
                           $balance),
                   "balance" => $balance,
                   "amount" => $amount));
