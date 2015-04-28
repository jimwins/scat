<?
include '../scat.php';
include '../lib/txn.php';
include '../lib/item.php';

$txn_id= (int)$_REQUEST['txn'];

if ($txn_id) {
  $txn= txn_load($db, $txn_id);
  if ($txn['paid']) {
    die_jsonp("This order is already paid!");
  }
}

foreach ($_REQUEST['items'] as $item => $qty) {
  $q= "INSERT INTO txn_line (txn, item, ordered, retail_price, taxfree)
       SELECT $txn_id AS txn, $item AS item, $qty AS ordered,
              (SELECT net_price
                 FROM vendor_item
                WHERE item = $item AND vendor = {$txn['person']}
                ORDER BY id DESC LIMIT 1), taxfree
         FROM item WHERE id = $item";
  $r= $db->query($q);
  if (!$r) die_query($db, $q);
}

$txn= txn_load($db, $txn_id);
$items= txn_load_items($db, $txn_id);

echo jsonp(array('txn' => $txn, 'items' => $items));
