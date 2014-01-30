<?
include '../scat.php';
include '../lib/txn.php';
include '../lib/item.php';

$txn_id= (int)$_REQUEST['txn'];

$item= (int)$_REQUEST['item'];
$search= $_REQUEST['q'];

if (!$search && !$item) die_jsonp('no query specified');

if ($txn_id) {
  $txn= txn_load($db, $txn_id);
  if ($txn['paid']) {
    die_jsonp("This order is already paid!");
  }
}

if (!$search)
  $search= "item:$item";

$items= item_find($db, $search, $_REQUEST['all'] ? FIND_ALL : 0);

// limit ourselves to 10 items
array_splice($items, 10);

/* if it is just one item, go ahead and add it to the invoice */
if (count($items) == 1) {
  if (!$txn_id) {
    $q= "START TRANSACTION;";
    $r= $db->query($q);
    if (!$r) die_query($db, $q);

    $q= "SELECT 1 + MAX(number) AS number FROM txn WHERE type = 'customer'";
    $r= $db->query($q);
    if (!$r) die_query($db, $q);
    $row= $r->fetch_assoc();

    $q= "INSERT INTO txn
            SET created= NOW(),
                type = 'customer',
                number = $row[number],
                tax_rate = " . DEFAULT_TAX_RATE;
    $r= $db->query($q);
    if (!$r) die_query($db, $q);

    $txn_id= $db->insert_id;

    $r= $db->commit();
    if (!$r) die_query($db, "COMMIT");

    $txn= txn_load($db, $txn_id);
  }

  // XXX some items should always be added on their own
  $unique= preg_match('/^ZZ-(frame|print|univ|canvas)/i', $items[0]['code']);

  $q= "SELECT id, ordered FROM txn_line
        WHERE txn = $txn_id AND item = {$items[0]['id']}";
  $r= $db->query($q);
  if (!$r) die_query($db, $q);

  if (!$unique && $r->num_rows) {
    $row= $r->fetch_assoc();
    $items[0]['line_id']= $row['id'];

    /* if found via barcode, we may have better quantity */
    $quantity= 1;
    if ($items[0]['barcode'][$search])
      $quantity= $items[0]['barcode'][$search];

    $items[0]['quantity']= -1 * ($row['ordered'] - $quantity);

    $q= "UPDATE txn_line SET ordered = -1 * {$items[0]['quantity']}
          WHERE id = {$items[0]['line_id']}";
    $r= $db->query($q);
    if (!$r) die_query($db, $q);
  } else {
    $q= "INSERT INTO txn_line (txn, item, ordered,
                               retail_price, discount, discount_type, taxfree)
         SELECT $txn_id AS txn, {$items[0]['id']} AS item, -1 AS ordered,
                retail_price, discount, discount_type, taxfree
           FROM item WHERE id = {$items[0]['id']}";
    $r= $db->query($q);
    if (!$r) die_query($db, $q);
    $items[0]['line_id']= $db->insert_id;
  }

  // XXX error handling
  txn_apply_discounts($db, $txn_id);

  if ($items[0]['sale_price']) {
    $sock= socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if (@socket_connect($sock, '127.0.0.1', 1888)) {
      $product= $items[0]['name'];
      $price= $items[0]['sale_price'];
      socket_write($sock,
                   sprintf("\x0d\x0a%-19.19s\x0a\x0d$%18.2f ",
                           $product, $price));
    }
  }

  $txn= txn_load($db, $txn_id);
  $items= txn_load_items($db, $txn_id);

  echo jsonp(array('txn' => $txn, 'items' => $items));
} else {
  echo jsonp(array('matches' => $items));
}


