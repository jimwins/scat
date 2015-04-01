<?
require 'scat.php';
require 'lib/txn.php';

$id= (int)$_REQUEST['id'];

$type= $_REQUEST['type'];
$number= (int)$_REQUEST['number'];

if (!$id && $type) {
  $q= "SELECT id FROM txn
        WHERE type = '". $db->real_escape_string($type) ."'
          AND number = $number";
  $r= $db->query($q);

  if (!$r->num_rows)
      die("<h2>No such transaction found.</h2>");

  $row= $r->fetch_row();
  $id= $row[0];
}

if (!$id) die("no transaction specified.");

$txn= txn_load_full($db, $id);

if ($txn['txn']['type'] == 'customer') {
  header("Location: ./?id=" . $id);
  exit;
}

head("Transaction @ Scat", true);

?>
<form class="form-horizontal" role="form">
  <div class="form-group">
    <label for="number" class="col-sm-2 control-label">Number</label>
    <div class="col-sm-8">
      <p class="form-control-static" id="number"
         data-bind="text: txn.formatted_number"></p>
    </div>
  </div>
  <div class="form-group">
    <label for="created" class="col-sm-2 control-label">Created</label>
    <div class="col-sm-8">
      <p class="form-control-static" id="created"
         data-bind="text: txn.created"></p>
    </div>
  </div>
  <div class="form-group">
    <label for="person" class="col-sm-2 control-label">Person</label>
    <div class="col-sm-8">
      <p class="form-control-static" id="person">
        <a data-bind="text: txn.person_name, 
                      attr: { href: 'person.php?id=' + txn.person() }">
        </a>
      </p>
    </div>
  </div>
  <div class="form-group">
    <label for="ordered" class="col-sm-2 control-label">Ordered</label>
    <div class="col-sm-8">
      <p class="form-control-static" id="ordered"
         data-bind="text: txn.ordered"></p>
    </div>
  </div>
  <div class="form-group">
    <label for="allocated" class="col-sm-2 control-label">Allocated</label>
    <div class="col-sm-8">
      <span class="form-control-static" id="allocated"
         data-bind="text: txn.allocated"></span>
      <button class="btn btn-default btn-sm"
         data-bind="visible: txn.ordered() != txn.allocated(),
                    click: allocateAll">
        Allocate
      </button>
      <button class="btn btn-default btn-sm"
         data-bind="visible: txn.ordered() != txn.allocated(),
                    click: closeAll">
        Close
      </button>
      <button class="btn btn-default btn-sm"
         data-bind="visible: txn.ordered() == txn.allocated(),
                    click: openAll">
        Unallocate
      </button>
    </div>
  </div>
</form>

<table class="table table-striped table-condensed"
       data-bind="visible: items()">
  <thead>
    <tr>
      <th class="num">#</th>
      <th>Code</th>
      <th>Name</th>
      <th>Price</th>
      <th>Discount</th>
      <th>Ordered</th>
      <th>Allocated</th>
    </tr>
  </thead>
  <tbody data-bind="foreach: items">
    <tr>
      <td class="num"><span data-bind="text: $index() + 1"></span></td>
      <td>
        <a data-bind="text: $data.code,
                      attr: { href: 'item.php?code=' + $data.code() }">
        </a>
      </td>
      <td><span data-bind="text: $data.name"></span></td>
      <td><span data-bind="text: amount($data.price())"></span></td>
      <td><span data-bind="text: $data.discount"></span></td>
      <td>
        <span id="quantity" data-bind="jeditable: $data.quantity, jeditableOptions: { onupdate: $parent.updateLine, line: $data, onblur: 'cancel', width: '3em', select: true }"></span>
      </td>
      <td>
        <span id="allocated" data-bind="jeditable: $data.allocated, jeditableOptions: { onupdate: $parent.updateLine, line: $data, onblur: 'cancel', width: '3em', select: true }"></span>
        <button class="btn btn-default btn-xs"
           data-bind="visible: $data.quantity() != $data.allocated(),
                      click: $parent.allocateLine">
          Allocate
        </button>
        <button class="btn btn-default btn-xs"
           data-bind="visible: $data.quantity() != $data.allocated(),
                      click: $parent.closeLine">
          Close
        </button>
      </td>
    </tr>
  </tbody>
</table>
<script>
var model= <?=json_encode($txn)?>;

var viewModel= ko.mapping.fromJS(model);

viewModel.allocateAll= function() {
  $.getJSON("api/txn-allocate.php?callback=?",
            { txn: viewModel.txn.id() },
            function (data) {
              if (data.error) {
                displayError(data);
                return;
              }
              ko.mapping.fromJS({ txn: data.txn, items: data.items },
                                viewModel);
            });
}

