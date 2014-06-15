<?
include '../scat.php';

$name= $_REQUEST['name'];
$company= $_REQUEST['company'];
if (empty($name) && empty($company))
  die_jsonp("You need to supply at least a name or company.");

if ($name) {
  $q= "SELECT id
         FROM person
        WHERE name = '" . $db->escape($name) . "'";
  $r= $db->query($q)
    or die_query($db, $q);

  if ($r->num_rows)
    die_jsonp("Someone by that name already exists.");
}

$list= array();
foreach(array('name', 'company', 'address',
              'email', 'phone', 'tax_id') as $field) {
  $list[]= "$field = '" . $db->real_escape_string($_REQUEST[$field]) . "', ";
}

$fields= join('', $list);

// add payment record
$q= "INSERT INTO person
        SET $fields
        active = 1";
$r= $db->query($q)
  or die_query($db, $q);

echo jsonp(array('person' => $db->insert_id));
