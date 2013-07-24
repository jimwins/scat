<?
require 'scat.php';
require 'lib/item.php';

head("quick sales");

$q= $_REQUEST['q'];
?>
<form method="get" action="<?=$_SERVER['PHP_SELF']?>">
<input id="autofocus" type="text" autocomplete="off" size="60" name="q" value="<?=htmlspecialchars($q)?>">
<label><input type="checkbox" value="1" name="all"<?=$_REQUEST['all']?' checked="checked"':''?>> All</label>
<input type="submit" value="Search">
</form>
<?

$options= FIND_OR;
list($sql_criteria, $begin) = item_terms_to_sql($db, $q, $options);

$begin= '2012-07-01';
$end=   '2012-11-01';

$q= "select 
            item.id AS meta,
            item.code Code\$item,
            item.name Name\$name,
              (SELECT SUM(allocated) FROM txn_line WHERE item = item.id) Stock,
            sum(-1 * allocated) Sold,
            avg(sale_price(txn_line.retail_price, txn_line.discount_type, txn_line.discount)) AvgPrice\$dollar,
            SUM(-1 * allocated * sale_price(txn_line.retail_price, txn_line.discount_type, txn_line.discount)) Total\$dollar
       FROM txn left join txn_line on txn.id = txn_line.txn left join item on txn_line.item = item.id where type = 'customer' and ($sql_criteria) and paid between '$begin' and '$end' group by 1";

dump_table($db->query($q));

dump_query($q);

foot();

