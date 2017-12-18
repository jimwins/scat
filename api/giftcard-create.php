<?php

include '../scat.php';

$balance= (float)$_REQUEST['balance'];

$q= "INSERT INTO giftcard 
        SET pin = SUBSTR(RAND(), 5, 4),
            active = 1";
$r= $db->query($q);
if (!$r) die(jsonp(array("error" => "Unable to create card.",
                         "detail" => $db->error)));

$q= "SELECT CONCAT(id, pin) card, id, pin FROM giftcard
      WHERE id = LAST_INSERT_ID()";
$r= $db->query($q);
if (!$r) die(jsonp(array("error" => "Unable to create card.",
                         "detail" => $db->error)));
$card= $r->fetch_assoc();

if ($balance) {
  $q= "INSERT INTO giftcard_txn
          SET card_id = $card[id],
              amount = $balance,
              entered = NOW()";

  $r= $db->query($q);
  if (!$r) die(jsonp(array("error" => "Unable to add balance to card.",
                                    "detail" => $db->error)));
}

echo jsonp(array("card" => $card['card'],
                 "balance" => sprintf("%.2f", $balance),
                 "success" =>sprintf("Card activated with \$%.2f balance.",
                                     $balance)));
