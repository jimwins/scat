<?
include '../scat.php';
include '../lib/person.php';

$term= $_REQUEST['term'];

if (($type= $_REQUEST['role'])) {
  $term.= " role:$type";
}


$people= person_find($db, $term);

echo jsonp($people);
