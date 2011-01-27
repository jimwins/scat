<?
require 'scat.php';

head("transactions");

$type= $_REQUEST['type'];
if ($type) {
  $criteria= "type = '".$db->real_escape_string($type)."'";
} else {
  $criteria= '1=1';
}

/*
$q= $_GET['q'];
?>
<form method="get" action="<?=$_SERVER['PHP_SELF']?>">
<input id="focus" type="text" name="q" value="<?=htmlspecialchars($q)?>">
<input type="submit" value="Search">
</form>
<br>
<?
*/

$q= "SELECT
            txn.type AS meta,
            CONCAT(txn.id, '|', type, '|', txn.number) AS Number\$txn,
            txn.created AS Created\$date,
            SUM(ordered) AS Ordered,
            SUM(shipped) AS Shipped,
            SUM(allocated) AS Allocated
       FROM txn
       LEFT JOIN txn_line ON (txn.id = txn_line.txn)
      WHERE $criteria
      GROUP BY txn.id
      ORDER BY created DESC
      LIMIT 200";

dump_table($db->query($q));
dump_query($q);
