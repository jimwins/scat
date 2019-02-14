<?
require 'scat.php';
require 'lib/item.php';

ob_start();

$search= $_GET['search'];

if (($saved= (int)$_GET['saved']) && !$search) {
  $search= $db->get_one("SELECT search FROM saved_search WHERE id = $saved");
}

head("Items @ Scat", true);

// XXX can't add items on phone for now
?>
<div class="hidden-xs btn-group hidden-print" style="float: right">
  <button id="add-item" class="btn btn-default">Add New Item</button>
  <button type="button" class="btn btn-default dropdown-toggle"
          data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
    <span class="caret"></span>
    <span class="sr-only">Toggle Dropdown</span>
  </button>
  <ul class="dropdown-menu">
    <li><a href="#" id="add-bulk-items">Bulk Add</a></li>
  </ul>
</div>
<?include 'item-searchform.php';?>
<div id="add-item-form" class="modal fade">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h4 class="modal-title">Add New Item</h4>
      </div>
      <div class="modal-body">
        <form>
         <div class="form-group">
           <label for="code">Code</label>
           <div class="input-group">
             <input type="text" class="form-control"
                    name="code" placeholder="Code">
             <span class="input-group-btn">
               <button class="btn btn-default" type="button"
                       id="load" title="Load from Vendor">
                 <span class="fa fa-upload"></span>
               </button>
             </span>
           </div>
         </div>
         <div class="form-group">
           <label for="name">Name</label>
           <input type="text" class="form-control"
                  name="name" placeholder="Name" size="30">
         </div>
         <div class="form-group">
           <label for="retail_price">Price</label>
           <input type="text" class="form-control"
                  name="retail_price" placeholder="$0.00">
         </div>
         <input type="hidden" name="barcode" value="">
         <input type="submit" class="btn btn-primary" value="Add Item">
        </form>
      </div>
    </div>
  </div>
