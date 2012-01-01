<?
include '../scat.php';
include '../lib/txn.php';

$id= (int)$_REQUEST['id'];

$type= $_REQUEST['type'];
$number= (int)$_REQUEST['number'];

if (!$id && $type) {
  $q= "SELECT id FROM txn
        WHERE type = '". $db->real_escape_string($type) ."'
          AND number = $number";
  $r= $db->query($q);

  if (!$r->num_rows)
    die_jsonp("No such transaction.");

  $row= $r->fetch_row();
  $id= $row[0];
}

if (!$id)
  die_jsonp("No transaction specified.");

$txn= txn_load($db, $id);

$q= "SELECT
            txn_line.id AS line_id, item.code,
            IFNULL(override_name, item.name) name,
            txn_line.retail_price msrp,
            IF(txn_line.discount_type,
               CASE txn_line.discount_type
                 WHEN 'percentage' THEN CAST(ROUND_TO_EVEN(txn_line.retail_price * ((100 - txn_line.discount) / 100), 2) AS DECIMAL(9,2))
                 WHEN 'relative' THEN (txn_line.retail_price - txn_line.discount) 
                 WHEN 'fixed' THEN (txn_line.discount)
               END,
               txn_line.retail_price) price,
            IFNULL(CONCAT('MSRP $', txn_line.retail_price, ' / Sale: ',
                          CASE txn_line.discount_type
              WHEN 'percentage' THEN CONCAT(ROUND(txn_line.discount), '% off')
              WHEN 'relative' THEN CONCAT('$', txn_line.discount, ' off')
            END), '') discount,
            ordered * IF(txn.type = 'customer', -1, 1) AS quantity,
            allocated * IF(txn.type = 'customer', -1, 1) AS allocated,
            (SELECT SUM(allocated) FROM txn_line WHERE item = item.id) AS stock
       FROM txn
       LEFT JOIN txn_line ON (txn.id = txn_line.txn)
       JOIN item ON (txn_line.item = item.id)
      WHERE txn.id = $id
      ORDER BY line ASC";

$r= $db->query($q)
  or die_query($db, $q);

$items= array();
while ($row= $r->fetch_assoc()) {
  /* force numeric values to numeric type */
  $row['msrp']= (float)$row['msrp'];
  $row['price']= (float)$row['price'];
  $row['quantity']= (int)$row['quantity'];
  $row['stock']= (int)$row['stock'];
  $items[]= $row;
}

$r= $db->query($q)
  or die_query($db, $q);

$q= "SELECT id, processed, method, amount
       FROM payment
      WHERE txn = $id
      ORDER BY processed ASC";

$r= $db->query($q)
  or die_query($db, $q);

$payments= array();
while ($row= $r->fetch_assoc()) {
  /* force numeric values to numeric type */
  $row['amount']= (float)$row['amount'];
  $payments[]= $row;
}

$q= "SELECT id, entered, content
       FROM txn_note
      WHERE txn = $id
      ORDER BY entered ASC";

$notes= array();
while ($row= $r->fetch_assoc()) {
  /* force numeric values to numeric type */
  $notes[]= $row;
}

echo generate_jsonp(array('txn' => $txn,
                          'items' => $items,
                          'payments' => $payments,
                          'notes' => $notes));
