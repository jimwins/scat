<?
include '../scat.php';
include '../lib/catalog.php';

$id= (int)$_REQUEST['id'];
$name= $_REQUEST['name'];
$slug= $_REQUEST['slug'];

try {
  $brand= Model::factory('Brand')->find_one($id);
  if ($name)
    $brand->name= $name;
  if ($slug)
    $brand->slug= $slug;
  $brand->save();
} catch (\PDOException $e) {
  die_jsonp($e->getMessage());
}

echo jsonp($brand->as_array());
