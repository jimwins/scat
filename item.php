<?
require 'scat.php';
require 'lib/item.php';

head("item");

$code= $_GET['code'];
$id= (int)$_GET['id'];
?>
<form method="get" action="items.php">
<input id="autofocus" type="text" name="q" value="<?=htmlspecialchars($code)?>">
<input type="submit" value="Find Items">
</form>
<br>
<?

if (!$code && !$id) exit;

if (!$id && $code) {
  $r= $db->query("SELECT id FROM item WHERE code = '" .
                 $db->real_escape_string($code) . "'");
  if (!$r) die($m->error);

  if (!$r->num_rows)
      die("<h2>No item found.</h2>");

  $id= $r->fetch_row();
  $id= $id[0];
}

$item= item_load($db, $id);

?>
<script>
$(function() {
  loadItem(<?=json_encode($item)?>);
});

var protoBarcodeRow= $('<tr><td></td><td class="quantity"></td><td>' +
                       '<i class="remove fa fa-minus-square-o"></i>' +
                       '</td></tr>');

function loadItem(item) {
  $('#item').data('item', item);
  var active= parseInt(item.active);
  if (active) {
    $('#item #active').removeClass('fa-square-o').addClass('fa-check-square-o');
  } else {
    $('#item #active').removeClass('fa-check-square-o').addClass('fa-square-o');
  }
  $('#item #code').text(item.code);
  $('#item #name').text(item.name);
  $('#item #brand').text(item.brand);
  $('#item #retail_price').text(amount(item.retail_price));
  $('#item #sale').text(amount(item.sale_price));
  $('#item #discount').text(item.discount_label);
  $('#item #stock').text(item.stock);
  $('#item #minimum_quantity').text(item.minimum_quantity);
  $('#item #last_net').text(amount(item.last_net));

  $('#item #barcodes tbody').empty();
  if (typeof(item.barcodes) != 'undefined') {
    var barcodes= item.barcodes.split(/,/);
    $.each(barcodes, function(i, barcode) {
      var info= barcode.split(/!/);
      var row= protoBarcodeRow.clone();
      $('td:nth(0)', row).text(info[0]);
      $('td:nth(1)', row).text(info[1]);
      $('.quantity', row).editable(editBarcodeQuantity);
      $('#item #barcodes tbody').append(row);
    });
  }
}
</script>
<style>
#item th { text-align: right; color: #666; vertical-align: top; }
#item th:after { content: ':'; }
#item td { padding-left: 1em; padding-right: 1em; }
#barcodes tr:nth-child(odd), #barcodes tr:nth-child(even) {
  background: inherit;
}
</style>
<table id="item">
 <tr><th><i id="print" class="fa fa-print"></i> Code</th><td><span id="code" class="editable"></span><i id="active" class="pull-right fa fa-check-square-o"></i></td></tr>
 <tr><th>Name</th><td id="name" class="editable"></td></tr>
 <tr><th>Brand</th><td id="brand"></td></tr>
 <tr><th>MSRP</th><td id="retail_price" class="editable"></td></tr>
 <tr><th>Sale</th><td id="sale"></td></tr>
 <tr><th>Discount</th><td id="discount" class="editable"></td></tr>
 <tr><th>Stock</th><td id="stock" class="editable"></td></tr>
 <tr><th>Minimum Stock</th><td id="minimum_quantity" class="editable"></td></tr>
 <tr><th onclick="$('#last_net').toggle()">Last Net</th><td id="last_net" style="display:none"></td></tr>
 <tr>
  <th>Barcodes</th>
  <td style="padding: 0">
   <table id="barcodes" width="100%" style="padding: 0; margin: 0">
    <tbody></tbody>
    <tfoot>
     <tr><td id="new-barcode" style="width:12em"><i class="fa fa-plus-square-o"></i></td><td></td><td></td></tr>
    </tfoot>
   </table>
  </td>
 </tr>
