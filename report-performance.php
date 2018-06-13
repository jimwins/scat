<?
require 'scat.php';
require 'lib/item.php';

$items= $_REQUEST['items'];

if (($saved= (int)$_GET['saved']) && !$items) {
  $items= $db->get_one("SELECT search FROM saved_search WHERE id = $saved");
}

$sql_criteria= "1=1";
if ($items) {
  list($sql_criteria, $x)= item_terms_to_sql($db, $items,
                                             FIND_ALL|FIND_LIMITED);
}

$begin= $_REQUEST['begin'];
$end= $_REQUEST['end'];

if (!$begin) {
  $begin= date('Y-m-d', time() - 365.25*24*3600);
} else {
  $begin= $db->escape($begin);
}

if (!$end) {
  $end= date('Y-m-d', time());
} else {
  $end= $db->escape($end);
}

head("Performance @ Scat", true);

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
<div id="results">
<?
$q= "SELECT SUM(ordered *
                sale_price(txn_line.retail_price, txn_line.discount_type,
                           txn_line.discount)) total
       FROM txn 
       JOIN txn_line ON (txn.id = txn_line.txn)
       JOIN item ON (txn_line.item = item.id)
      WHERE type = 'vendor'
        AND ($sql_criteria)
        AND created BETWEEN '$begin' AND '$end' + INTERVAL 1 DAY";

$purchased= $db->get_one($q);

$q= "SELECT SUM(ordered * -1 *
                sale_price(txn_line.retail_price, txn_line.discount_type,
                           txn_line.discount)) total
       FROM txn 
       JOIN txn_line ON (txn.id = txn_line.txn)
       JOIN item ON (txn_line.item = item.id)
      WHERE type = 'customer'
        AND ($sql_criteria)
        AND filled BETWEEN '$begin' AND '$end' + INTERVAL 1 DAY";

$sold= $db->get_one($q);

$q= "SELECT SUM((SELECT SUM(allocated) FROM txn_line WHERE item = item.id) *
                sale_price(item.retail_price, item.discount_type,
                           item.discount))
       FROM item
      WHERE ($sql_criteria)";

$stock= $db->get_one($q);

$q= "SELECT SUM(minimum_quantity *
                sale_price(item.retail_price, item.discount_type,
                           item.discount))
       FROM item
      WHERE ($sql_criteria) AND item.active";

$ideal= $db->get_one($q);
?>
</div>
<dl>
  <dt>Purchased:</dt> <dd><?=amount($purchased)?></dd>
  <dt>Sold:</dt> <dd><?=amount($sold)?></dd>
  <dt>Stock:</dt> <dd><?=amount($stock)?></dd>
  <dt>Ideal:</dt> <dd><?=amount($ideal)?></dd>
  <dt>Turns:</dt> <dd><?=($sold / $ideal)?></dd>
</dl>
<?
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

