<?
include '../scat.php';
include '../lib/txn.php';

$id= (int)$_REQUEST['id'];
$pattern= $_REQUEST['pattern'];
$pattern_type= $_REQUEST['pattern_type'];
$minimum_quantity= (int)$_REQUEST['minimum_quantity'];
$discount_type= $_REQUEST['discount_type'];
$discount= $_REQUEST['discount'];
$expires= $_REQUEST['expires'];
if (empty($expires)) $expires= null;

try {
  $override= Model::factory('PriceOverride')->find_one($id);
  $override->pattern= $pattern;
  $override->pattern_type= $pattern_type;
  $override->minimum_quantity= $minimum_quantity;
  $override->discount_type= $discount_type;
  $override->discount= $discount;
  $override->expires= $expires;
  $override->save();
} catch (\PDOException $e) {
  die_jsonp($e->getMessage());
}

echo jsonp($override->as_array());
