<?
include '../scat.php';
include '../lib/person.php';

$person_id= (int)$_REQUEST['person'];

$activity= person_load_activity($db, $person_id);

if (!$activity)
  die_jsonp('No such person.');

echo jsonp(array('activity' => $activity));
