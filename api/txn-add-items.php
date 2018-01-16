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

foreach ((array)$_REQUEST['items'] as $item) {
  $q= "INSERT INTO txn_line (txn, item, ordered, retail_price, taxfree)
       SELECT $txn_id AS txn, $item[0] AS item, $item[1] AS ordered,
              (SELECT IF(promo_price AND promo_price < net_price,
                         promo_price, net_price)
                 FROM vendor_item
                WHERE item = $item[0] AND vendor = {$txn['person']}
                  AND vendor_item.active
                ORDER BY id DESC LIMIT 1), taxfree
         FROM item WHERE id = $item[0]";
  $r= $db->query($q);
  if (!$r) die_query($db, $q);
}

$txn= txn_load($db, $txn_id);
$items= txn_load_items($db, $txn_id);

echo jsonp(array('txn' => $txn, 'items' => $items));
