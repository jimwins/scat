<?php
require '../scat.php';

$fn= $_FILES['src']['tmp_name'];
$url= $_REQUEST['url'];

if ($fn) {
  $image= \Scat\Image::createFromPath($fn);
} elseif ($url) {
  $image= \Scat\Image::createFromUrl($url);
}

echo jsonp($image->as_array());
