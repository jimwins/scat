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

head("Brand Sales @ Scat", true);
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
/* Current */
$q= "CREATE TEMPORARY TABLE current
       (item_id INT UNSIGNED PRIMARY KEY,
        brand_id INT UNSIGNED NOT NULL,
        units INT NOT NULL,
        amount DECIMAL(9,2) NOT NULL,
        KEY (brand_id))
     SELECT
            item_id, 0 brand_id,
            SUM(-1 * allocated) units,
            SUM(-1 * allocated * sale_price(txn_line.retail_price,
                                            txn_line.discount_type,
                                            txn_line.discount)) amount
       FROM txn
       LEFT JOIN txn_line ON txn.id = txn_line.txn_id
            JOIN item ON txn_line.item_id = item.id
      WHERE type = 'customer'
        AND ($sql_criteria)
        AND filled BETWEEN '$begin' AND '$end' + INTERVAL 1 DAY
        AND item_id IS NOT NULL
      GROUP BY 1";

$db->query($q) or die('Line : ' . __LINE__ . $db->error);

$q= "UPDATE current
        SET brand_id = IFNULL((SELECT product.brand_id
                                 FROM item
                                 JOIN product ON item.product_id = product.id
                                WHERE item.id = current.item_id),
                                0)";

$db->query($q) or die('Line : ' . __LINE__ . $db->error);

/* Previous */
$q= "CREATE TEMPORARY TABLE previous
       (item_id INT UNSIGNED PRIMARY KEY,
        brand_id INT UNSIGNED NOT NULL,
        units INT NOT NULL,
        amount DECIMAL(9,2) NOT NULL,
        KEY (brand_id))
     SELECT
            item_id, 0 brand_id,
            SUM(-1 * allocated) units,
            SUM(-1 * allocated * sale_price(txn_line.retail_price,
                                            txn_line.discount_type,
                                            txn_line.discount)) amount
       FROM txn
       LEFT JOIN txn_line ON txn.id = txn_line.txn_id
            JOIN item ON txn_line.item_id = item.id
      WHERE type = 'customer'
        AND ($sql_criteria)
        AND filled BETWEEN '$begin' - INTERVAL 1 YEAR
                       AND '$end' + INTERVAL 1 DAY - INTERVAL 1 YEAR
        AND item_id IS NOT NULL
      GROUP BY 1";

$db->query($q) or die('Line : ' . __LINE__ . $db->error);

$q= "UPDATE previous
        SET brand_id = IFNULL((SELECT product.brand_id
                                 FROM item
                                 JOIN product ON item.product_id = product.id
                                WHERE item.id = previous.item_id),
                                0)";

$db->query($q) or die('Line : ' . __LINE__ . $db->error);

/* Report */
$q= "SELECT
            name, slug, 0,
            (SELECT SUM(amount) FROM current WHERE brand_id = id)
              AS current_amount,
            (SELECT SUM(amount) FROM previous WHERE brand_id = id)
              AS previous_amount
       FROM brand 
     HAVING current_amount OR previous_amount
      ORDER BY name";

$r= $db->query($q) or die($db->error);

$cat= "";
$parent= 0;
?>
<table class="table table-striped table-sort">
 <thead>
  <tr>
   <th>Brand</th>
   <th align="right">Current</th>
   <th align="right">Previous</th>
   <th align="right">Change</th>
 </thead>
 <tbody id="results">
<?
while ($row= $r->fetch_assoc()) {
  if (@$row['parent'] && !$row['previous_amount'] && !$row['current_amount']) {
    continue;
  }

  if ($row['previous_amount'] == 0) {
    $change = 0;
  } else {
    $change= (($row['current_amount'] - $row['previous_amount']) / $row['previous_amount']) * 100;
  }
?>
  <tr class="XXX<?=($change < 0) ? 'danger' : (($change > 100) ? 'success' : '')?>">
   <td><a href="/report/category?begin=<?=ashtml($begin)?>&end=<?=ashtml($end)?>&items=<?=ashtml($items)?>+brand:<?=$row['slug']?>"><?=ashtml($row['name'])?></a></td>
   <td align="right"><?=amount($row['current_amount'])?></td>
   <td align="right"><?=amount($row['previous_amount'])?></td>
   <td align="right"><?=sprintf("%.1f%%", $change)?></td>
  </tr>
<?}?>
 </tbody>
</table>
<button id="download" class="btn btn-default">Download</button>
<form id="post-csv" style="display: none"
      method="post" action="/api/encode-tsv.php">
  <input type="hidden" name="fn" value="brand-sales.txt">
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
  var tsv= "Brand\tCurrent\tPrevious\tChange\r\n";
  $.each($("#results tr"), function (i, row) {
    if (i > 0) {
      tsv += $('td:nth(0)', row).text() + "\t" +
             $('td:nth(1)', row).text() + "\t" +
             $('td:nth(2)', row).text() + "\t" +
             $('td:nth(3)', row).text() +
             "\r\n";
    }
  });
  $("#file").val(tsv);
  $("#post-csv").submit();
});
</script>

