<?php
require '../scat.php';

use Intervention\Image\ImageManagerStatic as Image;

$fn= $_FILES['src']['tmp_name'];
$url= $_REQUEST['url'];

if ($fn) {
  $file= \GuzzleHttp\PSR7\stream_for(fopen($fn, "r"));
  $name= basename($_FILES['src']['name']);
} elseif ($url) {
  $client= new \GuzzleHttp\Client();
  $res= $client->get($url);
  $file= $res->getBody();
  $name= basename($url);
}

if (!$file)
  die_jsonp("No image uploaded");

$ext= pathinfo($name, PATHINFO_EXTENSION);

// Make sure we can read it as an image
$img= Image::make($file);

// Could use real UUID() but this is shorter. Hardcoded '1' could be
// replaced with a server-id to further avoid collisions
$uuid= sprintf("%08x%02x%s", time(), 1, bin2hex(random_bytes(8)));

// Upload the original
$b2= new \ChrisWhite\B2\Client(B2_ACCOUNT_ID, B2_APPLICATION_KEY);

$upload= $b2->upload([
  'BucketName' => B2_BUCKET,
  'FileName' => '/i/o/' . $uuid . '.' . $ext,
  'Body' => $file
]);

// Save the details
$image= Model::factory('Image')->create();
$image->uuid= $uuid;
$image->width= $img->width();
$image->height= $img->height();
$image->ext= $ext;
$image->name= $_REQUEST['name'] ?: $name;
$image->alt_text= $_REQUEST['alt_text'];
$image->save();

// Generate the standard size image (max 750px)
$img->resize(750, null, function ($constraint) {
    $constraint->aspectRatio();
    $constraint->upsize();
});

$upload= $b2->upload([
  'BucketName' => B2_BUCKET,
  'FileName' => '/i/st/' . $uuid . '.jpg',
  'Body' => $img->stream('jpg', 75)
]);

// Generate the thumbnail (max 125px)
$img->resize(125, null, function ($constraint) {
    $constraint->aspectRatio();
    $constraint->upsize();
});

$upload= $b2->upload([
  'BucketName' => B2_BUCKET,
  'FileName' => '/i/th/' . $uuid . '.jpg',
  'Body' => $img->stream('jpg', 75)
]);

echo jsonp($image->as_array());
