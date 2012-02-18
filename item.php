<?
require 'scat.php';
require 'lib/item.php';

head("item");

$code= $_GET['code'];
$id= (int)$_GET['id'];
?>
<form method="get" action="items.php">
<input id="focus" type="text" name="code" value="<?=htmlspecialchars($code)?>">
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

var protoBarcodeRow= $('<tr><td></td><td></td><td>' +
                       '<img align="right" class="remove" src="icons/delete.png" width="16" height="16">' +
                       '</td></tr>');

function loadItem(item) {
  $('#item').data('item', item);
  var active= parseInt(item.active);
  if (active) {
    $('#item #active').attr('src', 'icons/accept.png');
  } else {
    $('#item #active').attr('src', 'icons/cross.png');
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
  var barcodes= item.barcodes.split(/,/);
  $.each(barcodes, function(i, barcode) {
    var info= barcode.split(/!/);
    var row= protoBarcodeRow.clone();
    $('td:nth(0)', row).text(info[0]);
    $('td:nth(1)', row).text(info[1]);
    $('#item #barcodes tbody').append(row);
  });
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
 <tr><th>Code</th><td><span id="code" class="editable"></span><img id="active" align="right" src="icons/accept.png" height="16" width="16"></td></tr>
 <tr><th>Name</th><td id="name" class="editable"></td></tr>
 <tr><th>Brand</th><td id="brand"></td></tr>
 <tr><th>MSRP</th><td id="retail_price" class="editable"></td></tr>
 <tr><th>Sale</th><td id="sale"></td></tr>
 <tr><th>Discount</th><td id="discount" class="editable"></td></tr>
 <tr><th>Stock</th><td id="stock" class="editable"></td></tr>
 <tr><th>Minimum Stock</th><td id="minimum_quantity"></td></tr>
 <tr><th>Last Net</th><td id="last_net"></td></tr>
 <tr>
  <th>Barcodes</th>
  <td>
   <table id="barcodes" width="100%">
    <tbody></tbody>
    <tfoot>
     <tr><td></td><td></td><td id="add-barcode"><img align="right" src="icons/add.png" width="16" height="16"></td></tr>
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
  event: 'dblclick',
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
});
</script>
<?
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
