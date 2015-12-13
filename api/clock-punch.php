<?
include '../scat.php';

$id= (int)$_REQUEST['id'];
$punched= $_REQUEST['punched'];

$action= '';

if (!$id)
  die_jsonp("No person specified.");

if ($punched) {
  $q= "SELECT start
         FROM timeclock
        WHERE person = $id AND end IS NULL";
  $ok= $db->get_one($q);

  if (!$ok)
    die_jsonp("This person is not clocked in.");

  $q= "UPDATE timeclock
          SET end = NOW()
        WHERE person = $id AND end IS NULL";
  $db->query($q);

  $action= "out";

} else {

  $q= "SELECT start
         FROM timeclock
        WHERE person = ? AND end IS NULL";
  $ok= $db->get_one($q);

  if ($ok)
    die_jsonp("This person is already clocked in.");

  $q= "INSERT INTO timeclock
          SET person = $id, start = NOW()";
  $db->query($q);

  $action= "in";

}

$q= "SELECT id, name,
            (SELECT start
               FROM timeclock
              WHERE person = person.id
                AND end IS NULL
              ORDER BY id DESC
              LIMIT 1) AS punched
       FROM person
      WHERE id = $id";
$person= $db->get_one_assoc($q);

echo jsonp(array("action" => $action,
                 "person" => $person));
