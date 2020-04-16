<?
include '../scat.php';
include '../lib/person.php';

$criteria= array();

$type= $_REQUEST['type'];
if ($type) {
  $criteria[]= "(type = '".$db->real_escape_string($type)."')";
}

$q= $_REQUEST['q'];
if ($q) {
  $criteria[]= "(person.name LIKE '%$q%'
             OR person.company LIKE '%$q%')";
}
if ($_REQUEST['unfilled']) {
  $criteria[]= "txn.filled IS NULL";
}
if ($_REQUEST['unpaid']) {
  $criteria[]= "txn.paid IS NULL";
}
if ($_REQUEST['active']) {
  $criteria[]= "txn.paid IS NULL";
}

if (empty($criteria)) {
  $criteria= '1=1';
} else {
  $criteria= join(' AND ', $criteria);
}

$page= (int)$_REQUEST['page'];

$per_page= (int)$_REQUEST['limit'];
if (!$per_page) $per_page= 50;
$start= $page * $per_page;

$q= "SELECT id, type,
            number, created, filled, paid,
            CONCAT(DATE_FORMAT(created, '%Y-'), number) AS formatted_number,
            person, person_name, loyalty_number,
            IFNULL(ordered, 0) ordered, allocated,
            taxed, untaxed, tax_rate,
            CAST(ROUND_TO_EVEN(taxed * (1 + tax_rate / 100), 2) + untaxed
                 AS DECIMAL(9,2)) total,
            total_paid
      FROM (SELECT
            txn.id, txn.type, txn.number,
            txn.created, txn.filled, txn.paid,
            txn.person,
            CONCAT(IFNULL(person.name, ''),
                   IF(person.name != '' AND person.company != '', ' / ', ''),
                   IFNULL(person.company, ''))
                AS person_name,
            loyalty_number,
            SUM(ordered) * IF(txn.type = 'customer', -1, 1) AS ordered,
            SUM(allocated) * IF(txn.type = 'customer', -1, 1) AS allocated,
            CAST(ROUND_TO_EVEN(
              SUM(IF(txn_line.taxfree, 1, 0) *
                IF(type = 'customer', -1, 1) * allocated *
                sale_price(retail_price, discount_type, discount)),
              2) AS DECIMAL(9,2))
            untaxed,
            CAST(ROUND_TO_EVEN(
              SUM(IF(txn_line.taxfree, 0, 1) *
                IF(type = 'customer', -1, 1) * allocated *
                sale_price(retail_price, discount_type, discount)),
              2) AS DECIMAL(9,2))
            taxed,
            tax_rate,
            CAST((SELECT SUM(amount) FROM payment WHERE txn.id = payment.txn_id)
                 AS DECIMAL(9,2)) AS total_paid
       FROM txn
       LEFT JOIN txn_line ON (txn.id = txn_line.txn)
       LEFT JOIN person ON (txn.person = person.id)
      WHERE $criteria
      GROUP BY txn.id
      ORDER BY id DESC
      LIMIT $start, $per_page) t";

$r= $db->query($q)
  or die_query($db, $q);

$txn= array();
while ($row= $r->fetch_assoc()) {
  /* force numeric values to numeric type */
  $row['total']= (float)$row['total'];
  $row['total_paid']= (float)$row['total_paid'];
  if (!$row['person_name']) {
    $row['person_name']= format_phone($row['loyalty_number']);
  }
  $txn[]= $row;
}

echo jsonp($txn);
