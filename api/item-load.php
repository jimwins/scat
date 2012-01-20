<?
include '../scat.php';
include '../lib/item.php';

$id= (int)$_REQUEST['id'];

$code= $_REQUEST['code'];

if (!$id && $code) {
  $code= $db->escape($code);
  $q= "SELECT id FROM item WHERE code = '$code'";
  $id= $db->get_one($q);
};

if (!$id)
  die_jsonp("No item specified.");

$item= item_load($db, $id);

echo jsonp(array('item' => $item));
