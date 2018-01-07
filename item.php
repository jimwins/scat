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
    <label for="code" class="col-sm-2 control-label">
      <a class="text-left fa" id="reviewed"
         data-bind="css: { 'fa-check-square-o' : item.reviewed(), 'fa-square-o' : !item.reviewed() }"></a>
      Short Name
    </label>
    <div class="col-sm-8">
      <p class="form-control-static" id="short_name"
         data-bind="jeditable: item.short_name, jeditableOptions: { onupdate: saveItemProperty, onblur: 'cancel' }"></p>
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
      Minimum Quantity
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
    <label for="tic" class="col-sm-2 control-label">
      <abbr title="Taxability Information Code">TIC</abbr>
    </label>
    <div class="col-sm-8">
      <p class="form-control-static" id="tic"
         data-bind="jeditable: item.tic, jeditableOptions: { onupdate: saveItemProperty, onblur: 'cancel' }"></p>
    </div>
  </div>

  <div class="form-group">
    <label for="barcodes" class="col-sm-2 control-label">Barcodes</label>
    <div class="col-sm-8">
      <table id="barcodes" class="table table-condensed">
        <tbody data-bind="foreach: item.barcode_list">
          <tr>
            <td><span data-bind="text: $data.code"></span></td>
            <td><span data-bind="text: $data.quantity, jeditable: $data.quantity, jeditableOptions: { onupdate: editBarcodeQuantity, onblur: 'cancel' }"></span></td>
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
  var item= itemModel.item;

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
$('#reviewed').on('click', function(ev) {
  ev.preventDefault();
  var item= itemModel.item;

  $.getJSON("api/item-update.php?callback=?",
            { item: item.id(), reviewed: item.reviewed() ? 0 : 1 },
            function (data) {
              if (data.error) {
                displayError(data);
                return;
              }
              loadItem(data.item);
            });
});
function editBarcodeQuantity(value, settings) {
  var item= itemModel.item;
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
  var item= itemModel.item;
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

$q= "SELECT vendor_item.id, vendor_item.item, vendor, company vendor_name,
            code, vendor_sku, vendor_item.name,
            retail_price, net_price, promo_price,
            special_order,
            purchase_quantity
       FROM vendor_item
       JOIN person ON vendor_item.vendor = person.id
      WHERE item = $id";

$r= $db->query($q)
  or die_query($db, $q);

$vendor_items= array();
while ($row= $r->fetch_assoc()) {
  $vendor_items[]= $row;
}
?>
<div class="panel-group" id="accordion" role="tablist" aria-multiselectable="true">
  <div class="panel panel-default">
    <div class="panel-heading" role="tab" id="vendorsHeader">
      <a class="accordion-toggle collapsed" role="button" data-toggle="collapse" href="#vendors" aria-expanded="false" aria-controls="vendors">
        <h4 class="panel-title">Vendors</h4>
      </a>
    </div>
    <div id="vendors" class="panel-collapse collapse" role="tabpanel" aria-labelledby="vendorsHeader">
<table id="vendors" class="table table-striped table-hover">
  <thead>
    <tr>
      <th class="num">#</th>
      <th>Company</th>
      <th>Code</th>
      <th>List</th>
      <th>Net</th>
      <th>Promo</th>
      <th>Sale</th>
      <th>Special?</th>
      <th>Quantity</th>
    </tr>
  </thead>
  <tfoot>
    <tr>
      <td colspan="9">
        <button class="btn btn-primary" data-bind="click: editVendorItem">
          Add Vendor Item
        </button>
      </td>
    </tr>
  </tfoot>
  <tbody data-bind="foreach: vendor_items">
    <tr data-bind="click: $parent.editVendorItem">
      <td class="num" data-bind="text: $index() + 1"></td>
      <td data-bind="text: $data.vendor_name"></td>
      <td data-bind="text: $data.code"></td>
      <td data-bind="text: amount($data.retail_price())"></td>
      <td data-bind="text: amount($data.net_price())"></td>
      <td data-bind="text: amount($data.promo_price())"></td>
      <td data-bind="text: amount($data.net_price() / 0.6) + ' - ' + amount($data.net_price() / 0.5)"></td>
      <td><i class="fa" data-bind="css: { 'fa-check-square-o': $data.special_order() == '1', 'fa-square-o': $data.special_order() == '0' }"></i></td>
      <td data-bind="text: $data.purchase_quantity"></td>
    </tr>
  </tbody>
</table>
    </div>
  </div>
  <div class="panel panel-default">
    <div class="panel-heading" role="tab" id="historyHeader">
      <a class="accordion-toggle collapsed" role="button" data-toggle="collapse" href="#history" aria-expanded="false" aria-controls="history">
        <h4 class="panel-title">History</h4>
      </a>
    </div>
    <div id="history" class="panel-collapse collapse collapsed" role="tabpanel" aria-labelledby="historyHeader">
<?

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

dump_table($db->query($q), 'RunningTotal$right');
dump_query($q);
?>
    </div>
  </div>
</div>
<?

$q= "SELECT DATE(created) AS x,
            SUM(ABS(allocated)) AS y
       FROM txn_line
       JOIN txn ON (txn_line.txn = txn.id)
      WHERE item = $id AND type = 'customer'
      GROUP BY created
      ORDER BY created";

$r= $db->query($q);

$data= "[";
while ($row= $r->fetch_assoc()) {
  $data.= "{ x: '{$row['x']}', y: {$row['y']} },";
}
$data.= "]";
?>
<div class="container">
  <div class="chart-container" style="position: relative">
   <canvas id="sales-chart"></canvas>
  </div>
</div>
<script>
$(function() {

var data= {
  datasets: [{
    label: 'Sales',
    data: <?=$data?>
  }]
};

var options= {
  legend: {
    display: false,
  },
  scales: {
    xAxes: [{
      type: 'time',
      time: {
        unit: 'day',
        stepSize: 90
      }
    }]
  }
};

var salesChart= new Chart(document.getElementById('sales-chart'), {
                               type: 'bar',
                               data: data,
                               options: options
                         });

});
</script>
<button type="button" class="btn btn-default" data-bind="click: mergeItem">
  Merge
</button>
<?


foot();
?>
<script>
var model= {
  search: '<?=ashtml($search);?>',
  all: <?=(int)$all?>,
  item: <?=json_encode($item);?>,
  vendor_items: <?=json_encode($vendor_items);?>,
  brands: [],

};

var itemModel= ko.mapping.fromJS(model);

itemModel.printBarcode= function(place, ev) {
  var item= itemModel.item.id();

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

itemModel.removeBarcode= function(place) {
  $.getJSON("api/item-barcode-delete.php?callback=?",
            { item: itemModel.item.id, code: place.code },
            function (data) {
              if (data.error) {
                displayError(data);
                return;
              }
              loadItem(data.item);
            });
}

itemModel.mergeItem= function(place) {
  var code= window.prompt("Please enter the item to merge this one into.", "");

  if (code) {
    $.getJSON("api/item-merge.php?callback=?",
              { from: itemModel.item.id, to: code },
              function (data) {
                if (data.error) {
                  displayError(data);
                  return;
                }
                loadItem(data.item);
              });
  }
}

itemModel.editVendorItem= function(item) {
  Scat.dialog('item-vendor-item').done(function (html) {
    var panel= $(html);

    var vendorItem= item.vendor_sku ? ko.mapping.toJS(item) :
                    { id: 0, vendor: 0, item: item.item.id(),
                      vendor_sku: item.item.code(), name: item.item.name(),
                      retail_price: item.item.retail_price(),
                      net_price: 0.00, promo_price: null,
                      purchase_quantity: 1};

    vendorItem.vendors= [];
    vendorItem.error= '';

    panel.on('hidden.bs.modal', function() {
      $(this).remove();
    });

    $.getJSON('api/person-list.php?callback=?',
              { role: 'vendor' })
      .done(function (data) {
        ko.mapping.fromJS({ vendors: data }, vendorItemModel);
        vendorItemModel.vendor.valueHasMutated();
      })
      .fail(function (jqxhr, textStatus, error) {
        var data= $.parseJSON(jqxhr.responseText);
        vendor_item.error(textStatus + ', ' + error + ': ' + data.text)
      });


    vendorItemModel= ko.mapping.fromJS(vendorItem);

    vendorItemModel.saveItem= function(place, ev) {
      var vendorItem= ko.mapping.toJS(vendorItemModel);
      delete vendorItem.vendors;
      delete vendorItem.error;

      Scat.api(vendorItem.id ? 'vendor-item-update' : 'vendor-item-add',
               vendorItem)
          .done(function (data) {
            $(place).closest('.modal').modal('hide');
            if (data.vendor_item) {
              for (var i= 0; i < itemModel.vendor_items().length; i++) {
                if (data.vendor_item.id === itemModel.vendor_items()[i].id()) {
                  ko.mapping.fromJS(data.vendor_item, {},
                                    itemModel.vendor_items()[i]);
                  return;
                }
              }
              itemModel.vendor_items.push(ko.mapping.fromJS(data.vendor_item));
            }
          });
    }

    vendorItemModel.selectedVendor= ko.computed({
      read: function () {
        return this.vendor();
      },
      write: function (value) {
        if (typeof value != 'undefined' && value != '') {
          this.vendor(value);
        }
      },
      owner: vendorItemModel
    }).extend({ notify: 'always' });
 

    ko.applyBindings(vendorItemModel, panel[0]);
    panel.appendTo($('body')).modal();
  });
}

ko.applyBindings(itemModel);

function loadItem(item) {
  ko.mapping.fromJS({ item: item }, itemModel);
}

function saveItemProperty(value, settings) {
  var item= itemModel.item;
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
</script>
