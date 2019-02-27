<?
require 'scat.php';
require 'lib/item.php';

$sql_criteria= "1=1";
if (($items= $_REQUEST['items'])) {
  list($sql_criteria, $x)= item_terms_to_sql($db, $_REQUEST['items']);
}

$vendor= (int)$_REQUEST['vendor'];
if ($vendor) {
  $sql_criteria= "($sql_criteria) AND vendor_item.vendor = $vendor";
}

head("Price Increases @ Scat", true);
?>
<form id="report-params" class="form-horizontal" role="form"
      action="<?=$_SERVER['PHP_SELF']?>">
  <div class="form-group">
    <label for="vendor" class="col-sm-1 control-label">
      Vendor
    </label>
    <div class="col-sm-4">
      <select class="form-control" name="vendor">
        <option value="">All vendors</option>
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
    <label for="items" class="col-sm-1 control-label">
      Items
    </label>
    <div class="col-sm-4">
      <input id="items" name="items" type="text"
             class="form-control" style="width: 20em"
             value="<?=ashtml($items)?>">
    </div>
    <div class="col-sm-2">
      <input type="submit" class="btn btn-primary" value="Show">
<?if ((int)$_REQUEST['vendor'] > 0) {?>
      <button id="apply-all" class="btn btn-danger">Apply All</button>
<?}?>
    </div>
  </div>
</form>
<?if (!isset($_REQUEST['vendor'])) { foot(); exit; }?>
<div id="results">
<?
$q= "SELECT
            item.id AS meta,
            item.code Code\$item,
            item.name Name\$name,
            item.retail_price AS OldList\$dollar,
            MAX(vendor_item.retail_price) AS NewList\$dollar,
            sale_price(item.retail_price, item.discount_type, item.discount)
              AS OldSale\$dollar,
            CASE
            WHEN (item.discount_type = 'percentage')
              THEN CONCAT(item.discount, '%')
            WHEN (item.discount_type = 'relative')
              THEN CONCAT('-$', item.discount)
            WHEN (item.discount_type = 'fixed')
              THEN CONCAT('$', item.discount)
            ELSE 
              ''
            END
              AS Discount,
            CONCAT('$', CAST(MAX(vendor_item.net_price) / 0.6 AS DECIMAL(9,2)),
                   ' - ',
                   '$', CAST(MAX(vendor_item.net_price) / 0.5 AS DECIMAL(9,2)))
              AS NewSale,
            (SELECT SUM(allocated) FROM txn_line WHERE item = item.id)
              AS Stock
       FROM item
       LEFT JOIN vendor_item ON item.id = vendor_item.item
       LEFT JOIN brand ON item.brand = brand.id
       LEFT JOIN barcode ON (item.id = barcode.item)
      WHERE ABS(vendor_item.retail_price - item.retail_price) > 0.01
        AND item.active AND NOT item.deleted
        AND vendor_item.active
        AND ($sql_criteria)
      GROUP BY 1
      ORDER BY 2";

function Change($row) {
  echo '<a class="price-change" data-id="' . $row[0] . '" data-msrp="' . $row[4] . '"><i class="far fa-money-bill-alt"></a>';
}

dump_table($db->query($q), 'Change$right');

dump_query($q);
?>
  <button id="download" class="btn btn-default">Download</button>
</div>
<form id="post-csv" style="display: none"
      method="post" action="api/encode-tsv.php">
  <input type="hidden" name="fn" value="item-sales.txt">
  <textarea id="file" name="file"></textarea>
</form>
<?
foot();
?>
<script type="text/html" id="change-template">
  <form class="form price-change-form">
    <input type="hidden" name="id">
    <div class="form-group">
      <label for="retail_price" class="control-label">New Retail Price</label>
      <input type="text" class="form-control" name="retail_price"
             placeholder="$0.00">
    </div>
    <div class="form-group">
      <label for="discount" class="control-label">New Discount</label>
      <input type="text" class="form-control" name="discount"
             placeholder="$0.00 or 0%">
    </div>
    <div class="form-group">
      <label class="control-label">
        <input type="checkbox" name="print" value="1" checked>
        Print new label?
      </label>
    </div>
    <input type="submit" class="btn btn-primary" value="Save">
  </form>
</script>
<script>
$(function() {
  $('#report-params .input-daterange').datepicker({
      format: "yyyy-mm-dd",
      todayHighlight: true
  });
});

$('.price-change').popover({
  html: true,
  placement: 'bottom',
  content: function(e) {
    var tmpl= $($('#change-template').html());
    $('input[name="id"]', tmpl).val($(this).data('id'));
    $('input[name="retail_price"]', tmpl).val($(this).data('msrp'));
    return tmpl;
  },
});

$('body').on('submit', '.price-change-form', function(ev) {
  ev.preventDefault();
  var form= $(this);
  Scat.api('item-update', form.serializeArray())
      .done(function (data) {
        if ($('input[name="print"]:checked', form).length) {
          Scat.printDirect('labels-price',
                           { id: $('input[name="id"]', form).val() });
        }
        $(form).parent().parent()
               .siblings('.price-change')
               .popover('hide');
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

$('#apply-all').on('click', function(env) {
  if (confirm("Are you sure you want to update all of these prices?")) {
    Scat.api('vendor-apply-price-changes', { vendor: <?=$vendor?>,
                                             items: <?=json_encode($items)?>})
        .done(function (data) {
          alert("Changes applied.");
        });
  }
  return false;
});
</script>
