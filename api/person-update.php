<?
include '../scat.php';
include '../lib/person.php';

$person_id= (int)$_REQUEST['id'];

$person= person_load($db, $person_id);

if (!$person)
  die_jsonp('No such person.');

if (isset($_REQUEST['phone'])) {
  $_REQUEST['loyalty_number']= preg_replace('/[^\d]/', '', $_REQUEST['phone']);
}

// TODO: reset sms_ok and email_ok when email or phone changed

foreach (array('name', 'role', 'company', 'email', 'notes',
               'phone', 'loyalty_number', 'tax_id', 'address') as $key) {
  if (isset($_REQUEST[$key])) {
    $value= trim($_REQUEST[$key]);
    /* Turn empty strings into NULL, escape others and wrap in quotes */
    $value= ($value != '') ?  "'" . $db->escape($value) . "'" : 'NULL';
    $q= "UPDATE person SET $key = $value WHERE id = $person_id";

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
