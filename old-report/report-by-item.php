<?
require '../scat.php';
require '../lib/item.php';

$sql_criteria= "1=1";
if (($items= $_REQUEST['items'])) {
  list($sql_criteria, $x)= item_terms_to_sql($db, $_REQUEST['items'],
                                             FIND_OR|FIND_ALL);
}

$begin= $_REQUEST['begin'];
$end= $_REQUEST['end'];

if (!$begin) {
  $begin= date('Y-m-d', time());
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
      action="<?=str_replace('?'.$_SERVER['QUERY_STRING'], '',
                             $_SERVER['REQUEST_URI'])?>">
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

$q= "SELECT
            item.id AS meta,
            item.code Code\$item,
            item.name Name\$name,
            (SELECT SUM(-1 * allocated)
               FROM txn
               JOIN txn_line ON txn_line.txn_id = txn.id
              WHERE type = 'customer'
                AND txn_line.item_id = item.id
                AND filled BETWEEN '$begin' AND '$end' + INTERVAL 1 DAY)
              AS Sold,
            (SELECT SUM(-1 * allocated * sale_price(txn_line.retail_price,
                                                    txn_line.discount_type,
                                                    txn_line.discount))
               FROM txn
               JOIN txn_line ON txn_line.txn_id = txn.id
              WHERE type = 'customer'
                AND txn_line.item_id = item.id
                AND filled BETWEEN '$begin' AND '$end' + INTERVAL 1 DAY)
              AS Total\$dollar,
            (SELECT SUM(-1 * allocated)
               FROM txn
               JOIN txn_line ON txn_line.txn_id = txn.id
              WHERE type = 'customer'
                AND txn_line.item_id = item.id
                AND filled BETWEEN '$begin' - INTERVAL 1 YEAR 
                               AND '$end' - INTERVAL 1 YEAR + INTERVAL 1 DAY)
              AS LastSold,
            (SELECT SUM(-1 * allocated * sale_price(txn_line.retail_price,
                                                    txn_line.discount_type,
                                                    txn_line.discount))
               FROM txn
               JOIN txn_line ON txn_line.txn_id = txn.id
              WHERE type = 'customer'
                AND txn_line.item_id = item.id
                AND filled BETWEEN '$begin' - INTERVAL 1 YEAR 
                               AND '$end' - INTERVAL 1 YEAR + INTERVAL 1 DAY)
              AS LastTotal\$dollar
       FROM item
       LEFT JOIN brand ON item.brand_id = brand.id
       WHERE ($sql_criteria)
       HAVING Sold OR LastSold
       ORDER BY item.code";

dump_table($db->query($q));

dump_query($q);
?>
  <button id="download" class="btn btn-default">Download</button>
</div>
<form id="post-csv" style="display: none"
      method="post" action="/api/encode-tsv.php">
  <input type="hidden" name="fn" value="item-sales.txt">
  <textarea id="file" name="file"></textarea>
</form>
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

$('#download').on('click', function(ev) {
  var tsv= "Code\tName\tQuantity\r\n";
  $.each($("#results tr"), function (i, row) {
    if (i > 0) {
      tsv += $('td:nth(1) a', row).text() + "\t" +
             $('td:nth(2)', row).text() + "\t" +
             $('td:nth(3)', row).text() +
             "\r\n";
    }
  });
  $("#file").val(tsv);
  $("#post-csv").submit();
});
</script>
