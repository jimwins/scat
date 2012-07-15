<?
include '../scat.php';
include '../lib/item.php';

$q= $_GET['q'];
if (!$q) exit;

$options= 0;
if ($_REQUEST['all']) $options+= FIND_ALL;
if ($_REQUEST['or']) $options+= FIND_OR;
if ($_REQUEST['sales']) $options+= FIND_SALES;

$items= item_find($db, $q, $options);

echo jsonp(array('items' => $items));
