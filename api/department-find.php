<?php
require '../scat.php';
require '../lib/catalog.php';

$parent= (int)$_REQUEST['parent'];

$departments= Model::factory('Department')
                ->where('parent_id', $parent)
                ->order_by_asc('name')
                ->find_many();

if (!$departments)
  die_jsonp(array('error' => 'No departments in that department!'));

echo jsonp(array_map(function ($d) { return $d->as_array(); }, $departments));
