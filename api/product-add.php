<?
include '../scat.php';
include '../lib/catalog.php';

$name= $_REQUEST['name'];
$slug= $_REQUEST['slug'];

try {
  $product= Model::factory('Product')->create();

  foreach ($_REQUEST as $k => $v) {
    if (in_array($k, array('department_id', 'brand_id', 'name', 'description',
                           'slug', 'image', 'variation_style', 'active')))
    {
      $product->set($k, $v);
    }
  }

  $product->save();

} catch (\PDOException $e) {
  die_jsonp($e->getMessage());
}

echo jsonp($product);

