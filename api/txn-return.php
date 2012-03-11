<?
include '../scat.php';
include '../lib/txn.php';

$txn_id= (int)$_REQUEST['txn'];

$txn= txn_load($db, $txn_id);
if (!$txn['paid']) {
  die_jsonp("Can't return an order that hasn't been paid for!");
}

$q= "SELECT 1 + MAX(number) AS number FROM txn WHERE type = 'customer'";
$r= $db->query($q);
if (!$r)
  die_query($db, $q);
$row= $r->fetch_assoc();

$q= "INSERT INTO txn
        SET created= NOW(),
            type = 'customer',
            number = $row[number],
            returned_from = $txn_id,
            tax_rate = " . DEFAULT_TAX_RATE;
$r= $db->query($q);
if (!$r)
  die_query($db, $q);

$new_txn_id= $db->insert_id;

$q= "INSERT INTO txn_line (txn, line, item, ordered, allocated, override_name,
                           retail_price, discount_type, discount, taxfree)
     SELECT $new_txn_id AS txn,
            line, item,
            -ordered AS ordered,
            -allocated AS allocated,
            override_name,
            retail_price, discount_type, discount,
            taxfree
       FROM txn_line WHERE txn = $txn_id";
$r= $db->query($q);
if (!$r)
  die_query($db, $q);

echo jsonp(array('txn' => txn_load($db, $new_txn_id),
                 'items' => txn_load_items($db, $new_txn_id),
                 'payments' => array(),
                 'notes' => array()));
