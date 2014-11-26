<?php
require '../scat.php';
require '../lib/txn.php';

$id= (int)$_REQUEST['id'];

if (!$id)
  die("No id specified.");

$txn= txn_load_full($db, $id);
if (!$txn)
  die("Transaction not found.");

header("Content-type: text/csv");
if ($_REQUEST['dl']) {
  header('Content-disposition: attachment; filename="' .
         'PO' . $txn['txn']['formatted_number'] . '.csv"');
}
echo ",Name,MSRP,Sale,Net,Qty,Ext,Barcode\r\n";

foreach ($txn['items'] as $item) {
  echo $item['code'], ",",
       $item['name'], ",",
       $item['msrp'], ",",
       $item['price'], ",",
       $item['price'], ",",
       $item['quantity'], ",",
       $item['ext_price'], "\r\n";
}
