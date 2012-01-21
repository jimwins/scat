<?
include '../scat.php';
include '../lib/item.php';

$item_id= (int)$_REQUEST['item'];

if (isset($_REQUEST['name'])) {
  $name= $db->real_escape_string($_REQUEST['name']);
  $q= "UPDATE item
          SET name = '$name'
        WHERE id = $item_id";

  $r= $db->query($q)
    or die_query($db, $q);
}

if (isset($_REQUEST['brand']) && (int)$_REQUEST['brand']) {
  $brand= (int) $_REQUEST['brand'];
  $q= "UPDATE item
          SET brand = $brand
        WHERE id = $item_id";

  $r= $db->query($q)
    or die_query($db, $q);
}

if (isset($_REQUEST['discount'])) {
  $discount= $_REQUEST['discount'];
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

$item= item_load($db, $item_id);

echo jsonp(array('item' => $item));
