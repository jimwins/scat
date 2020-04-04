<?
include '../scat.php';

$product_id= (int)$_REQUEST['product_id'];
$image_id= (int)$_REQUEST['image_id'];

$q= "DELETE FROM product_to_image
           WHERE product_id = $product_id
             AND image_id = $image_id";

$db->query($q)
  or die_query($db, $q);

$product= \Scat\Model\Product::getById($product_id);

echo jsonp($product);
