<?
include '../scat.php';
include '../lib/item.php';

// build list of items
$items= $_REQUEST['items'];
if (!preg_match('/^(\d+)(,\d+)*$/', $items)) {
  die_jsonp("Invalid items specified.");
}

$props= array();

if (isset($_REQUEST['brand_id']) && (int)$_REQUEST['brand_id']) {
  $brand= (int) $_REQUEST['brand_id'];
  $props[]= "brand = $brand";
}

if (!empty($_REQUEST['retail_price'])) {
  $retail_price= preg_replace('/^\\$/', '', $_REQUEST['retail_price']);
  $retail_price= $db->real_escape_string($retail_price);
  $props[]= "retail_price = '$retail_price'";
}

if (!empty($_REQUEST['discount'])) {
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

  $props[]= "discount_type = $discount_type";
  $props[]= "discount = $discount";
}

if (!empty($_REQUEST['minimum_quantity'])) {
  $minimum_quantity= (int)$_REQUEST['minimum_quantity'];
  $props[]= "minimum_quantity = $minimum_quantity";
}

// build query
$set= join(', ', $props);
$q= "UPDATE item SET $set
      WHERE id IN ($items)";

$r= $db->query($q)
  or die_query($db, $q);

$ret= array();
foreach (explode(',', $items) as $id) {
  $ret[]= item_load($db, $id);
}

echo jsonp(array('items' => $ret));

exit;
# OLD STUFF HERE XXX

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

if (isset($_REQUEST['brand_id']) && (int)$_REQUEST['brand_id']) {
  $brand= (int) $_REQUEST['brand_id'];
  $q= "UPDATE item
          SET brand = $brand
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
    if ($stock > $item['stock']) {
      $cost= 0.00;
    } else {
      $q= "SELECT retail_price
             FROM txn_line JOIN txn ON (txn_line.txn = txn.id)
            WHERE item = $item_id AND type = 'vendor'
            ORDER BY created DESC
            LIMIT 1";
      $cost= $db->get_one($q);
      if (!$cost) $cost= 0.00;
    }

    $q= "INSERT INTO txn_line
            SET txn = $txn_id,
                item = $item_id,
                retail_price = $cost,
                ordered = $diff,
                allocated = $diff";

    $r= $db->query($q)
      or die_query($db, $q);
  }
}


$item= item_load($db, $item_id);

echo jsonp(array('item' => $item));

