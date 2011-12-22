<?
require 'scat.php';

head("person");

$id= (int)$_REQUEST['id'];
$search= $_REQUEST['search'];

?>
<form method="get" action="person.php">
<input id="focus" type="text" name="search" value="<?=ashtml($search)?>">
<input type="submit" value="Find People">
</form>
<br>
<?

if (!empty($search)) {
  $search= $db->real_escape_string($search);

  $q= "SELECT IF(deleted, 'deleted', '') AS meta,
              CONCAT(id, '|', IFNULL(company,''),
                     '|', IFNULL(name,''))
                AS Person\$person
         FROM person
        WHERE name like '%$search%' OR company LIKE '%$search%'
        ORDER BY company, name";

  $r= $db->query($q)
    or die($db->error);

  if ($r->num_rows > 1) {
    dump_table($r);
  } else {
    $person= $r->fetch_assoc();
    $id= (int)$person['Person$person'];
  }
}

if (!$id) {
  foot();
  exit;
}

$q= "SELECT name,
            company,
            address,
            email,
            phone,
            tax_id,
            active,
            deleted
       FROM person
      WHERE id = $id";

$r= $db->query($q)
  or die($db->error);
$person= $r->fetch_assoc();

?>
<style>
  .person th { text-align: right; vertical-align: top; color: #777; }
  .person td { white-space: pre-wrap; }
  .deleted { text-decoration: line-through; }
</style>
<table class="person">
  <tr class="<?=($person['deleted'] ? 'deleted' : '');?>">
   <th>Name:</th>
   <td><?=htmlspecialchars($person['name']);?></td>
  </tr>
  <tr>
   <th>Company:</th>
   <td><?=htmlspecialchars($person['company']);?></td>
  </tr>
  <tr>
   <th>Email:</th>
   <td><?=htmlspecialchars($person['email']);?></td>
  </tr>
  <tr>
   <th>Phone:</th>
   <td><?=htmlspecialchars($person['phone']);?></td>
  </tr>
  <tr>
   <th>Address:</th>
   <td><?=htmlspecialchars($person['address']);?></td>
  </tr>
  <tr>
   <th>Tax ID:</th>
   <td><?=htmlspecialchars($person['tax_id']);?></td>
  </tr>
</table>

<h2>Activity</h2>
<?
$q= "SELECT meta, Number\$txn, Created\$date,
            Ordered, Shipped, Allocated,
            CAST(ROUND_TO_EVEN(taxed * (1 + tax_rate / 100), 2) + untaxed
                 AS DECIMAL(9,2))
            Total\$dollar,
            Paid\$dollar
      FROM (SELECT
            txn.type AS meta,
            CONCAT(txn.id, '|', type, '|', txn.number) AS Number\$txn,
            txn.created AS Created\$date,
            CONCAT(txn.person, '|', IFNULL(person.company,''),
                   '|', IFNULL(person.name,''))
              AS Person\$person,
            SUM(ordered) AS Ordered,
            SUM(shipped) AS Shipped,
            SUM(allocated) AS Allocated,
            CAST(ROUND_TO_EVEN(
              SUM(IF(txn_line.taxfree, 1, 0) *
                IF(type = 'customer', -1, 1) * allocated *
                CASE discount_type
                  WHEN 'percentage' THEN retail_price * ((100 - discount) / 100)
                  WHEN 'relative' THEN (retail_price - discount) 
                  WHEN 'fixed' THEN (discount)
                  ELSE retail_price
                END),
              2) AS DECIMAL(9,2))
            untaxed,
            CAST(ROUND_TO_EVEN(
              SUM(IF(txn_line.taxfree, 0, 1) *
                IF(type = 'customer', -1, 1) * allocated *
                CASE discount_type
                  WHEN 'percentage' THEN retail_price * ((100 - discount) / 100)
                  WHEN 'relative' THEN (retail_price - discount) 
                  WHEN 'fixed' THEN (discount)
                  ELSE retail_price
                END),
              2) AS DECIMAL(9,2))
            taxed,
            tax_rate,
            CAST((SELECT SUM(amount) FROM payment WHERE txn.id = payment.txn)
                 AS DECIMAL(9,2)) AS Paid\$dollar
       FROM txn
       LEFT JOIN txn_line ON (txn.id = txn_line.txn)
       LEFT JOIN person ON (txn.person = person.id)
      WHERE person = $id
      GROUP BY txn.id
      ORDER BY created DESC
      LIMIT 50) t";

dump_table($db->query($q));

foot();
