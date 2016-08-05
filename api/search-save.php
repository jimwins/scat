<?
include '../scat.php';

$id= (int)$_REQUEST['id'];
$name= $_REQUEST['name'];
$search= $_REQUEST['search'];

if ($id) {
  $q= "UPDATE saved_search
          SET search = '" . $db->escape($search) . "'
        WHERE id = $id";
} else {
  $q= "INSERT INTO saved_search
          SET name = '" . $db->escape($name) . "',
              search = '" . $db->escape($search) . "'";
}

$r= $db->query($q)
  or die_query($db, $q);

if (!$id) {
  $id= $db->insert_id;
}

$res= $db->get_one_assoc("SELECT * FROM saved_search WHERE id = $id");

echo jsonp(array('search' => $res));