viewModel.allocateLine= function(line) {
  $.getJSON("api/txn-allocate.php?callback=?",
            { txn: viewModel.txn.id(), line: line.line_id() },
            function (data) {
              if (data.error) {
                displayError(data);
                return;
              }
              ko.mapping.fromJS({ txn: data.txn, items: data.items },
                                viewModel);
            });
}

viewModel.closeAll= function() {
  $.getJSON("api/txn-close.php?callback=?",
            { txn: viewModel.txn.id() },
            function (data) {
              if (data.error) {
                displayError(data);
                return;
              }
              ko.mapping.fromJS({ txn: data.txn, items: data.items },
                                viewModel);
            });
}

viewModel.closeLine= function(line) {
  $.getJSON("api/txn-close.php?callback=?",
            { txn: viewModel.txn.id(), line: line.line_id() },
            function (data) {
              if (data.error) {
                displayError(data);
                return;
              }
              ko.mapping.fromJS({ txn: data.txn, items: data.items },
                                viewModel);
            });
}

viewModel.openAll= function() {
  $.getJSON("api/txn-open.php?callback=?",
            { txn: viewModel.txn.id() },
            function (data) {
              if (data.error) {
                displayError(data);
                return;
              }
              ko.mapping.fromJS({ txn: data.txn, items: data.items },
                                viewModel);
            });
}

viewModel.updateLine= function (value, settings) {
  var data= { txn: viewModel.txn.id(), id: settings.line.line_id() };
  data[this.id]= value;

  $.getJSON("api/txn-update-item.php?callback=?",
            data,
            function (data) {
              if (data.error) {
                displayError(data);
                return;
              }
              ko.mapping.fromJS({ txn: data.txn, items: data.items },
                                viewModel);
            });
  return value;
}

ko.applyBindings(viewModel);
</script>
<?
if ($txn['txn']['type'] == 'vendor') {
?>
<div id="upload-status" class="modal fade">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h4 class="modal-title">Results</h4>
      </div>
      <div class="modal-body">
      </div>
    </div>
  </div>
</div>
<script>
$("body").html5Uploader({
  name: 'src',
  postUrl: 'api/txn-upload-items.php?txn=<?=$id?>',
  onSuccess: function(e, file, response) {
    data= $.parseJSON(response);
    if (data.error) {
      displayError(data);
      return;
    }
    ko.mapping.fromJS({ txn: data.txn, items: data.items },
                      viewModel);
    $('#upload-status .modal-body').append(data.result);
    $('#upload-status').modal('show');
  },
  onServerError: function(e, file) {
    alert("File upload failed.");
  },
});
</script>
<?
}

if ($txn['meta'] == 'customer') {
?>
<button id="receipt" class="btn btn-default">Print Receipt</button>
<script>
$("#receipt").on('click', function() {
  var lpr= $('<iframe id="receipt" src="print/receipt.php?print=1&amp;id=<?=$id?>"></iframe>').hide();
  $(this).children("#receipt").remove();
  $(this).append(lpr);
});
</script>
<?
}

if ($txn['txn']['type'] == 'vendor') {
?>
<button id="print-product-labels" class="btn btn-default">
  Print Product Labels
</button>
<script>
$("#print-product-labels").on('click', function() {
  var lpr= $('<iframe id="receipt" src="print/labels-product.php?print=1&amp;id=<?=$id?>"></iframe>').hide();
  $(this).children("#receipt").remove();
  $(this).append(lpr);
  lpr.on('load', function (ev) {
    window.frames['receipt'].print()
  });
});
</script>
<button id="export-order" class="btn btn-default">
  Export Order
</button>
<script>
$("#export-order").on('click', function() {
  window.location.href= 'export/txn.php?dl=1&id=<?=$id?>';
});
</script>
<?
}

function charge_record($row) {
  return '<a href="print/charge-record.php?id=' . $row[0] . '">Charge Record</a>';
}

if (preg_match('/customer/', $txn['Number$txn'])) {
  echo '<h2>Payments</h2>';
  $q= "SELECT id AS meta,
              processed AS Date,
              method AS Method\$payment,
              amount AS Amount\$dollar
         FROM payment
        WHERE txn = $id
        ORDER BY processed ASC";
  dump_table($db->query($q), 'charge_record$html');
  dump_query($q);
}

echo '<h2>Notes</h2>';
$q= "SELECT id AS meta,
            entered AS Date,
            content AS Note
       FROM txn_note
      WHERE txn = $id
      ORDER BY entered ASC";
dump_table($db->query($q));
dump_query($q);

foot();
