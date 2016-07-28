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

$person= $txn['person'] ? $txn['person'] : 'NULL';

$q= "INSERT INTO txn
        SET created= NOW(),
            type = 'customer',
            number = $row[number],
            person = $person,
            returned_from = $txn_id,
            tax_rate = " . DEFAULT_TAX_RATE;
$r= $db->query($q);
if (!$r)
  die_query($db, $q);

$new_txn_id= $db->insert_id;

$q= "INSERT INTO txn_line (txn, item, ordered, allocated, override_name,
                           retail_price, discount_type, discount, taxfree)
     SELECT $new_txn_id AS txn,
            item,
            -ordered AS ordered,
            -allocated AS allocated,
            override_name,
            retail_price, discount_type, discount,
            taxfree
       FROM txn_line WHERE txn = $txn_id";
$r= $db->query($q);
if (!$r)
  die_query($db, $q);

// Check for discounts, and adjust prices as necessary
$q= "SELECT SUM(discount) FROM payment WHERE txn = $txn_id";

$discount= $db->get_one($q);

if ($discount) {
  $q= "UPDATE txn_line
          SET discount_type = 'fixed',
              discount = sale_price(retail_price, discount_type, discount) *
                         (1 - $discount / 100)
        WHERE txn = $new_txn_id";

  $r= $db->query($q)
    or die_query($db, $q);

  if ($db->affected_rows) {
    $q= "INSERT INTO txn_note
            SET txn = $new_txn_id, entered = NOW(),
                content = 'Applied extra $discount% discount to items from original purchase.'";

    $r= $db->query($q)
      or die_query($db, $q);
  }
}

echo jsonp(txn_load_full($db, $new_txn_id));
