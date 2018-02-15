<?
include '../scat.php';
include '../lib/txn.php';

$id= (int)$_REQUEST['id'];

try {
  $override= Model::factory('PriceOverride')->find_one($id);
  $override->delete();
} catch (\PDOException $e) {
  die_jsonp($e->getMessage());
}

echo jsonp(array());
