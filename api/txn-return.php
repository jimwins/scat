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
$uuid= $txn['uuid'] ? "REVERSE('{$txn['uuid']}')" : 'NULL';

$q= "INSERT INTO txn
        SET created= NOW(),
            type = 'customer',
            number = $row[number],
            person_id = $person,
            uuid = $uuid,
            returned_from_id = $txn_id,
            tax_rate = {$txn['tax_rate']}";
$r= $db->query($q);
if (!$r)
  die_query($db, $q);

$new_txn_id= $db->insert_id;

$q= "INSERT INTO txn_line (txn_id, item_id, ordered, allocated, override_name,
                           retail_price, discount_type, discount, taxfree,
                           tic, tax)
     SELECT $new_txn_id AS txn_id,
            item_id,
            -ordered AS ordered,
            -allocated AS allocated,
            override_name,
            retail_price, discount_type, discount,
            taxfree, tic, -tax
       FROM txn_line WHERE txn_id = $txn_id";
$r= $db->query($q);
if (!$r)
  die_query($db, $q);

// Check for discounts, and adjust prices as necessary
$q= "SELECT SUM(discount) FROM payment WHERE txn_id = $txn_id";

$discount= $db->get_one($q);

if ($discount) {
  $q= "UPDATE txn_line
          SET discount = sale_price(retail_price, discount_type, discount) *
                         (1 - $discount / 100),
              discount_type = 'fixed'
        WHERE txn_id = $new_txn_id";

  $r= $db->query($q)
    or die_query($db, $q);

  if ($db->affected_rows) {
    $q= "INSERT INTO note
            SET kind = 'txn', attach_id = $new_txn_id,
                content = 'Applied extra $discount% discount to items from original purchase.'";

    $r= $db->query($q)
      or die_query($db, $q);
  }
}

echo jsonp(txn_load_full($db, $new_txn_id));
