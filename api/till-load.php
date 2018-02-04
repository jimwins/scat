<?php
require '../scat.php';

$q= "SELECT CAST(SUM(amount) AS DECIMAL(9,2)) FROM payment
      WHERE method IN ('cash','change','withdrawal')";
$current= $db->get_one($q)
  or die($db->error);

$q= "SELECT created FROM txn
      WHERE type = 'drawer'
      ORDER BY id DESC
      LIMIT 1";
$last_txn= $db->get_one($q)
  or die($db->error);

$q= "SELECT COUNT(*)
       FROM payment
      WHERE method = 'check' AND processed > '$last_txn'";
$checks= $db->get_one($q);

echo jsonp(array('current' => $current,
                 'checks' => (int)$checks));
