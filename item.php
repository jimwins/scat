<?
require 'scat.php';
require 'lib/item.php';

$code= $_GET['code'];
$id= (int)$_GET['id'];

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

$search= "";

head("Item: " . ashtml($item['name']). " @ Scat", true);

include 'item-searchform.php';
?>
<form class="form-horizontal" role="form">
  <div class="form-group">
    <label for="code" class="col-sm-2 control-label">
      <a class="text-left fa" id="active"
         data-bind="css: { 'fa-check-square-o' : item.active(), 'fa-square-o' : !item.active() }"></a>
      Code
    </label>
    <div class="col-sm-8">
      <p class="form-control-static" id="code"
         data-bind="jeditable: item.code, jeditableOptions: { onupdate: saveItemProperty, onblur: 'cancel' }"></p>
    </div>
  </div>
  <div class="form-group">
    <label for="name" class="col-sm-2 control-label">
      <a data-bind="attr: { href: 'http://rawm.us/' + item.code() }"
         target="_blank">
        <i class="fa fa-external-link"></i>
      </a>
      Name
    </label>
    <div class="col-sm-8">
      <p class="form-control-static" id="name"
         data-bind="jeditable: item.name, jeditableOptions: { onupdate: saveItemProperty, onblur: 'cancel' }"></p>
    </div>
  </div>
  <div class="form-group">
    <label for="brand" class="col-sm-2 control-label">Brand</label>
    <div class="col-sm-8">
      <p class="form-control-static" id="brand_id"
         data-bind="jeditable: item.brand, jeditableOptions: { type: 'select', submit: 'OK', loadurl: 'api/brand-list.php', onupdate: saveItemProperty, onblur: 'cancel' }"></p>
    </div>
  </div>
  <div class="form-group">
    <label for="retail_price" class="col-sm-2 control-label">List</label>
    <div class="col-sm-8">
      <p class="form-control-static" id="retail_price"
         data-bind="jeditable: item.retail_price, jeditableOptions: { ondisplay: amount, data: item.retail_price(), onupdate: saveItemProperty, onblur: 'cancel' }"></p>
    </div>
  </div>
  <div class="form-group">
    <label for="sale_price" class="col-sm-2 control-label">Sale</label>
    <div class="col-sm-8">
      <p class="form-control-static"
         data-bind="text: amount(item.sale_price())"></p>
    </div>
  </div>
  <div class="form-group">
    <label for="discount" class="col-sm-2 control-label">Discount</label>
    <div class="col-sm-8">
      <p class="form-control-static" id="discount"
         data-bind="jeditable: item.discount, jeditableOptions: { ondisplay: function() { return item.discount_label() ? item.discount_label() : item.discount() ? amount(item.discount()) : '...' } , data : item.discount(), onupdate: saveItemProperty, onblur: 'cancel' }"></p>
    </div>
  </div>
  <div class="form-group">
    <label for="stock" class="col-sm-2 control-label">Stock</label>
    <div class="col-sm-8">
      <p class="form-control-static" id="stock"
         data-bind="jeditable: item.stock, jeditableOptions: { onupdate: saveItemProperty, onblur: 'cancel' }"></p>
    </div>
  </div>
  <div class="form-group">
    <label for="minimum_quantity" class="col-sm-2 control-label">
      Minimum Quantity
    </label>
    <div class="col-sm-8">
      <p class="form-control-static" id="minimum_quantity"
         data-bind="jeditable: item.minimum_quantity, jeditableOptions: { onupdate: saveItemProperty, onblur: 'cancel' }"></p>
    </div>
  </div>

  <div class="form-group">
    <label for="barcodes" class="col-sm-2 control-label"><a id="print" class="fa fa-print"></a> Barcodes</label>
    <div class="col-sm-8">
      <table id="barcodes" class="table table-condensed">
        <tbody data-bind="foreach: item.barcode_list">
          <tr>
            <td><span data-bind="click: $parent.removeBarcode"><a class="fa fa-trash-o"></a></span></td>
            <td><span data-bind="text: $data.code"></span></td>
            <td><span data-bind="text: $data.quantity"></span></td>
          </tr>
        </tbody>
        <tfoot>
          <tr>
            <td id="new-barcode" colspan="3">
              <a class="fa fa-plus-square-o"></a>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</form>
<script>
$('#active').on('click', function(ev) {
  ev.preventDefault();
  var item= viewModel.item;

  $.getJSON("api/item-update.php?callback=?",
            { item: item.id(), active: item.active() ? 0 : 1 },
            function (data) {
              if (data.error) {
                displayError(data);
                return;
              }
              loadItem(data.item);
            });
});
function editBarcodeQuantity(value, settings) {
  var item= viewModel.item;
  var row= $(this).closest('tr');
  var code= $('td:nth(0)', row).text();

  $.getJSON("api/item-barcode-update.php?callback=?",
            { item: item.id, code: code, quantity: value},
            function (data) {
              if (data.error) {
                displayError(data);
                return;
              }
              loadItem(data.item);
            });
}
$('#new-barcode').editable(function(value, settings) {
  var item= viewModel.item;
  $.getJSON("api/item-barcode-update.php?callback=?",
            { item: item.id, code: value },
            function (data) {
              if (data.error) {
                displayError(data);
                return;
              }
              loadItem(data.item);
            });
  return  $(this).data('original');
}, {
  event: 'click',
  style: 'display: inline',
  placeholder: '',
  data: function(value, settings) {
    $(this).data('original', value);
    return '';
  },
});
$('#print').on('click', function(ev) {
  ev.preventDefault();
  var item= viewModel.item.id();

  $.getJSON("print/labels-price.php?callback=?",
            { id: item },
            function (data) {
              if (data.error) {
                displayError(data);
                return;
              }
            });
});
</script>
<?

$q= "SELECT company Company,
            code Code,
            retail_price List\$dollar,
            net_price Net\$dollar,
            promo_price Promo\$dollar,
            CONCAT('$', CAST(vendor_item.net_price / 0.6 AS DECIMAL(9,2)),
                   ' - ',
                   '$', CAST(vendor_item.net_price / 0.5 AS DECIMAL(9,2)))
              AS Sale,
            purchase_quantity AS OrderQuantity
       FROM vendor_item
       JOIN person ON vendor_item.vendor = person.id
      WHERE item = $id";

echo '<h2 onclick="$(\'#vendors\').toggle()">Vendors</h2>';
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

echo '<h2 onclick="$(\'#history\').toggle()">History</h2>';
echo '<div id="history" style="display: none">';
dump_table($db->query($q));
dump_query($q);
echo '</div>';

foot();
?>
<script>
var model= {
  search: '<?=ashtml($search);?>',
  all: <?=(int)$all?>,
  item: <?=json_encode($item);?>,
  brands: [],
};

var viewModel= ko.mapping.fromJS(model);

viewModel.removeBarcode= function(place) {
  $.getJSON("api/item-barcode-delete.php?callback=?",
            { item: viewModel.item.id, code: place.code },
            function (data) {
              if (data.error) {
                displayError(data);
                return;
              }
              loadItem(data.item);
            });
}

ko.applyBindings(viewModel);

function loadItem(item) {
  ko.mapping.fromJS({ item: item }, viewModel);
}

function saveItemProperty(value, settings) {
  var item= viewModel.item;
  var data= { item: item.id() };
  var key= this.id;
  data[key]= value;

  $.getJSON("api/item-update.php?callback=?",
            data,
            function (data) {
              if (data.error) {
                displayError(data);
                return;
              }
              loadItem(data.item);
            });
  return "...";
}
</script>
