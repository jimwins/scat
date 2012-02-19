<?
include '../scat.php';
include '../lib/item.php';

$code= $_REQUEST['code'];
$name= $_REQUEST['name'];
$msrp= $_REQUEST['retail_price'];

if (!$code)
  die_jsonp('Must specify a code.');
if (!$name)
  die_jsonp('Must specify a name.');
if (!$msrp)
  die_jsonp('Must specify a price.');

$code= $db->escape($code);
$name= $db->escape($name);
$msrp= $db->escape($msrp);

$q= "INSERT INTO item
        SET code = '$code', name = '$name', retail_price = '$msrp',
            taxfree = 0, minimum_quantity = 1, active = 1";

$r= $db->query($q)
  or die_query($db, $q);

$item_id= $db->insert_id;

$item= item_load($db, $item_id);

echo jsonp(array('item' => $item));
