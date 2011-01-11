<?
require 'scat.php';

$type= $_REQUEST['type'];
$number= (int)$_REQUEST['number'];

if (!$type || !$number) die("no transaction specified.");

# xxx escaping
$q= "SELECT id FROM txn WHERE type = '$type' AND number = $number";
$r= $db->query($q);
$row= $r->fetch_row();
$txn= $row[0];

$search= $_REQUEST['search'];

$terms= preg_split('/\s+/', $search);
$criteria= array();
foreach ($terms as $term) {
  $term= $db->real_escape_string($term);
  if (preg_match('/^code:(.+)/i', $term, $dbt)) {
    $criteria[]= "(item.code LIKE '{$dbt[1]}%')";
  } else {
    $criteria[]= "(item.name LIKE '%$term%'
               OR brand.name LIKE '%$term%'
               OR item.code LIKE '%$term%'
               OR barcode.code LIKE '%$term%')";
  }
}
$criteria[]= "(active AND NOT deleted)";

$q= "SELECT
            item.id id,
            item.code Code\$item,
            item.name Name,
            brand.name Brand
       FROM item
       JOIN brand ON (item.brand = brand.id)
  LEFT JOIN barcode ON (item.id = barcode.item)
      WHERE " . join(' AND ', $criteria) . "
   GROUP BY item.id";

$r= $db->query($q) or die("failed" . $db->error . '<pre>' . $q);

if ($r->num_rows == 0) {
  # nothing
  echo json_encode(array('error' => 'No matches found.'));
  exit;
}

if ($r->num_rows > 1) {
# multiple matches, check against lines
  echo json_encode(array('error' => 'Too many matches found.'));
  exit;
}

# exact match, find line
$item= $r->fetch_assoc();

$q= "SELECT line, ordered, shipped, allocated
       FROM txn_line
      WHERE txn = $txn AND item = {$item['id']}";
$r= $db->query($q) or die("failed" . $db->error . '<pre>' . $q);

if ($r->num_rows == 0) {
  # nothing, allow product to be added
  echo json_encode(array('error' => 'No matches found.'));
  exit;
}

if ($r->num_rows > 1) {
  # too many matches, makes no sense
  echo json_encode(array('error' => 'Too many lines found.'));
  exit;
}

# found it, add to inventory, return results
$line= $r->fetch_assoc();

$q= "UPDATE txn_line SET allocated = allocated + 1 WHERE txn = $txn AND line = $line[line]";
$r= $db->query($q) or die("failed" . $db->error . '<pre>' . $q);
if ($db->affected_rows != 1) {
  echo json_encode(array('error' => 'Failed to receive item.'));
  exit;
}
$line['allocated']+= 1;

echo json_encode($line);
