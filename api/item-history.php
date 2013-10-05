<?
include '../scat.php';
include '../lib/item.php';

$id= (int)$_REQUEST['id'];

$code= $_REQUEST['code'];

if (!$id && $code) {
  $code= $db->escape($code);
  $q= "SELECT id FROM item WHERE code = '$code'";
  $id= $db->get_one($q);
};

if (!$id)
  die_jsonp("No item specified.");

$q= "SELECT created, txn, txn.type, txn.number,
            SALE_PRICE(retail_price, discount_type, discount) sale_price,
            SUM(allocated) qty
       FROM txn_line
       JOIN txn ON (txn_line.txn = txn.id)
      WHERE item = $id
      GROUP BY txn
      ORDER BY created";

echo jsonp(array('item' => $item));

