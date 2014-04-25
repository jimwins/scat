<?
include '../scat.php';

$id= 0;

$code= $_REQUEST['code'];

if ($code) {
  $code= $db->escape($code);
  $q= "SELECT id FROM vendor_item WHERE code = '$code'";
  $id= $db->get_one($q);
}

if (!$id)
  die_jsonp("No item specified.");

$item= $db->get_one_assoc("SELECT * FROM vendor_item WHERE id = $id");

echo jsonp(array('item' => $item));
