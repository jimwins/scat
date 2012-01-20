<?php
require '../scat.php';

$q= "SELECT id, name FROM brand WHERE name != '' ORDER BY name";
$r= $db->query($q)
  or die_query($db, $q);

$brands= array();
while ($row= $r->fetch_row()) {
  $brands[$row[0]]= $row[1];
}
$brands['selected']= $_REQUEST['id'];

echo jsonp($brands);
