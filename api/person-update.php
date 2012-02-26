<?
include '../scat.php';
include '../lib/person.php';

$person_id= (int)$_REQUEST['person'];

$person= person_load($db, $person_id);

if (!$person)
  die_jsonp('No such person.');

foreach (array('name', 'company', 'email',
               'phone', 'tax_id', 'address') as $key) {
  if (isset($_REQUEST[$key])) {
    $value= $db->real_escape_string($_REQUEST[$key]);
    $q= "UPDATE person SET $key = '$value' WHERE id = $person_id";

    $r= $db->query($q)
      or die_query($db, $q);
  }
}

if (isset($_REQUEST['active'])) {
  $active= (int)$_REQUEST['active'];
  $q= "UPDATE person
          SET active = $active
        WHERE id = $person_id";

  $r= $db->query($q)
    or die_query($db, $q);
}

$person= person_load($db, $person_id);

echo jsonp(array('person' => $person));
