<?
require '../scat.php';

$vendor_id= (int)$_REQUEST['vendor'];

if (!$vendor_id)
  die_jsonp("No vendor specified.");

$q= "UPDATE vendor_item SET promo_price = NULL, promo_quantity = NULL
      WHERE vendor = $vendor_id";

$db->query($q)
  or die_query($db, $q);

echo json_encode([ 'message' => 'Cleared promos for vendor.' ]);
