<?
include '../scat.php';
include '../lib/person.php';

$from_id= (int)$_REQUEST['from'];
$from= person_load($db, $from_id);
if (!$from)
  die_jsonp('No such person to merge from.');

$to_id= $_REQUEST['to'];
$to= person_load($db, $to_id);
if (!$to)
  die_jsonp('No such person to merge to.');

$q= "START TRANSACTION";
$r= $db->query($q)
  or die_jsonp($db->error);

$q= "UPDATE txn SET person = $to_id WHERE person = $from_id";
$db->query($q)
  or die_query($db, $q);

$q= "UPDATE loyalty SET person_id = $to_id WHERE person_id = $from_id";
$db->query($q)
  or die_query($db, $q);

$q= "UPDATE vendor_item SET vendor = $to_id WHERE vendor = $from_id";
$db->query($q)
  or die_query($db, $q);

$q= "UPDATE timeclock SET person = $to_id WHERE person = $from_id";
$db->query($q)
  or die_query($db, $q);

$q= "UPDATE person SET deleted = 1, loyalty_number = NULL WHERE id = $from_id";
$db->query($q)
  or die_query($db, $q);

foreach (array('name', 'company', 'address', 'email', 'phone', 'loyalty_number',
               'birthday', 'notes', 'tax_id', 'payment_account_id') as $field) {
  if (!$to[$field] && $from[$field]) {
    $q= "UPDATE person SET $field = '" . $db->escape($from[$field]) . "'
          WHERE id = $to_id";
    $db->query($q)
      or die_query($db, $q);
  }
}

$db->commit()
  or die_query($db, "COMMIT");

$to= person_load($db, $to_id);

echo jsonp(array('person' => $to));
