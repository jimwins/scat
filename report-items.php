<?
require 'scat.php';
require 'lib/item.php';

$sql_criteria= "1=1";
if (($items= $_REQUEST['items'])) {
  list($sql_criteria, $x)= item_terms_to_sql($db, $_REQUEST['items'], FIND_OR);
}

$begin= $_REQUEST['begin'];
$end= $_REQUEST['end'];

if (!$begin) {
  $begin= date('Y-m-d', time() - 3 * 24 * 3600);
} else {
  $begin= $db->escape($begin);
}

if (!$end) {
  $end= date('Y-m-d', time());
} else {
  $end= $db->escape($end);
}

head("Item Sales @ Scat", true);
?>
<form id="report-params" class="form-horizontal" role="form"
      action="<?=$_SERVER['PHP_SELF']?>">
  <div class="form-group">
    <label for="datepicker" class="col-sm-2 control-label">
      Dates
    </label>
    <div class="col-sm-10">
      <div class="input-daterange input-group" id="datepicker">
        <input type="text" class="form-control" name="begin"
               value="<?=ashtml($begin)?>" />
        <span class="input-group-addon">to</span>
        <input type="text" class="form-control" name="end"
               value="<?=ashtml($end)?>" />
      </div>
    </div>
  </div>
  <div class="form-group">
    <label for="items" class="col-sm-2 control-label">
      Items
    </label>
    <div class="col-sm-10">
      <input id="items" name="items" type="text"
             class="form-control" style="width: 20em"
             value="<?=ashtml($items)?>">
    </div>
  </div>
  <div class="form-group">
    <div class="col-sm-offset-2 col-sm-10">
      <input type="submit" class="btn btn-primary" value="Show">
    </div>
  </div>
</form>
<?

$q= "SELECT
            item.id AS meta,
            item.code Code\$item,
            item.name Name\$name,
            SUM(-1 * allocated) Sold,
            AVG(sale_price(txn_line.retail_price, txn_line.discount_type,
                           txn_line.discount)) AvgPrice\$dollar,
            SUM(-1 * allocated * sale_price(txn_line.retail_price,
                                            txn_line.discount_type,
                                            txn_line.discount)) Total\$dollar
       FROM txn
       LEFT JOIN txn_line ON txn.id = txn_line.txn
       LEFT JOIN item ON txn_line.item = item.id
       LEFT JOIN brand ON item.brand = brand.id
       LEFT JOIN barcode ON (item.id = barcode.item)
      WHERE type = 'customer'
        AND ($sql_criteria)
        AND paid BETWEEN '$begin' AND '$end' + INTERVAL 1 DAY
      GROUP BY 1
      ORDER BY 2";

dump_table($db->query($q));

dump_query($q);

foot();
?>
<script>
$(function() {
  $('#report-params .input-daterange').datepicker({
      format: "yyyy-mm-dd",
      todayHighlight: true
  });
});
</script>
