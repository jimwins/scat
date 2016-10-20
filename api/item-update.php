<?
include '../scat.php';
include '../lib/item.php';

$item_id= (int)$_REQUEST['item'];
if (!isset($_REQUEST['item']))
  $item_id= (int)$_REQUEST['id'];

$item= item_load($db, $item_id);

if (!$item)
  die_jsonp('No such item.');

if (isset($_REQUEST['code'])) {
  $code= $db->real_escape_string($_REQUEST['code']);
  $q= "UPDATE item
          SET code = '$code'
        WHERE id = $item_id";

  $r= $db->query($q)
    or die_query($db, $q);
}

if (isset($_REQUEST['name'])) {
  $name= $db->real_escape_string($_REQUEST['name']);
  $q= "UPDATE item
          SET name = '$name'
        WHERE id = $item_id";

  $r= $db->query($q)
    or die_query($db, $q);
}

// Workaround for jEditable sorting: id may be prefixed with _
$brand_id= isset($_REQUEST['brand_id']) ? ltrim($_REQUEST['brand_id'], '_') : 0;
if ((int)$brand_id) {
  $q= "UPDATE item
          SET brand = $brand_id
        WHERE id = $item_id";

  $r= $db->query($q)
    or die_query($db, $q);
}

if (isset($_REQUEST['retail_price'])) {
  $retail_price= preg_replace('/^\\$/', '', $_REQUEST['retail_price']);
  $retail_price= $db->real_escape_string($retail_price);

  $q= "UPDATE item
          SET retail_price = '$retail_price'
        WHERE id = $item_id";

  $r= $db->query($q)
    or die_query($db, $q);
}

if (isset($_REQUEST['discount'])) {
  $discount= preg_replace('/^\\$/', '', $_REQUEST['discount']);
  $discount= $db->real_escape_string($discount);
  if (preg_match('/^(\d*)(\/|%)( off)?$/', $discount, $m)) {
    $discount = (float)$m[1];
    $discount_type = "'percentage'";
  } elseif (preg_match('/^(\d*\.?\d*)$/', $discount, $m)) {
    $discount = (float)$m[1];
    $discount_type = "'fixed'";
  } elseif (preg_match('/^\$?(\d*\.?\d*)( off)?$/', $discount, $m)) {
    $discount = (float)$m[1];
    $discount_type = "'relative'";
  } elseif (preg_match('/^(def|\.\.\.)$/', $discount)) {
    $discount = 'NULL';
    $discount_type = 'NULL';
  } else {
    die_jsonp("Did not understand discount.");
  }

  $q= "UPDATE item
          SET 
              discount_type = $discount_type,
              discount = $discount 
        WHERE id = $item_id";

  $r= $db->query($q)
    or die_query($db, $q);
}

if (isset($_REQUEST['minimum_quantity'])) {
  $minimum_quantity= (int)$_REQUEST['minimum_quantity'];
  $q= "UPDATE item
          SET minimum_quantity = $minimum_quantity
        WHERE id = $item_id";

  $r= $db->query($q)
    or die_query($db, $q);
}

if (isset($_REQUEST['purchase_quantity'])) {
  $purchase_quantity= (int)$_REQUEST['purchase_quantity'];
  $q= "UPDATE item
          SET purchase_quantity = $purchase_quantity
        WHERE id = $item_id";

  $r= $db->query($q)
    or die_query($db, $q);
}

if (isset($_REQUEST['active'])) {
  $active= (int)$_REQUEST['active'];
  $q= "UPDATE item
          SET active = $active
        WHERE id = $item_id";

  $r= $db->query($q)
    or die_query($db, $q);
}

if (isset($_REQUEST['stock'])) {
  $stock= (int)$_REQUEST['stock'];

  if ($stock != $item['stock']) {
    $q= "SELECT id
           FROM txn
          WHERE type = 'correction'
            AND DATE(NOW()) = DATE(created)";
    $txn_id= $db->get_one($q);

    if (!$txn_id) {
      $q= "SELECT MAX(number) + 1 FROM txn WHERE type = 'correction'";
      $num= $db->get_one($q);

      $q= "INSERT INTO txn
              SET type = 'correction',
                  number = $num,
                  tax_rate = 0,
                  created = NOW()";
      $r= $db->query($q)
        or die_query($db, $q);
      $txn_id= $db->insert_id;
                          
    }

    $diff= $stock - $item['stock'];
    $cost= 0.00;

    $q= "SELECT retail_price
           FROM txn_line JOIN txn ON (txn_line.txn = txn.id)
          WHERE item = $item_id AND type = 'vendor'
          ORDER BY created DESC
          LIMIT 1";
    $cost= $db->get_one($q);
    if (!$cost) $cost= 0.00;

    $q= "SELECT id FROM txn_line WHERE txn = $txn_id AND item = $item_id";
    $txn_line= $db->get_one($q);

    if ($txn_line) {
      $q= "UPDATE txn_line
              SET ordered = ordered + $diff,
                  allocated = allocated + $diff,
                  retail_price = IF(ordered < 0, $cost, 0.00)
            WHERE id = $txn_line";
    } else {
      $q= "INSERT INTO txn_line
              SET txn = $txn_id,
                  item = $item_id,
                  retail_price = $cost,
                  ordered = $diff,
                  allocated = $diff";
    }

    $r= $db->query($q)
      or die_query($db, $q);
  }
}


$item= item_load($db, $item_id);

echo jsonp(array('item' => $item));
