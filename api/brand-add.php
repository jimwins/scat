<?
include '../scat.php';
include '../lib/item.php';

$name= $_REQUEST['name'];
$slug= $_REQUEST['slug'];

if (!$name)
  die_jsonp('Must specify a name.');
if (!$slug)
  die_jsonp('Must specify a slug.');

$name= $db->escape($name);
$slug= $db->escape($slug);

$q= "INSERT INTO brand
        SET name = '$name', slug = '$slug'";

$r= $db->query($q)
  or die_query($db, $q);

$brand_id= $db->insert_id;

echo jsonp(array('brand' => $brand_id));
