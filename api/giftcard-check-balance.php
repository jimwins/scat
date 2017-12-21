<?php
include '../scat.php';

$card= $db->escape($_REQUEST['card']);
$card= preg_replace('/^RAW-/', '', $card);

$q= "SELECT giftcard.id, active, CONCAT(giftcard.id, pin) card, expires,
            DATE_FORMAT(MAX(entered), '%W, %M %e, %Y') AS latest,
            SUM(amount) AS balance
       FROM giftcard
       LEFT JOIN giftcard_txn ON giftcard.id = giftcard_txn.card_id
      WHERE giftcard.id = SUBSTRING('$card', 1, 7)
        AND pin = SUBSTRING('$card',-4)";

$r= $db->query($q);
if (!$r) die(jsonp(array("error" => "Unable to check card info.",
                         "detail" => $db->error)));

echo jsonp($r->fetch_assoc());
