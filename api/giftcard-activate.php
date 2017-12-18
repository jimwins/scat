<?php

include '../scat.php';

$card= $db->escape($_REQUEST['card']);
$card= preg_replace('/^RAW-/', '', $card);

$balance= (float)$_REQUEST['balance'];

$q= "SELECT id, active
       FROM giftcard
      WHERE id = SUBSTRING('$card', 1, 7)
        AND pin = SUBSTRING('$card',-4)";

$r= $db->query($q);
if (!$r) die(jsonp(array("error" => "Unable to check card info.",
                         "detail" => $db->error)));
$row= $r->fetch_row();
if (!$r->num_rows) {
  die(jsonp(array("error" => "No such gift card exists.")));
}
if ($row[1]) {
  die(jsonp(array("error" => "Gift card is already active.")));
}
$card= $row[0];

$q= "UPDATE giftcard SET active = 1 WHERE id = $card";
$r= $db->query($q);
if (!$r) die(jsonp(array("error" => "Unable to activate card.",
                         "detail" => $db->error)));

if ($balance) {
  $q= "INSERT INTO giftcard_txn
          SET card_id = $card, amount = $balance, entered = NOW()";
  $r= $db->query($q);
  if (!$r) die(jsonp(array("error" => "Unable to add balance to card.",
                           "detail" => $db->error)));
}

echo jsonp(array("success" =>
                   sprintf("Card activated with \$%.2f balance.",
                           $balance)));
