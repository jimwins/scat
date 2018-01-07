<?
include '../scat.php';
include '../lib/catalog.php';

$id= (int)$_REQUEST['id'];
$dest= (int)$_REQUEST['dest'];

try {
  $brand= Model::factory('Brand')->find_one($id);
  $dest= Model::factory('Brand')->find_one($dest);

  ORM::get_db()->beginTransaction();

  ORM::raw_execute("UPDATE item
                       SET brand = {$dest->id}
                    WHERE brand = {$brand->id}");
  ORM::raw_execute("UPDATE product
                       SET brand_id = {$dest->id}
                    WHERE brand_id = {$brand->id}");

  $brand->delete();

  ORM::get_db()->commit();
} catch (\PDOException $e) {
  ORM::get_db()->rollBack();
  die_jsonp($e->getMessage());
}

echo jsonp($dest->as_array());

