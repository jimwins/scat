<?
include 'scat.php';

head("Reorder @ Scat", true);

$extra= $extra_field= $extra_field_name= '';
$code_field= "code";

$all= (int)$_REQUEST['all'];

$vendor= (int)$_REQUEST['vendor'];
if ($vendor > 0) {
  $code_field= "(SELECT code FROM vendor_item WHERE vendor = $vendor AND item = item.id LIMIT 1)";
  $extra= "AND EXISTS (SELECT id
                         FROM vendor_item
                        WHERE vendor = $vendor
                          AND item = item.id)";
  $extra_field= "(SELECT MIN(IF(promo_price, promo_price, net_price)
                             * ((100 - vendor_rebate) / 100))
                    FROM vendor_item
                    JOIN person ON vendor_item.vendor = person.id
                  WHERE item = item.id
                    AND NOT special_order
                    AND vendor = $vendor) -
                 (SELECT MIN(IF(promo_price, promo_price, net_price)
                             * ((100 - vendor_rebate) / 100))
                    FROM vendor_item
                    JOIN person ON vendor_item.vendor = person.id
                   WHERE item = item.id
                     AND NOT special_order
                     AND vendor != $vendor)
                 Cheapest\$trool, ";
  $extra_field_name= "Cheapest\$trool,";
} else if ($vendor < 0) {
  // No vendor
  $extra= "AND NOT EXISTS (SELECT id
                             FROM vendor_item
                            WHERE item = item.id)";
}

$code= $_REQUEST['code'];
if ($code) {
  $extra= "AND code LIKE '" . $db->escape($code) . "%'";
}
?>
<style>
.order { text-align: right; }
</style>
<form class="form-inline" method="get" action="<?=$_SERVER['PHP_SELF']?>">
  <div class="form-group">
    <label class="sr-only" for="vendor"></label>
    <select class="form-control" name="vendor">
      <option value="">All vendors</option>
      <option value="-1" <?if ($vendor == -1) echo 'selected'?>>
        No vendor
      </option>
<?
$q= "SELECT id, company FROM person WHERE role = 'vendor' ORDER BY company";
$r= $db->query($q);

while ($row= $r->fetch_assoc()) {
  echo '<option value="', $row['id'], '"',
       ($row['id'] == $vendor) ? ' selected' : '',
       '>',
       ashtml($row['company']),
       '</option>';
}
?>
    </select>
  </div>
  <div class="form-group">
    <label class="sr-only" for="code">Code</label>
    <input type="text" class="form-control" name="code" placeholder="Code"
           value="<?=ashtml($code)?>">
  </div>
  <div class="checkbox">
    <label>
      <input type="checkbox" name="all" value="1"
       <?=($all ? 'checked="checked"' : '')?>>
      All?
    </label>
  </div>
  <button type="submit" class="btn btn-primary">Limit</button>
</form>

<div class="pull-right">
  <button id="zero" class="btn btn-default">Zero</button>
</div>
<?
$criteria= ($all ? '1=1'
                 : '(ordered IS NULL OR NOT ordered)
                    AND IFNULL(Stock$right, 0) < Min$right');
$q= "SELECT meta, Code\$item, Name, Stock\$right,
            Min\$right,
            Last3Months\$right,
            $extra_field_name
            Order\$order
       FROM (SELECT item.id meta,
                    $code_field Code\$item,
                    name Name,
                    SUM(allocated) Stock\$right,
                    minimum_quantity Min\$right,
                    (SELECT -1 * SUM(allocated)
                       FROM txn_line JOIN txn ON (txn = txn.id)
                      WHERE type = 'customer'
                        AND txn_line.item = item.id
                        AND filled > NOW() - INTERVAL 3 MONTH)
                    AS Last3Months\$right,
                    (SELECT SUM(ordered - allocated)
                       FROM txn_line JOIN txn ON (txn = txn.id)
                      WHERE type = 'vendor'
                        AND txn_line.item = item.id
                        AND created > NOW() - INTERVAL 12 MONTH)
                    AS ordered,
                    $extra_field
                    IF(minimum_quantity > minimum_quantity - SUM(allocated),
                       minimum_quantity,
                       minimum_quantity - IFNULL(SUM(allocated), 0))
                      AS Order\$order
               FROM item
               LEFT JOIN txn_line ON (item = item.id)
              WHERE purchase_quantity
                AND item.active AND NOT item.deleted
                $extra
              GROUP BY item.id
              ORDER BY code) t
       WHERE $criteria
       ORDER BY Code\$item
      ";

dump_table($db->query($q));
?>
<button id="download" class="btn btn-default">Download TSV</button>
<button id="download-xls" class="btn btn-default">Download XLS</button>
<?if ($vendor > 0) {?>
  <button id="create" class="btn btn-default">Create Order</button>
<?}?>
<form id="post-csv" style="display: none"
      method="post" action="api/encode-tsv.php">
<textarea id="file" name="file"></textarea>
</form>
<form id="post-xls" style="display: none"
      method="post" action="api/encode-xls.php">
<textarea id="file" name="file"></textarea>
</form>
<script>
$('.order').editable(function (val, settings) { return val; },
                     { width: '3em', select: true });

$('#download').on('click', function(ev) {
  var tsv= "code\tqty\r\n";
  $.each($(".sortable tr"), function (i, row) {
    if (i > 0 && parseInt($('.order', row).text()) > 0) {
      tsv += $('.item a', row).text() + "\t" + $('.order', row).text() + "\r\n";
    }
  });
  $("#post-csv #file").val(tsv);
  $("#post-csv").submit();
});
$('#download-xls').on('click', function(ev) {
  var tsv= "code\tqty\r\n";
  $.each($(".sortable tr"), function (i, row) {
    if (i > 0 && parseInt($('.order', row).text()) > 0) {
      tsv += $('.item a', row).text() + "\t" + $('.order', row).text() + "\r\n";
    }
  });
  $("#post-xls #file").val(tsv);
  $("#post-xls").submit();
});
$('#create').on('click', function(ev) {
  var order= [];
  $.each($(".sortable tr"), function (i, row) {
    if (i > 0 && parseInt($('.order', row).text()) > 0) {
      order.push([ $(row).attr('data-id'), $('.order', row).text() ]);
    }
  });

  Scat.api("txn-create", { type: 'vendor', person: <?=$vendor?> })
      .done(function (data) {
        Scat.api("txn-add-items", { txn: data.txn.id, items: order },
                 { method: 'POST' })
            .done(function(data) {
              window.location= './?id=' + data.txn.id;
            });
      });
});
$('#zero').on('click', function(ev) {
  $('.order').text('0');
});
</script>
<?

foot();
