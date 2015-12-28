<?
include 'scat.php';

head("Reorder @ Scat", true);

$extra= $extra_field= $extra_field_name= '';
$code_field= "code";

$vendor= (int)$_REQUEST['vendor'];
if ($vendor) {
  $code_field= "(SELECT code FROM vendor_item WHERE vendor = $vendor AND item = item.id LIMIT 1)";
  $extra= "AND EXISTS (SELECT id
                         FROM vendor_item
                        WHERE vendor = $vendor
                          AND item = item.id)";
  $extra_field= "(SELECT MIN(IF(promo_price, promo_price, net_price)) FROM vendor_item WHERE item = item.id AND vendor = $vendor) <= (SELECT MIN(IF(promo_price, promo_price, net_price)) FROM vendor_item WHERE item = item.id) Cheapest\$bool, ";
  $extra_field_name= "Cheapest\$bool,";
}
if ((int)$_REQUEST['novendor']) {
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
<button id="download" class="btn btn-default">Download</button>
<?if ($vendor) {?>
  <button id="create" class="btn btn-default">Create Order</button>
<?}?>
<button id="zero" class="btn btn-default">Zero</button>
<?

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
              WHERE active AND NOT deleted
                $extra
              GROUP BY item.id
              ORDER BY code) t
       WHERE (ordered IS NULL OR NOT ordered) AND
             IFNULL(Stock\$right, 0) < Min\$right
       ORDER BY Code\$item
      ";

dump_table($db->query($q));
dump_query($q);
?>
<form id="post-csv" style="display: none"
      method="post" action="api/encode-tsv.php">
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
  $("#file").val(tsv);
  $("#post-csv").submit();
});
$('#create').on('click', function(ev) {
  var order= {};
  $.each($(".sortable tr"), function (i, row) {
    if (i > 0 && parseInt($('.order', row).text()) > 0) {
      order[$(row).attr('data-id')]= $('.order', row).text();
    }
  });

  $.getJSON("api/txn-create.php?callback=?",
            { type: 'vendor', person: <?=$vendor?> },
            function (data) {
              if (data.error) {
                displayError(data);
              }
              $.getJSON("api/txn-add-items.php?callback=?",
                        { txn: data.txn.id, items: order },
                        function (data) {
                          if (data.error) {
                            displayError(data);
                          }
                          window.location= 'txn.php?id=' + data.txn.id;
                        });
            });
});
$('#zero').on('click', function(ev) {
  $('.order').text('0');
});
</script>
<?

foot();
