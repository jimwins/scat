<?
include '../scat.php';

$q= "SELECT id, name,
            (SELECT start
               FROM timeclock
              WHERE person = person.id
                AND end IS NULL
              ORDER BY id DESC
              LIMIT 1) AS punched
       FROM person
      WHERE person.active AND role = 'employee'
      ORDER BY name";

$r= $db->query($q);

$people= array();
while ($person= $r->fetch_assoc()) {
  $people[]= $person;
}

echo jsonp(array("people" => $people));

