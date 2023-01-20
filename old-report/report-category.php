<?
require '../scat.php';
require '../lib/item.php';

$sql_criteria= "1=1";
if (($items= @$_REQUEST['items'])) {
  list($sql_criteria, $x)= item_terms_to_sql($db, $_REQUEST['items'],
                                             FIND_OR|FIND_ALL|FIND_NEW_METHOD);
}

$begin= @$_REQUEST['begin'];
$end= @$_REQUEST['end'];

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

head("Category Sales @ Scat", true);
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
$q= "CREATE TEMPORARY TABLE report_current
       (item_id INT UNSIGNED PRIMARY KEY,
        product_id INT UNSIGNED,
        department_id INT UNSIGNED,
        units INT NOT NULL,
        amount DECIMAL(9,2) NOT NULL,
        KEY (department_id))
     SELECT
            txn_line.item_id, product_id, department_id,
            SUM(-1 * allocated) units,
            SUM(-1 * allocated * sale_price(txn_line.retail_price,
                                            txn_line.discount_type,
                                            txn_line.discount)) amount
       FROM txn
       LEFT JOIN txn_line ON txn.id = txn_line.txn_id
            JOIN item ON txn_line.item_id = item.id
       LEFT JOIN barcode ON item.id = barcode.item_id
       LEFT JOIN product ON product_id = product.id
       LEFT JOIN brand ON product.brand_id = brand.id
       LEFT JOIN department ON department.id = department_id
      WHERE type = 'customer'
        AND ($sql_criteria)
        AND filled BETWEEN '$begin' AND '$end' + INTERVAL 1 DAY
        AND txn_line.item_id IS NOT NULL
      GROUP BY 1";

$db->query($q) or die('Line : ' . __LINE__ . $db->error);

/* Previous */
$q= "CREATE TEMPORARY TABLE report_previous
       (item_id INT UNSIGNED PRIMARY KEY,
        product_id INT UNSIGNED,
        department_id INT UNSIGNED,
        units INT NOT NULL,
        amount DECIMAL(9,2) NOT NULL,
        KEY (department_id))
     SELECT
            txn_line.item_id, product_id, department_id,
            SUM(-1 * allocated) units,
            SUM(-1 * allocated * sale_price(txn_line.retail_price,
                                            txn_line.discount_type,
                                            txn_line.discount)) amount
       FROM txn
       LEFT JOIN txn_line ON txn.id = txn_line.txn_id
            JOIN item ON txn_line.item_id = item.id
       LEFT JOIN barcode ON item.id = barcode.item_id
       LEFT JOIN product ON product_id = product.id
       LEFT JOIN brand ON product.brand_id = brand.id
       LEFT JOIN department ON department.id = department_id
      WHERE type = 'customer'
        AND ($sql_criteria)
        AND filled BETWEEN '$begin' - INTERVAL 1 YEAR
                       AND '$end' + INTERVAL 1 DAY - INTERVAL 1 YEAR
        AND txn_line.item_id IS NOT NULL
      GROUP BY 1";

$db->query($q) or die('Line : ' . __LINE__ . $db->error);

/* Report */
$q= "SELECT
            name, parent_id,
              IF(parent_id,
               CONCAT((SELECT slug FROM department d
                        WHERE d.id = department.parent_id), '/', slug),
               slug)
              AS full_slug,
            slug,
            (SELECT SUM(amount) FROM report_current WHERE department_id = id)
              AS current_amount,
            (SELECT SUM(amount) FROM report_previous WHERE department_id = id)
              AS previous_amount
       FROM department
     HAVING NOT parent_id OR current_amount OR previous_amount
/*
      UNION
      SELECT
            'Unknown' AS name, 0 AS parent_id,
            ''  AS slug,
            (SELECT SUM(amount) FROM report_current
                               WHERE department_id IS NULL)
              AS current_amount,
            (SELECT SUM(amount) FROM report_previous
                               WHERE department_id IS NULL)
              AS previous_amount
*/
      ORDER BY full_slug
      ";

$r= $db->query($q) or die($db->error);

$cat= "";
$parent= 0;
?>
<table class="table table-striped table-sort">
 <thead>
  <tr>
   <th>Category</th>
   <th style="text-align: right">Current</th>
   <th style="text-align: right">Previous</th>
   <th style="text-align: right">Change</th>
 </thead>
 <tbody>
<?
while ($row= $r->fetch_assoc()) {
  if ($row['parent_id'] && !$row['previous_amount'] && !$row['current_amount']) {
    continue;
  }

  if ($row['previous_amount'] == 0) {
    $change = 0;
  } else {
    $change= (($row['current_amount'] - $row['previous_amount']) / $row['previous_amount']) * 100;
  }
?>
  <tr>
    <td>
      <a href="/catalog/search?q=<?=rawurlencode($items)?>+category:<?=$row['slug']?>">
        <?=$row['parent_id'] ? ' &nbsp; ' . ashtml($row['name']) : '<b> ' . ashtml($row['name']) . '</b>' ?>
      </a>
    </td>
    <td align="right"><?=amount($row['current_amount'])?></td>
    <td align="right"><?=amount($row['previous_amount'])?></td>
    <td align="right"><?=sprintf("%.1f%%", $change)?></td>
  </tr>
<?}?>
 </tbody>
</table>

<?
$db->query("DROP TABLE report_current, report_previous");
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

