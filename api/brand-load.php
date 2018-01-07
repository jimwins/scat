<?php
require '../scat.php';
require '../lib/catalog.php';

$id= (int)$_REQUEST['id'];

if (!$id) {
  die_jsonp("No id requested.");
}

$brand= Model::factory('Brand')->find_one($id);

if (!$brand)
  die(json_encode(array('error' => 'No such brand!')));

echo jsonp($brand->as_array());

