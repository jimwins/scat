<?
include '../scat.php';
include '../lib/person.php';

$id= (int)$_REQUEST['person'];

try {
  $person= Model::factory('Person')->find_one($id);
  $loyalty= Model::factory('Loyalty')->create();
  $loyalty->person_id= $person->id;
  $loyalty->points= (int)$_REQUEST['points'];
  $loyalty->note= $_REQUEST['note'];
  $loyalty->save();

  # XXX force loyalty points to recalculate
} catch (\PDOException $e) {
  die_jsonp($e->getMessage());
}

echo jsonp($person->as_array());
