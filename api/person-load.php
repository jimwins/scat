<?
include '../scat.php';
include '../lib/person.php';

$person_id= (int)$_REQUEST['person'];

$person= person_load($db, $person_id);

if (!$person)
  die_jsonp('No such person.');

echo jsonp(array('person' => $person));
