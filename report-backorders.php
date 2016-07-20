<?
require 'scat.php';
require 'lib/item.php';

head("Backorders @ Scat", true);

$q= "SELECT txn.id AS txn, created,
            IF(type = 'vendor' && YEAR(created) > 2013,
               CONCAT(SUBSTRING(YEAR(created), 3, 2), number),
               CONCAT(DATE_FORMAT(created, '%Y-'), number))
              AS formatted_number,
            person.id AS person, person.company AS person_name,
            item.id AS item, item.code, item.name AS item_name,
            ordered, allocated
       FROM txn
       JOIN txn_line ON txn.id = txn_line.txn
       JOIN person ON txn.person = person.id
       JOIN item ON txn_line.item = item.id
      WHERE type = 'vendor'
        AND ordered != allocated
      ORDER BY txn.id DESC, code";

$r= $db->query($q) or die('Line : ' . __LINE__ . $db->error);

$txn= 0;
?>
<table class="table table-striped">
 <thead>
  <tr>
   <th></th>
   <th>Code</th>
   <th>Name</th>
   <th>Ordered</th>
   <th>Received</th>
  </tr>
 </thead>
 <tbody>
<?
while ($row= $r->fetch_assoc()) {
  if ($row['txn'] != $txn) {
    $txn= $row['txn'];
?>
  <tr class="active">
    <td colspan="5">
      <a href="txn.php?id=<?=$row['txn']?>"><?=$row['formatted_number']?></a>
  /    <?=$row['created']?>
  /    <a href="person.php?id=<?=$row['person']?>"><?=ashtml($row['person_name'])?></a>
    </td>
  </tr>
<?}?>
  <tr>
   <td> &nbsp; </td>
   <td><a href="item.php?id=<?=$row['item']?>"><?=ashtml($row['code'])?></td>
   <td><?=ashtml($row['item_name'])?></td>
   <td><?=ashtml($row['ordered'])?></td>
   <td><?=ashtml($row['allocated'])?></td>
  </tr>
<?}?>
 </tbody>
</table>

<?
foot();
?>
