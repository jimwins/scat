<?
include '../scat.php';
include '../lib/txn.php';

$type= $_REQUEST['type'];

if (!in_array($type, array('correction','vendor','customer','drawer'))) {
  die_json("Requested type not understood.");
}

$type= $db->escape($type);

$q= "START TRANSACTION;";
$r= $db->query($q);
if (!$r) die_query($db, $q);

$q= "SELECT 1 + MAX(number) AS number FROM txn WHERE type = '$type'";
$number= $db->get_one($q);

$tax_rate= ($type == 'customer') ? DEFAULT_TAX_RATE : 0;
$person= (int)$_REQUEST['person'];
if (!$person) $person = 'NULL';

$q= "INSERT INTO txn
        SET created= NOW(),
            type = '$type',
            number = $number,
            person = $person,
            tax_rate = $tax_rate";
$r= $db->query($q);
if (!$r) die_query($db, $q);

$txn_id= $db->insert_id;

$r= $db->commit();
if (!$r) die_query($db, "COMMIT");

$txn= txn_load($db, $txn_id);

echo jsonp(array('txn' => $txn));

