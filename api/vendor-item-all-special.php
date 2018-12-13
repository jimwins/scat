<?
require '../scat.php';

$vendor_id= (int)$_REQUEST['vendor'];

if (!$vendor_id)
  die_jsonp("No vendor specified.");

$q= "UPDATE vendor_item SET special_order = 1
      WHERE vendor = $vendor_id";

$db->query($q)
  or die_query($db, $q);

echo json_encode([ 'message' => 'Set all items as special order.' ]);
