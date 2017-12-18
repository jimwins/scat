<?php
include '../scat.php';

$card= $db->escape($_GET['card']);
$card= preg_replace('/^RAW-/', '', $card);

$q= "SELECT id, active, CONCAT(id, pin) card
       FROM giftcard
      WHERE id = SUBSTRING('$card', 1, 7) AND pin = SUBSTRING('$card',-4)";
$r= $db->query($q);
if (!$r) die(jsonp(array("error" => "Unable to check card info.",
                         "detail" => $db->error)));
$row= $r->fetch_row();
if (!$r->num_rows || !$row[1]) {
  die(jsonp(array("error" => "No such gift card is active.")));
}
$card= $row[0];
$full_card= $row[2];

# card is active, now check the balance!

$q= "SELECT CONCAT(card, pin) AS card,
            DATE_FORMAT(MAX(entered), '%W, %M %e, %Y') AS latest,
            SUM(amount) AS balance
       FROM giftcard_txn JOIN giftcard ON (giftcard.id = card_id)
      WHERE card = '$card'
      GROUP BY card";
$r= $db->query($q);
if (!$r) die(jsonp(array("error" => "Unable to check balance.",
                         "detail" => $db->error)));
if (!$r->num_rows) {
  die(jsonp(array("card" => $full_card,
                  "latest" => date("l, F j, Y"),
                  "balance" => 0.00)));
}

echo jsonp($r->fetch_assoc());
