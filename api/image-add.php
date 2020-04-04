<?php
require '../scat.php';

$fn= $_FILES['src']['tmp_name'];
$url= $_REQUEST['url'];

if ($fn) {
  $image= \Scat\Model\Image::createFromPath($fn);
} elseif ($url) {
  $image= \Scat\Model\Image::createFromUrl($url);
}

echo jsonp($image->as_array());
