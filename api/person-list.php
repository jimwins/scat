<?
include '../scat.php';

$criteria= array();

$term= $_REQUEST['term'];
$terms= preg_split('/\s+/', $term);

foreach ($terms as $term) {
  $term= $db->real_escape_string($term);
  $criteria[]= "(person.name LIKE '%$term%'
             OR person.company LIKE '%$term%')";
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
                AS value 
       FROM person
      WHERE $criteria
      ORDER BY value";

$r= $db->query($q)
  or die_query($db, $q);

$list= array();
while ($row= $r->fetch_assoc()) {
  /* force numeric values to numeric type */
  $list[]= $row;
}

echo jsonp($list);
