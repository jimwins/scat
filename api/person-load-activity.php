<?
include '../scat.php';
include '../lib/person.php';

$person_id= (int)$_REQUEST['person'];
$page= (int)$_REQUEST['page'];

$activity= person_load_activity($db, $person_id, $page);

if (!is_array($activity))
  die_jsonp('No such person.');

echo jsonp(array('activity' => $activity, 'activity_page' => $page));
