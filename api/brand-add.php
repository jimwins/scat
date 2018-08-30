<?
include '../scat.php';

$name= $_REQUEST['name'];
$slug= $_REQUEST['slug'];

if (!$name)
  die_jsonp('Must specify a name.');
if (!$slug)
  die_jsonp('Must specify a slug.');

try {
  $brand= Model::factory('Brand')->create();
  $brand->name= $name;
  $brand->slug= $slug;
  $brand->save();
} catch (\PDOException $e) {
  die_jsonp($e->getMessage());
}

echo jsonp($brand->as_array());
