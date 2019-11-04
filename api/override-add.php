<?
include '../scat.php';
include '../lib/txn.php';

$pattern= $_REQUEST['pattern'];
$pattern_type= $_REQUEST['pattern_type'];
$minimum_quantity= (int)$_REQUEST['minimum_quantity'];
$discount_type= $_REQUEST['discount_type'];
$discount= $_REQUEST['discount'];
$expires= $_REQUEST['expires'];
$in_stock= (int)$_REQUEST['in_stock'];
if (empty($expires)) $expires= null;

if (!$pattern)
  die_jsonp('Must specify a pattern.');
if (!$minimum_quantity)
  die_jsonp('Must specify a minimum quantity.');

try {
  $override= Model::factory('PriceOverride')->create();
  $override->pattern= $pattern;
  $override->pattern_type= $pattern_type;
  $override->minimum_quantity= $minimum_quantity;
  $override->discount_type= $discount_type;
  $override->discount= $discount;
  $override->expires= $expires;
  $override->in_stock= $in_stock;
  $override->save();
} catch (\PDOException $e) {
  die_jsonp($e->getMessage());
}

echo jsonp($override->as_array());
