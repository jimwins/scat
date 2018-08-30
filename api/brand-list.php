<?php
require '../scat.php';

$verbose= (int)$_REQUEST['verbose'];

$brands= Model::factory('Brand')
           ->where_not_equal('name', '')
           ->order_by_asc('name')
           ->find_array();

$data= array();

if ($verbose) {
  $data= $brands;
} else {
  foreach ($brands as $row) {
    // Workaround for jEditable sorting: prefix id with _
    $data['_'.$row['id']]= $row['name'];
  }
}

if ($_REQUEST['id'])
  $data['selected']= $_REQUEST['id'];

echo jsonp($data);
