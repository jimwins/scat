<?php
require '../scat.php';
require '../lib/txn.php';

$all= (int)$_REQUEST['all'];

$overrides= Model::factory('PriceOverride')
           ->order_by_asc('pattern')
           ->order_by_asc('minimum_quantity')
           ->find_array();

echo jsonp($overrides);
