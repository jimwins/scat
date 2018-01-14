<?
include '../scat.php';
include '../lib/catalog.php';

$id= (int)$_REQUEST['id'];
$name= $_REQUEST['name'];
$slug= $_REQUEST['slug'];

try {
  $product= Model::factory('Product')->find_one($id);
  if ($name)
    $product->name= $name;
  if ($slug)
    $product->slug= $slug;
  if (array_key_exists('active', $_REQUEST))
    $product->active= (int)$_REQUEST['active'];
  $product->save();
} catch (\PDOException $e) {
  die_jsonp($e->getMessage());
}

echo jsonp($product->as_array());
