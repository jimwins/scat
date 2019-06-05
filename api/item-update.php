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

/* Plain string values */
foreach(array('name', 'short_name', 'variation', 'tic', 'color') as $key) {
  if (isset($_REQUEST[$key])) {
    $value= $db->real_escape_string($_REQUEST[$key]);
    // $key is one of our hardcoded values
    $q= "UPDATE item SET $key = '$value' WHERE id = $item_id";
    $r= $db->query($q)
      or die_query($db, $q);
  }
}

/* Plain integer values */
foreach(array('active', 'reviewed', 'product_id',
              'purchase_quantity', 'minimum_quantity',
              'prop65', 'hazmat', 'oversized') as $key) {
  if (isset($_REQUEST[$key])) {
    $value= (int)$_REQUEST[$key];
    // $key is one of our hardcoded values
    $q= "UPDATE item SET $key = $value WHERE id = $item_id";
    $r= $db->query($q)
      or die_query($db, $q);
  }
}

/* Decimal values */
foreach(array('length', 'width', 'height', 'weight') as $key) {
  if (isset($_REQUEST[$key])) {
    $value= $db->real_escape_string($_REQUEST[$key]);
    // $key is one of our hardcoded values
    $q= "UPDATE item
            SET $key = CAST('$value' AS DECIMAL(9,3))
          WHERE id = $item_id";
    $r= $db->query($q)
      or die_query($db, $q);
  }
}

if (strlen($_REQUEST['dimensions'])) {
  $dimensions= $db->real_escape_string($_REQUEST['dimensions']);
  list($l, $w, $h)= preg_split('/\s*x\s*/', $dimensions);
  $q= "UPDATE item
          SET length = CAST('$l' AS DECIMAL(9,3)),
              width = CAST('$w' AS DECIMAL(9,3)),
              height = CAST('$h' AS DECIMAL(9,3))
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

if (isset($_REQUEST['discount']) && $_REQUEST['discount'] !== '') {
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
  } elseif (preg_match('/^-\$?(\d*\.?\d*)$/', $discount, $m)) {
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

$product= array('id' => 0, 'name' => ''); /* Bare necessities for KO */
if ($item['product_id']) {
  $prod= \Scat\Product::getById($item['product_id']);
  $product= $prod->as_array();
  $product['full_slug']= $prod->full_slug();
}

echo jsonp(array('item' => $item, 'product' => $product));
