<?
include '../scat.php';

$id= (int)$_REQUEST['id'];
$name= $_REQUEST['name'];
$slug= $_REQUEST['slug'];

try {
  $product= Model::factory('Product')->find_one($id);

  $old_slug= "";
  if (($product->slug && $_REQUEST['slug'] != $product->slug) ||
      ($product->department_id &&
       $_REQUEST['department_id'] != $product->department_id))
  {
    // XXX handle rename
    $old_slug= '';
  }

  foreach ($_REQUEST as $k => $v) {
    if (in_array($k, array('department_id', 'brand_id', 'name', 'description',
                           'slug', 'image', 'variation_style', 'active')))
    {
      $product->set($k, $v);
    }
  }

  $product->save();

  // XXX Save redirect information
  if ($old_slug) {
  }

} catch (\PDOException $e) {
  die_jsonp($e->getMessage());
}

echo jsonp($product);