</div>
<script>
$('#add-item').on('click', function(ev) {
  $('#add-item-form').modal('show');
});
$('#add-item-form #load').on('click', function(ev) {
  Scat.api('vendor-item-load',
           { code: $('#add-item-form [name="code"]').val() })
      .done(function (data) {
        $('#add-item-form [name="name"]').val(data.item.name);
        $('#add-item-form [name="retail_price"]').val(data.item.retail_price);
        $('#add-item-form [name="barcode"]').val(data.item.barcode);
      });
});
$('#add-item-form form').on('submit', function(ev) {
  ev.preventDefault();
  var data= $("#add-item-form :input").serializeArray();
  Scat.api('item-add', data)
      .done(function (data) {
        window.location.href= 'item.php?id=' + data.item.id;
      });
});
$('#add-bulk-items').on('click', function(ev) {
  Scat.dialog('item-bulk-add').done(function (html) {
    var panel= $(html);

    var vendorItem= { vendor: 0, code: '' };
    vendorItem.vendors= [];
    vendorItem.error= '';

    panel.on('hidden.bs.modal', function() {
      $(this).remove();
    });

    Scat.api('person-list', { role: 'vendor' })
        .done(function (data) {
          ko.mapping.fromJS({ vendors: data }, vendorItemModel);
          vendorItemModel.vendor.valueHasMutated();
        })
        .fail(function (jqxhr, textStatus, error) {
          var data= $.parseJSON(jqxhr.responseText);
          vendor_item.error(textStatus + ', ' + error + ': ' + data.text)
        });

    vendorItemModel= ko.mapping.fromJS(vendorItem);

    vendorItemModel.addItems= function(place, ev) {
      var vendorItem= ko.mapping.toJS(vendorItemModel);
      delete vendorItem.vendors;
      delete vendorItem.error;

      Scat.api('item-add-bulk', vendorItem)
          .done(function (data) {
            $(place).closest('.modal').modal('hide');
            Scat.alert("Added " + data.items + " items.");
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
});
</script>
<br>
<?

if (!$search) {
  $q= "SELECT id, name, search FROM saved_search ORDER BY name";
  $r= $db->query($q);

  echo '<ul class="list-group">';
  while ($row= $r->fetch_assoc()) {
    echo '<li class="list-group-item">',
         '<a href="items.php?saved=', $row['id'], '" ',
            'title="', ashtml($row['search']), '">',
         ashtml($row['name']), '</a>',
         '<a class="pull-right" href="report-performance.php?saved=', $row['id'], '">',
         '<i class="far fa-chart-bar"></i></a>',
         '</li>';
  }
  echo '</ul>';

  goto end;
}

$begin= false;

$options= 0;
if ($_REQUEST['all'])
  $options|= FIND_ALL;

list($sql_criteria, $begin) = item_terms_to_sql($db, $search, $options);

$extra= "";
if (!$begin) {
  $begin= date("Y-m-d", time() - 90*24*3600);
}
if (!$_REQUEST['all'])
  $criteria[]= "(item.active AND NOT item.deleted)";

$q= "SELECT
            item.id AS meta,
            item.code Code\$item,
            item.name Name\$name,
            brand.name Brand\$brand,
            retail_price MSRP\$dollar,
            sale_price(item.retail_price, item.discount_type, item.discount)
              Sale\$dollar,
            CASE item.discount_type
              WHEN 'percentage' THEN CONCAT(ROUND(item.discount), '% off')
              WHEN 'relative' THEN CONCAT('$', item.discount, ' off')
              ELSE ''
            END Discount\$discount,
            IFNULL((SELECT SUM(allocated) FROM txn_line WHERE item = item.id), 0) Stock\$right,
            minimum_quantity Minimum\$right,
            (SELECT -1 * SUM(allocated)
               FROM txn_line JOIN txn ON (txn = txn.id)
              WHERE type = 'customer'
                AND item = item.id AND filled > NOW() - INTERVAL 3 MONTH)
            AS Last3Months\$right,
            item.active Active\$bool
       FROM item
  LEFT JOIN brand ON (item.brand = brand.id)
  LEFT JOIN barcode ON (item.id = barcode.item)
      WHERE $sql_criteria
   GROUP BY item.id
   ORDER BY (Minimum\$right > 0 OR Stock\$right > 0) DESC, 2";

$r= $db->query($q)
  or die($db->error);

if ($r->num_rows == 1) {
  $row= $r->fetch_assoc();
  ob_end_clean();
  // XXX preserve search parameters
  header("Location: item.php?id=" . $row['meta']);
  exit;
}
ob_end_flush();
?>
<div id="results">
  <?dump_table($r);?>
</div>
<div class="panel-group hidden-print" id="accordion">
  <div class="panel panel-default">
    <div class="panel-heading">
      <h4 class="panel-title">
        <a data-toggle="collapse" data-parent="#accordion"
           href="#bulk-edit-form">
          Bulk Edit
        </a>
      </h4>
    </div>
    <div id="bulk-edit-form" class="panel-collapse collapse">
      <div class="panel-body">
        <form class="form-horizontal">
          <div class="form-group">
            <label for="brand_id" class="col-sm-2 control-label control-label">
              Brand
            </label>
            <div class="col-sm-10">
              <select id="brand_id" name="brand_id" class="form-control">
                <option value=""></option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label for="product_id" class="col-sm-2 control-label control-label">
              Product
            </label>
            <div class="col-sm-10">
              <select id="product_id" name="product_id" class="form-control">
                <option value=""></option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label for="name" class="col-sm-2 control-label">
              Name
            </label>
            <div class="col-sm-10">
              <input type="text" name="name" class="form-control"
                     placeholder="Name including {{short_name}} and/or {{variation}}">
            </div>
          </div>
          <div class="form-group">
            <label for="variation" class="col-sm-2 control-label">
              Variation
            </label>
            <div class="col-sm-10">
              <input type="text" name="variation" class="form-control"
                     placeholder="">
            </div>
          </div>
          <div class="form-group">
            <label for="retail_price" class="col-sm-2 control-label">
              List
            </label>
            <div class="col-sm-10">
              <input type="text" name="retail_price" class="form-control"
                     placeholder="$0.00">
            </div>
          </div>
          <div class="form-group">
            <label for="discount" class="col-sm-2 control-label">
              Discount
            </label>
            <div class="col-sm-10">
              <input type="text" name="discount" class="form-control"
                     placeholder="$0.00 or 0%">
            </div>
          </div>
          <div class="form-group">
            <label for="minimum_quantity" class="col-sm-2 control-label">
              Minimum Quantity
            </label>
            <div class="col-sm-10">
              <input type="text" name="minimum_quantity" class="form-control"
                     placeholder="1">
            </div>
          </div>
          <div class="form-group">
            <label for="purchase_quantity" class="col-sm-2 control-label">
              Purchase Quantity
            </label>
            <div class="col-sm-10">
              <input type="text" name="purchase_quantity" class="form-control"
                     placeholder="1">
            </div>
          </div>
          <div class="form-group">
            <span class="col-sm-offset-2 col-sm-10">
              <button class="btn btn-primary">Submit</button>
            </span>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="btn-group hidden-print">
  <button type="button" class="btn btn-default"
          id="print-price-labels">
    <i class="fa fa-print"></i> Print Price Labels
  </button>
  <button type="button" class="btn btn-default dropdown-toggle"
          data-toggle="dropdown" aria-haspopup="true"
          aria-expanded="false">
    <span class="caret"></span>
    <span class="sr-only">Toggle Dropdown</span>
  </button>
  <ul class="dropdown-menu">
    <li>
      <a id="print-price-labels-brush">
        Half-size price labels
      </a>
    </li>
    <li>
      <a id="print-price-labels-trim">
        Trim name
      </a>
    </li>
    <li>
      <a id="print-price-labels-noprice">
        No price
      </a>
    </li>
  </ul>
</div>
<script>
function updateItem(item) {
  $('.' + item.id + ' .name').text(item.name);
  $('.' + item.id + ' .brand').text(item.brand);
  $('.' + item.id + ' td:nth(4)').text(amount(item.retail_price));
  $('.' + item.id + ' td:nth(5)').text(amount(item.sale_price));
  $('.' + item.id + ' .discount').text(item.discount_label);
  $('.' + item.id + ' td:nth(7)').text(item.stock);
  $('.' + item.id + ' td:nth(8)').text(item.minimum_quantity);
  var active= parseInt(item.active);
  $('.' + item.id + ' td:nth(10) i').data('truth', active);
  if (active) {
    $('.' + item.id + ' td:nth(10) i').removeClass('fa-square').addClass('fa-check-square');
  } else {
    $('.' + item.id + ' td:nth(10) i').removeClass('fa-check-square').addClass('fa-square');
  }
}
$('tbody tr .name').editable(function(value, settings) {
  var item= $(this).closest('tr').attr('class');

  Scat.api('item-update', { item: item, name: value })
      .done(function (data) {
        updateItem(data.item);
      });
  return "...";
}, { event: 'click', style: 'display: inline' });
$('tbody tr .brand').editable(function(value, settings) {
  var item= $(this).closest('tr').attr('class');

  Scat.api('item-update', { item: item, brand_id: value })
      .done(function (data) {
        updateItem(data.item);
      });
  return "...";
}, { event: 'click', style: 'display: inline', type: 'select', submit: 'OK',
loadurl: 'api/brand-list.php', placeholder: '' });
$('#brand_id').select2({
  ajax: {
    url: 'api/brand-find.php',
    dataType: 'json'
  }
});

$('#product_id').select2({
  ajax: {
    url: 'api/product-find.php',
    dataType: 'json'
  }
});

$('tbody tr td#msrp').editable(function(value, settings) {
  var item= $(this).closest('tr').attr('class');

  Scat.api('item-update', { item: item, retail_price: value })
      .done(function (data) {
        updateItem(data.item);
      });
  return "...";
}, { event: 'click', style: 'display: inline', placeholder: '', width: '5em', select: true, });
$('tbody tr .discount').editable(function(value, settings) {
  var item= $(this).closest('tr').attr('class');

  Scat.api('item-update', { item: item, discount: value })
      .done(function (data) {
        updateItem(data.item);
      });
  return "...";
}, { event: 'click', style: 'display: inline', placeholder: '', width: '6em', select: true, });
$('tbody tr td#stock').editable(function(value, settings) {
  var item= $(this).closest('tr').attr('class');

  Scat.api('item-update', { item: item, stock: value })
      .done(function (data) {
        updateItem(data.item);
      });
  return "...";
}, { event: 'click', style: 'display: inline', width: '3em', select: true });
$('tbody tr td#minimum').editable(function(value, settings) {
  var item= $(this).closest('tr').attr('class');

  Scat.api('item-update', { item: item, minimum_quantity: value })
      .done(function (data) {
        updateItem(data.item);
      });
  return "...";
}, { event: 'click', style: 'display: inline', width: '3em', select: true });
$('tbody').on('click', 'tr td#active', function(ev) {
  ev.preventDefault();
  var item= $(this).closest('tr').attr('class');
  var val= $("i", this).data('truth');

  Scat.api('item-update', { item: item, active: parseInt(val) ? 0 : 1 })
      .done(function (data) {
        updateItem(data.item);
      });
});
$('#print-price-labels').on('click', function(ev) {
  ev.preventDefault();
  var q= $('#search').val();

  Scat.printDirect('labels-price', { q: q });
});
$('#print-price-labels-noprice').on('click', function(ev) {
  ev.preventDefault();
  var q= $('#search').val();

  Scat.printDirect('labels-price', { q: q, noprice: 1 });
});
$('#print-price-labels-brush').on('click', function(ev) {
  ev.preventDefault();
  var q= $('#search').val();

  var trim= window.prompt("Please enter the part of the name to trim from the labels", "");

  if (trim) {
    Scat.printDirect('labels-price-brush', { q: q, trim: trim });
  }
});
$('#print-price-labels-trim').on('click', function(ev) {
  ev.preventDefault();
  var q= $('#search').val();

  var trim= window.prompt("Please enter the part of the name to trim from the labels", "");

  if (trim) {
    Scat.printDirect('labels-price', { q: q, trim: trim });
  }
});

$('#bulk-edit-form').on('show.bs.collapse', function () {
  $.each($("tbody tr"), function (i, row) {
    var cell= $('td:nth(0)', row);
    cell.data('num', cell.text());
    cell.html($('<input type="checkbox" checked>'));
  });
  $('thead tr th:nth(0)').html(
    $('<input type="checkbox" checked>').on('click', function(ev, place) {
      $.each($("tbody tr input[type='checkbox']"), function (i, row) {
        row.checked= ev.target.checked;
      });
    })
  );
});
$('#bulk-edit-form').on('hide.bs.collapse', function () {
  $.each($("tbody tr"), function (i, row) {
    var cell= $('td:nth(0)', row);
    cell.text(cell.data('num'));
  });
  $('thead tr th:nth(0)').text('#');
});

$('#bulk-edit-form form').on('submit', function(ev) {
  ev.preventDefault();

  var data= $(this).serializeArray();
  items= { name: 'items', value: [] };

  $.each($("tbody tr"), function (i, row) {
    if ($('input[type="checkbox"]', row).is(':checked')) {
      items.value.push(row.className);
    }
  });

  data.push(items);

  Scat.api('item-bulk-update', data, { method: 'POST' })
      .done(function (data) {
        $.each(data.items, function (i, item) {
          updateItem(item);
        });
        $('#bulk-edit-form form')[0].reset();
        $('#bulk-edit-form').collapse('hide');
      });
});
</script>
<?
dump_query($q);
?>
  <button id="download" class="btn btn-default">Download</button>
<form id="post-csv" style="display: none"
      method="post" action="api/encode-tsv.php">
  <input type="hidden" name="fn" value="items.txt">
  <textarea id="file" name="file"></textarea>
</form>
<?
end:
foot();
?>
<script>
$('#download').on('click', function(ev) {
  var tsv= "Code\tName\tBrand\tMSRP\tSale\tDiscount\tStock\tMinimum\tLast3\r\n";
  $.each($("#results tr"), function (i, row) {
    if (i > 0) {
      tsv += $('td:nth(1) a', row).text() + "\t" +
             $('td:nth(2)', row).text() + "\t" +
             $('td:nth(3)', row).text() + "\t" +
             $('td:nth(4)', row).text() + "\t" +
             $('td:nth(5)', row).text() + "\t" +
             $('td:nth(6)', row).text() + "\t" +
             $('td:nth(7)', row).text() + "\t" +
             $('td:nth(8)', row).text() + "\t" +
             $('td:nth(9)', row).text() +
             "\r\n";
    }
  });
  $("#file").val(tsv);
  $("#post-csv").submit();
});
</script>
