<?php
require '../scat.php';
require '../lib/catalog.php';

$attach_id= (int)$_REQUEST['attach_id'];
if ($attach_id) {
  $extra_conditions= 'AND attach_id = ' . $attach_id;
}

$parent_id= (int)$_REQUEST['parent_id'];

$todo= (int)$_REQUEST['todo'];

$q= "SELECT COUNT(*)
       FROM note
      WHERE parent_id = $parent_id
        AND IF($todo, todo, 1)
        $extra_conditions";

echo jsonp(array('notes' => $db->get_one($q)));
