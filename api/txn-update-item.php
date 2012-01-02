<?
include '../scat.php';
include '../lib/txn.php';

$txn_id= (int)$_REQUEST['txn'];
$id= (int)$_REQUEST['id'];

if (!$txn_id || !$id) die_jsonp('No transaction or item specified');

$txn= txn_load($db, $txn_id);
if ($txn['paid']) {
  die_jsonp("This order is already paid!");
}

if (!empty($_REQUEST['price'])) {
  $price= $_REQUEST['price'];
  if (preg_match('/^\d*(\/|%)$/', $price)) {
    $discount = (float)$price;
    $discount_type = "'percentage'";
    $price= 'item.retail_price';
  } elseif (preg_match('/^\d*\.?\d*$/', $price)) {
    $price= (float)$price;
    $discount_type= 'NULL';
    $discount= 'NULL';
  } elseif (preg_match('/^(def|\.\.\.)$/', $price)) {
    $discount = 'item.discount';
    $discount_type = 'item.discount_type';
    $price= 'item.retail_price';
  } else {
    die_jsonp("Did not understand price.");
  }

  $q= "UPDATE txn_line, item
          SET txn_line.retail_price = $price,
              txn_line.discount_type = $discount_type,
              txn_line.discount = $discount 
        WHERE txn = $txn_id AND txn_line.id = $id AND txn_line.item = item.id";

  $r= $db->query($q);
  if (!$r) {
    die_jsonp(array('error' => 'Query failed. ' . $db->error, 'query' => $q));
  }
}

if (!empty($_REQUEST['quantity'])) {
  $quantity= (int)$_REQUEST['quantity'];
  $q= "UPDATE txn_line
          SET ordered = -1 * $quantity
        WHERE txn = $txn_id AND txn_line.id = $id";

  $r= $db->query($q);
  if (!$r) {
    die_jsonp(array('error' => 'Query failed. ' . $db->error, 'query' => $q));
  }
}

if (isset($_REQUEST['name'])) {
  $name= $db->real_escape_string($_REQUEST['name']);
  $q= "UPDATE txn_line
          SET override_name = IF('$name' = '', NULL, '$name')
        WHERE txn = $txn_id AND txn_line.id = $id";

  $r= $db->query($q);
  if (!$r) {
    die_jsonp(array('error' => 'Query failed. ' . $db->error, 'query' => $q));
  }
}

$q= "SELECT txn_line.id AS line_id,
            IFNULL(override_name,
                   (SELECT name FROM item WHERE item.id = item)) AS name,
            ordered * -1 AS quantity,
            CAST(CASE discount_type
              WHEN 'percentage' THEN
                ROUND_TO_EVEN(retail_price * ((100 - discount) / 100), 2)
              WHEN 'relative' THEN (retail_price - discount) 
              WHEN 'fixed' THEN (discount)
              ELSE retail_price
            END AS DECIMAL(9,2)) price,
            IFNULL(CONCAT('MSRP $', retail_price, ' / Sale: ',
                          CASE discount_type
              WHEN 'percentage' THEN CONCAT(ROUND(discount), '% off')
              WHEN 'relative' THEN CONCAT('$', discount, ' off')
            END), '') discount,
            (SELECT SUM(allocated) FROM txn_line WHERE item = txn_line.id) stock
       FROM txn_line
      WHERE txn = $txn_id AND txn_line.id = $id";

$r= $db->query($q);
if (!$r) {
  die_jsonp(array('error' => 'Query failed. ' . $db->error, 'query' => $q));
}

$items= array();
while ($row= $r->fetch_assoc()) {
  /* force numeric values to numeric type */
  $row['price']= (float)$row['price'];
  $row['quantity']= (int)$row['quantity'];
  $row['stock']= (int)$row['stock'];
  $items[]= $row;
}

$txn= txn_load($db, $txn_id);

echo generate_jsonp(array('txn' => $txn, 'items' => $items));
