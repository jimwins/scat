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
    $old_slug= $product->full_slug();
  }

  foreach ($_REQUEST as $k => $v) {
    if (in_array($k, array('department_id', 'brand_id', 'name', 'description',
                           'slug', 'image', 'variation_style', 'active')))
    {
      $product->set($k, $v);
    }
  }

  $product->save();

  // Save redirect information
  if ($old_slug) {
    $new_slug= $product->full_slug();
    error_log("Redirecting $old_slug to $new_slug");
    $redir= Model::factory('Redirect')->create();
    $redir->source= $old_slug;
    $redir->dest= $new_slug;
    $redir->save();
  }

} catch (\PDOException $e) {
  die_jsonp($e->getMessage());
}

echo jsonp($product);
