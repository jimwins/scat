<?
include '../scat.php';

$product_id= (int)$_REQUEST['product_id'];
$image_id= (int)$_REQUEST['image_id'];

$q= "INSERT INTO product_to_image
             SET product_id = $product_id,
                 image_id = $image_id";

$db->query($q)
  or die_query($db, $q);

$product= \Scat\Product::getById($product_id);

echo jsonp($product);
