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

if (strlen($_REQUEST['minimum_quantity'])) {
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
