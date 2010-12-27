<?
require 'scat.php';

head("transactions");

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
            CONCAT(type, '|', txn.number) AS Number\$txn,
            txn.created AS Created\$date,
            SUM(ordered) AS Ordered,
            SUM(allocated) AS Allocated
       FROM txn
       LEFT JOIN txn_line ON (txn.id = txn_line.txn)
      GROUP BY txn.id
      ORDER BY created DESC";

dump_table($db->query($q));
dump_query($q);
