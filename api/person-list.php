<?
include '../scat.php';
include '../lib/person.php';

$criteria= array();

$term= $_REQUEST['term'];
$terms= preg_split('/\s+/', $term);

foreach ($terms as $term) {
  $term= $db->real_escape_string($term);
  $criteria[]= "(person.name LIKE '%$term%'
             OR person.company LIKE '%$term%'
             OR person.email LIKE '%$term%'
             OR person.loyalty_number LIKE '%$term%'
             OR person.phone LIKE '%$term%')";
}

if (!$_REQUEST['all'])
  $criteria[]= 'active';

if (($type= $_REQUEST['role'])) {
  $criteria[]= "person.role = '" . $db->escape($type) . "'";
}

if (empty($criteria)) {
  $criteria= '1=1';
} else {
  $criteria= join(' AND ', $criteria);
}

$q= "SELECT id,
            CONCAT(IFNULL(name, ''),
                   IF(name != '' AND company != '', ' / ', ''),
                   IFNULL(company, ''))
                AS value,
            loyalty_number
       FROM person
      WHERE $criteria
      ORDER BY value";

$r= $db->query($q)
  or die_query($db, $q);

$list= array();
while ($row= $r->fetch_assoc()) {
  if (!$row['value']) $row['value']= format_phone($row['loyalty_number']);
  $list[]= $row;
}

echo jsonp($list);
