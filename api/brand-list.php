<?php
require '../scat.php';

$verbose= (int)$_REQUEST['verbose'];

$q= "SELECT id, name FROM brand WHERE name != '' ORDER BY name";
$r= $db->query($q)
  or die_query($db, $q);

$brands= array();
while ($row= $r->fetch_row()) {
  if ($verbose) {
    $brands[]= array('id' => $row[0], 'name' => $row[1]);
  } else {
    $brands[$row[0]]= $row[1];
  }
}

if ($_REQUEST['id'])
  $brands['selected']= $_REQUEST['id'];

echo jsonp($brands);
