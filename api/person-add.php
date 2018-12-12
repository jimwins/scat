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
  $list[]= "$field = '" . $db->escape($_REQUEST[$field]) . "', ";
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
