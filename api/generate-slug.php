<?php
require '../scat.php';
require '../lib/catalog.php';

$brand_id= (int)$_REQUEST['brand'];
$name= $db->escape($_REQUEST['name']);

$slug= $db->get_one("SELECT SLUG('$name') AS slug");

if ($brand_id) {
  $brand_slug= $db->get_one("SELECT slug FROM brand WHERE id = $brand_id");

  $slug= "$brand_slug-$slug";
}

echo jsonp(array('slug' => $slug));
