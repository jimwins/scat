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

head("Item: " . $item['name']. " @ Scat", true);

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
         data-bind="jeditable: item.brand, jeditableOptions: { type: 'select', submit: 'OK', loadurl: 'api/brand-list.php', onupdate: saveItemProperty, onblur: 'cancel', cssclass: 'form-inline' }"></p>
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
      <!-- ko if: item.inventoried() -->
      Minimum Quantity
      <!-- /ko -->
      <!-- ko if: !item.inventoried() -->
      Not Inventoried
      <!-- /ko -->
    </label>
    <div class="col-sm-8">
      <p class="form-control-static" id="minimum_quantity"
         data-bind="jeditable: item.minimum_quantity, jeditableOptions: { onupdate: saveItemProperty, onblur: 'cancel' }"></p>
    </div>
  </div>
  <div class="form-group">
    <label for="purchase_quantity" class="col-sm-2 control-label">
      Purchase Quantity
    </label>
    <div class="col-sm-8">
      <p class="form-control-static" id="purchase_quantity"
         data-bind="jeditable: item.purchase_quantity, jeditableOptions: { onupdate: saveItemProperty, onblur: 'cancel' }"></p>
    </div>
  </div>

  <div class="form-group">
    <label for="barcodes" class="col-sm-2 control-label">Barcodes</label>
    <div class="col-sm-8">
      <table id="barcodes" class="table table-condensed">
        <tbody data-bind="foreach: item.barcode_list">
          <tr>
            <td><span data-bind="text: $data.code"></span></td>
            <td><span data-bind="text: $data.quantity"></span></td>
            <td><button type="button" class="btn btn-default btn-xs" data-bind="click: $parent.removeBarcode"><i class="fa fa-trash-o"></i></button></td>
          </tr>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="3">
              <button id="new-barcode" class="btn btn-default">
                <i class="fa fa-barcode"></i> New
              </button>
              <div class="btn-group">
                <button type="button" class="btn btn-default"
                        data-bind="click: printBarcode">
                  <i class="fa fa-print"></i> Print
                </button>
                <button type="button" class="btn btn-default dropdown-toggle"
                        data-toggle="dropdown" aria-haspopup="true"
                        aria-expanded="false">
                  <span class="caret"></span>
                  <span class="sr-only">Toggle Dropdown</span>
                </button>
                <ul class="dropdown-menu">
                  <li>
                    <a data-bind="click: printBarcode" data-multiple="1">
                      Multiple
                    </a>
                  </li>
                  <li>
                    <a data-bind="click: printBarcode" data-noprice="1">
                      No price
                    </a>
                  </li>
                </ul>
              </div>
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
  cssclass: 'form-inline',
  placeholder: '',
  data: function(value, settings) {
    $(this).data('original', value);
    return '';
  },
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
echo '<button id="add-vendor" class="btn btn-default">Add Vendor Item</a>';
echo '</div>';

function RunningTotal($row) {
  static $count= 0;
  $count= $count + $row[4];
  return $count;
}

$q= "SELECT DATE_FORMAT(created, '%a, %b %e %Y %H:%i') Date,
            CONCAT(txn, '|', txn.type, '|', txn.number) AS Transaction\$txn,
            CASE type
              WHEN 'customer' THEN IF(SUM(allocated) <= 0, 'Sale', 'Return')
              WHEN 'vendor' THEN 'Stock'
              WHEN 'correction' THEN 'Correction'
              WHEN 'drawer' THEN 'Till Count'
              ELSE type
            END Type,
            AVG(sale_price(retail_price, discount_type, discount))
              AS Price\$dollar,
            SUM(allocated) AS Quantity\$right
       FROM txn_line
       JOIN txn ON (txn_line.txn = txn.id)
      WHERE item = $id
      GROUP BY txn
      ORDER BY created";

echo '<h2 onclick="$(\'#history\').toggle()">History</h2>';
echo '<div id="history" style="display: none">';
dump_table($db->query($q), 'RunningTotal$right');
dump_query($q);
echo '</div>';

echo '<button type="button" class="btn btn-default" data-bind="click: mergeItem">Merge</button>';

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

viewModel.printBarcode= function(place, ev) {
  var item= viewModel.item.id();

  var noprice= $(ev.target).data('noprice') || 0;

  var qty= 1;
  if ($(ev.target).data('multiple')) {
    qty= window.prompt("How many?", "1");
  }

  if (!qty)
    return;

  $.getJSON("print/labels-price.php?callback=?",
            { id: item, noprice: noprice, quantity: qty },
            function (data) {
              if (data.error) {
                displayError(data);
                return;
              }
            });
}

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

viewModel.mergeItem= function(place) {
  var code= window.prompt("Please enter the item to merge this one into.", "");

  if (code) {
    $.getJSON("api/item-merge.php?callback=?",
              { from: viewModel.item.id, to: code },
              function (data) {
                if (data.error) {
                  displayError(data);
                  return;
                }
                loadItem(data.item);
              });
  }
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

  item[key]("\0"); // force knockout to update this observable when item updated

  $.getJSON("api/item-update.php?callback=?",
            data,
            function (data) {
              if (data.error) {
                displayError(data);
                return;
              }
              loadItem(data.item);
            });
  return '<span><i class="fa fa-spinner fa-spin"></i></span>';
}

$('#add-vendor').on('click', function() {
  $.get('ui/item-vendor-item.html').done(function (html) {
    var panel= $(html);

    var vendor_item= { vendor: 0, vendor_sku: '', name: '',
                       retail_price: 0.00, net_price: 0.00,
                       promo_price: '', purchase_qty: 1,
                       vendors: [], error: '' };

    panel.on('hidden.bs.modal', function() {
      $(this).remove();
    });

    $.getJSON('api/person-list.php?callback=?',
              { role: 'vendor' })
      .done(function (data) {
        ko.mapping.fromJS({ vendors: data }, itemModel);
      })
      .fail(function (jqxhr, textStatus, error) {
        var data= $.parseJSON(jqxhr.responseText);
        vendor_item.error(textStatus + ', ' + error + ': ' + data.text)
      });


    itemModel= ko.mapping.fromJS(vendor_item);

    itemModel.saveItem= function(place, ev) {
      1;
    }

    itemModel.selectedVendor= ko.computed({
      read: function () {
        return this.vendor();
      },
      write: function (value) {
        if (typeof value != 'undefined' && value != '') {
          this.vendor(value);
        }
      },
      owner: itemModel
    }).extend({ notify: 'always' });
 

    ko.applyBindings(itemModel, panel[0]);
    panel.appendTo($('body')).modal();
  });
});
</script>
