<?
include '../scat.php';
include '../lib/person.php';

$person_id= (int)$_REQUEST['person'];

$person= person_load($db, $person_id);
if (!$person) {
  die_jsonp("No such person.");
}

if ($person['suppress_loyalty']) {
  echo jsonp(array("rewards" => array()));
  exit;
}

$points= (int)$person['points_available']; // might be NULL

$q= "SELECT item_id, cost, code, name, retail_price
       FROM loyalty_reward
       JOIN item ON item.id = item_id
      WHERE cost <= $points
      ORDER BY cost DESC";

$r= $db->query($q)
  or die_query($db, $q);

$rewards= array();
while ($row= $r->fetch_assoc()) {
  $rewards[]= $row;
}

echo jsonp(array("rewards" => $rewards));
