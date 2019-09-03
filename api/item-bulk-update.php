<?
include '../scat.php';
include '../lib/item.php';

// build list of items
$items= $_REQUEST['items'];
if (!preg_match('/^(?:\d+)(?:,\d+)*$/', $items)) {
  die_jsonp("Invalid items specified.");
}

$props= array();

if (isset($_REQUEST['brand_id']) && (int)$_REQUEST['brand_id']) {
  $brand= (int) $_REQUEST['brand_id'];
  $props[]= "brand = $brand";
}

if (isset($_REQUEST['product_id']) && (int)$_REQUEST['product_id']) {
  $product= (int) $_REQUEST['product_id'];
  $props[]= "product_id = $product";
}

if (!empty($_REQUEST['name'])) {
  $name= $db->real_escape_string($_REQUEST['name']);
  $name= preg_replace('/({{(\w+?)}})/', '\', \\2, \'', $name);
  $props[]= "name = CONCAT('$name')";
}

if (!empty($_REQUEST['short_name'])) {
  $short_name= $db->real_escape_string($_REQUEST['short_name']);
  $short_name= preg_replace('/({{size}})/', '\', REPLACE(CONCAT(REPLACE(LEFT(name, LOCATE(" ", name)-1), "x", "\" × "), "\""), \'yd"\', " yds."), \'', $short_name);
  $short_name= preg_replace('/({{(\w+?)}})/', '\', \\2, \'', $short_name);
  $props[]= "short_name = CONCAT('$short_name')";
}

if (!empty($_REQUEST['variation'])) {
  $variation= $db->real_escape_string($_REQUEST['variation']);
  $props[]= "variation = '$variation'";
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

if (strlen($_REQUEST['minimum_quantity'])) {
  $minimum_quantity= (int)$_REQUEST['minimum_quantity'];
  $props[]= "minimum_quantity = $minimum_quantity";
}

if (strlen($_REQUEST['purchase_quantity'])) {
  $purchase_quantity= (int)$_REQUEST['purchase_quantity'];
  $props[]= "purchase_quantity = $purchase_quantity";
}

if (strlen($_REQUEST['dimensions'])) {
  list($l, $w, $h)= preg_split('/\s*x\s*/', $_REQUEST['dimensions']);
  $props[]= "length = " . (float)$l;
  $props[]= "width = " . (float)$w;
  $props[]= "height = " . (float)$h;
}

if (strlen($_REQUEST['weight'])) {
  $weight= (float)$_REQUEST['weight'];
  $props[]= "weight = $weight";
}

foreach (array('active','oversized','hazmat','prop65') as $key) {
  if (strlen($_REQUEST[$key])) {
    $props[]= "$key = " . (int)$_REQUEST[$key];
  }
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
