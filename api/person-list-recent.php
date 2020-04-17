<?php

include '../scat.php';
include '../lib/person.php';

$q= "SELECT person.id, name, company, loyalty_number
       FROM txn
       JOIN person ON (txn.person_id = person.id)
      WHERE type = 'customer'
      ORDER BY IFNULL(txn.paid, txn.created) DESC LIMIT 10";

$r= $db->query($q)
  or die_query($db, $q);

$people= array();
while (($person= $r->fetch_assoc())) {
  $person['pretty_phone']= format_phone($person['loyalty_number']);
  $people[]= $person;
}

echo jsonp($people);
