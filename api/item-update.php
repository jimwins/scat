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

$item= item_load($db, $item_id);

echo jsonp(array('item' => $item));
