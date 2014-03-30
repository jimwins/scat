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

head("Price Increases @ Scat", true);
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
$q= "SELECT
            item.id AS meta,
            item.code Code\$item,
            item.name Name\$name,
            item.retail_price AS OldList\$dollar,
            vendor_item.retail_price AS NewList\$dollar,
            sale_price(item.retail_price, item.discount_type, item.discount)
              AS OldSale\$dollar,
            CONCAT('$', CAST(vendor_item.net_price / 0.6 AS DECIMAL(9,2)),
                   ' - ',
                   '$', CAST(vendor_item.net_price / 0.5 AS DECIMAL(9,2)))
              AS NewSale,
            (SELECT SUM(allocated) FROM txn_line WHERE item = item.id)
              AS Stock
       FROM item
       LEFT JOIN vendor_item ON item.id = vendor_item.item
       LEFT JOIN brand ON item.brand = brand.id
       LEFT JOIN barcode ON (item.id = barcode.item)
      WHERE vendor_item.retail_price != item.retail_price
        AND active AND NOT deleted
        AND ($sql_criteria)
      GROUP BY 1
      ORDER BY 2";

function Change($row) {
  echo '<a class="price-change" data-id="' . $row[0] . '" data-msrp="' . $row[4] . '"><i class="fa fa-money"></a>';
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
  $.getJSON("api/item-update.php?callback=?",
            form.serializeArray(),
            function (data) {
              if (data.error) {
                alert(data.error);
                return;
              }
              if ($('input[name="print"]:checked', form).length) {
                $.getJSON("print/labels-price.php?callback=?",
                          { id: $('input[name="id"]', form).val() },
                          function (data) {
                            if (data.error) {
                              alert(data.error);
                              return;
                            }
                          });
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
</script>
