<?
include '../scat.php';

$name= $_REQUEST['name'];
$company= $_REQUEST['company'];
$phone= $_REQUEST['phone'];
if (empty($name) && empty($company) && empty($phone))
  die_jsonp("You need to supply at least a name, company, or phone number.");

$list= array();
foreach(array('name', 'role', 'company', 'address',
              'email', 'phone', 'tax_id') as $field) {
  $value= trim($_REQUEST[$field]);
  /* Turn empty strings into NULL, escape others and wrap in quotes */
  $value= ($value != '') ?  "'" . $db->escape($value) . "'" : 'NULL';
  $list[]= "$field = " . $value . ", ";
}

if ($_REQUEST['phone']) {
  $list[]= "loyalty_number = '" .
           preg_replace('/[^\d]/', '', $_REQUEST['phone']) .
           "', ";
}

$fields= join('', $list);

$q= "INSERT INTO person
        SET $fields
        active = 1";
$r= $db->query($q)
  or die_query($db, $q);

echo jsonp(array('person' => $db->insert_id));
