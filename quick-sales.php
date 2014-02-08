<?
require 'scat.php';
require 'lib/item.php';

head("Item Sales @ Scat");

?>
<form id="report-params" class="form-horizontal" role="form"
      action="<?=$_SERVER['PHP_SELF']?>">
  <div class="form-group">
    <label for="datepicker" class="col-sm-2 control-label">
      Dates
    </label>
    <div class="col-sm-10">
      <div class="input-daterange input-group" id="datepicker">
        <input type="text" class="form-control" name="begin" />
        <span class="input-group-addon">to</span>
        <input type="text" class="form-control" name="end" />
      </div>
    </div>
  </div>
  <div class="form-group">
    <label for="items" class="col-sm-2 control-label">
      Items
    </label>
    <div class="col-sm-10">
      <input id="items" name="items" type="text" class="form-control" style="width: 20em">
    </div>
  </div>
  <div class="form-group">
    <div class="col-sm-offset-2 col-sm-10">
      <input type="submit" class="btn btn-primary" value="Show">
    </div>
  </div>
</form>
<?

$begin= $_REQUEST['begin'];
$end= $_REQUEST['end'];

$sql_criteria= "1=1";
if ($_REQUEST['items']) {
  list($sql_criteria, $x)= item_terms_to_sql($db, $_REQUEST['items'], FIND_OR);
}

if (!$begin) {
  $days= $_REQUEST['days'];
  if (!$days) $days= 10;
  $begin= "DATE(NOW() - INTERVAL 3 DAY)";
} else {
  $begin= "'" . $db->escape($begin) . "'";
}

if (!$end) {
  $end= "DATE(NOW() + INTERVAL 1 DAY)";
} else {
  $end= "'" . $db->escape($end) . "' + INTERVAL 1 DAY";
}

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
      WHERE type = 'customer'
        AND ($sql_criteria)
        AND paid BETWEEN $begin AND $end
      GROUP BY 1
      ORDER BY 2";

dump_table($db->query($q));

dump_query($q);

foot:
foot();
?>
<script>
$(function() {
  $('#report-params .input-daterange').datepicker({
      format: "yyyy-mm-dd",
      todayHighlight: true
  });
});
