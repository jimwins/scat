<?
include '../scat.php';
include '../lib/person.php';

$term= $_REQUEST['term'];
$limit= (int)$_REQUEST['limit'];

if (($type= $_REQUEST['role'])) {
  $term.= " role:$type";
}


$people= person_find($db, $term, null, $limit);

echo jsonp($people);