</table>
<script>
$('#item .editable').editable(function(value, settings) {
  var item= $('#item').data('item');
  var data= { item: item.id };
  var key= this.id;
  data[key] = value;

  $.getJSON("api/item-update.php?callback=?",
            data,
            function (data) {
              if (data.error) {
                $.modal(data.error);
                return;
              }
              loadItem(data.item);
            });
  return "...";
}, {
  event: 'click',
  style: 'display: inline',
  placeholder: '',
});
$('#item #brand').editable(function(value, settings) {
  var item= $('#item').data('item');

  $.getJSON("api/item-update.php?callback=?",
            { item: item.id, brand: value },
            function (data) {
              if (data.error) {
                $.modal(data.error);
                return;
              }
              loadItem(data.item);
            });
  return "...";
}, {
  event: 'dblclick',
  style: 'display: inline',
  type: 'select',
  submit: 'OK',
  loadurl: 'api/brand-list.php',
  placeholder: '',
});
$('#item #active').on('dblclick', function(ev) {
  ev.preventDefault();
  var item= $('#item').data('item');

  $.getJSON("api/item-update.php?callback=?",
            { item: item.id, active: parseInt(item.active) ? 0 : 1 },
            function (data) {
              if (data.error) {
                $.modal(data.error);
                return;
              }
              loadItem(data.item);
            });
});
function editBarcodeQuantity(value, settings) {
  var item= $('#item').data('item');
  var row= $(this).closest('tr');
  var code= $('td:nth(0)', row).text();

  $.getJSON("api/item-barcode-update.php?callback=?",
            { item: item.id, code: code, quantity: value},
            function (data) {
              if (data.error) {
                $.modal(data.error);
                return;
              }
              loadItem(data.item);
            });
}
$('#barcodes').on('dblclick', '.remove', function(ev) {
  var item= $('#item').data('item');
  var row= $(this).closest('tr');
  var code= $('td:nth(0)', row).text();
  var qty= $('td:nth(1)', row).text();

  $.getJSON("api/item-barcode-delete.php?callback=?",
            { item: item.id, code: code },
            function (data) {
              if (data.error) {
                $.modal(data.error);
                return;
              }
              loadItem(data.item);
            });
});
$('#barcodes #new-barcode').editable(function(value, settings) {
  var item= $('#item').data('item');
  $.getJSON("api/item-barcode-update.php?callback=?",
            { item: item.id, code: value },
            function (data) {
              if (data.error) {
                $.modal(data.error);
                return;
              }
              loadItem(data.item);
            });
  return  $(this).data('original');
}, {
  event: 'dblclick',
  style: 'display: inline',
  placeholder: '',
  data: function(value, settings) {
    $(this).data('original', value);
    return '';
  },
});
$('#item #print').on('dblclick', function(ev) {
  ev.preventDefault();
  var item= $('#item').data('item');

  $.getJSON("print/labels-price.php?callback=?",
            { id: item.id },
            function (data) {
              if (data.error) {
                $.modal(data.error);
                return;
              }
            });
});
</script>
<?

$q= "SELECT company Company,
            retail_price MSRP\$dollar,
            net_price Net\$dollar,
            promo_price Promo\$dollar
       FROM vendor_item
       JOIN person ON vendor_item.vendor = person.id
      WHERE item = $id";

echo '<h2 onclick="$(\'#vendors\').show()">Vendors</h2>';
echo '<div id="vendors" style="display: none">';
dump_table($db->query($q));
dump_query($q);
echo '</div>';

$r= $db->query("SET @count = 0");

$q= "SELECT DATE_FORMAT(created, '%a, %b %e %Y %H:%i') Date,
            CONCAT(txn, '|', txn.type, '|', txn.number) AS Transaction\$txn,
            CASE type
              WHEN 'customer' THEN IF(allocated <= 0, 'Sale', 'Return')
              WHEN 'vendor' THEN 'Stock'
              WHEN 'correction' THEN 'Correction'
              WHEN 'drawer' THEN 'Till Count'
              ELSE type
            END Type,
            IF(discount_type,
               CASE discount_type
                 WHEN 'percentage' THEN ROUND(retail_price * ((100 - discount) / 100), 2)
                 WHEN 'relative' THEN (retail_price - discount) 
                 WHEN 'fixed' THEN (discount)
               END,
               retail_price) AS Price\$dollar,
            allocated AS Quantity\$right,
            @count := @count + allocated AS Count\$right
       FROM txn_line
       JOIN txn ON (txn_line.txn = txn.id)
      WHERE item = $id
      GROUP BY txn
      ORDER BY created";

echo '<h2>History</h2>';
dump_table($db->query($q));
dump_query($q);

foot();
