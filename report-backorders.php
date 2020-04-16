<?
require 'scat.php';
require 'lib/item.php';

head("Backorders @ Scat", true);

$q= "SELECT txn.id AS txn, txn.created,
            IF(type = 'vendor' && YEAR(txn.created) > 2013,
               CONCAT(SUBSTRING(YEAR(txn.created), 3, 2), number),
               CONCAT(DATE_FORMAT(txn.created, '%Y-'), number))
              AS formatted_number,
            person.id AS person, person.company AS person_name,
            item.id AS item, item.code,
            IFNULL(override_name, item.name) AS item_name, data,
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
<style type="text/css">
table.collapse.in {
   display: table;
}
</style>
<div class="panel-group" id="accordion" role="tablist" aria-multiselectable="true">
<?
while ($row= $r->fetch_assoc()) {
  if ($row['txn'] != $txn) {
    if ($txn) {?>
      </tbody>
    </table>
  </div>
<?}
    $txn= $row['txn'];
?>
  <div class="panel panel-default">
    <div class="panel-heading" role="tab" id="heading<?=$row['txn']?>">
      <h4 class="panel-title">
        <a role="button" data-toggle="collapse" data-parent="#accordion" href="#collapse<?=$row['txn']?>" aria-expanded="true" aria-controls="collapse<?=$row['txn']?>">
	  PO <?=$row['formatted_number']?>
          / <?=$row['created']?>
          / <?=ashtml($row['person_name'])?>
        </a>
      </h4>
    </div>
    <table id="collapse<?=$row['txn']?>" class="table table-striped collapse" role="tabpanel" aria-labelledby="heading<?=$row['txn']?>">
      <caption>
        <a class="btn btn-default" href="./?id=<?=$row['txn']?>">Invoice</a>
      </caption>
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
<?}?>
        <tr>
          <td> &nbsp; </td>
	  <td><a href="/catalog/item/<?=$row['code']?>"><?=ashtml($row['code'])?></td>
	  <td><?=ashtml($row['item_name'])?></td>
	  <td><?=ashtml($row['ordered'])?></td>
	  <td><?=ashtml($row['allocated'])?></td>
        </tr>
<?}?>
      </tbody>
    </table>
  </div>
</div>

<?
foot();
?>
