<?
require 'scat.php';
require 'lib/person.php';

head("Person @ Scat", true);

$id= (int)$_REQUEST['id'];
$search= $_REQUEST['search'];

?>
<form class="col-sm-10" role="form" method="get" action="person.php">
  <div class="input-group">
    <span class="input-group-btn">
      <input type="submit" class="btn btn-primary" value="Search">
    </span>
    <input name="search"
           type="text" class="autofocus form-control" size="60"
           autocomplete="off" autocorrect="off" autocapitalize="off"
           data-bind="value: search"
           placeholder="Name">
    <span class="input-group-addon">
      <label><input type="checkbox" value="1" name="all" data-bind="checked: all"> Include inactive?</label>
    </span>
  </div>
</form>
<br>
<br>
<?

$person= array();
$activity= array();
$people= array();

if (!empty($search)) {
  $people= person_find($db, $search);

  if (count($people) == 1) {
    $person= $people[0];
    $id= (int)$person['id'];
  }
}
?>
<table class="table table-condensed table-striped table-hover"
       data-bind="if: people().length && !person.id">
 <thead>
  <tr>
    <th>#</th>
    <th>Name</th>
    <th>Company</th>
    <th>Phone</th>
  </tr>
 </thead>
 <tbody data-bind="foreach: people">
  <tr data-bind="click: function(d, e) { window.location.href= 'person.php?id=' + $data.id() }">
   <td data-bind="text: $index() + 1"></td>
   <td data-bind="text: $data.name"></td>
   <td data-bind="text: $data.company"></td>
   <td data-bind="text: $data.pretty_phone"></td>
  </tr>
 </tbody>
</table>
<?

if (!$id)
  goto end;

$person= person_load($db, $id);
?>
<form class="form-horizontal" role="form"
      data-bind="submit: savePerson">
  <div class="form-group">
    <label for="name" class="col-sm-2 control-label">Name</label>
    <div class="col-sm-8">
      <input type="text" class="form-control" id="name" placeholder="Name"
             data-bind="value: person.name">
    </div>
  </div>
  <div class="form-group">
    <label for="role" class="col-sm-2 control-label">Role</label>
    <div class="col-sm-8">
      <label class="checkbox-inline">
        <input type="radio" value="customer"
               data-bind="checked: person.role"> Customer
      </label>
      <label class="checkbox-inline">
        <input type="radio" value="employee"
               data-bind="checked: person.role"> Employee
      </label>
      <label class="checkbox-inline">
        <input type="radio" value="vendor"
               data-bind="checked: person.role"> Vendor
      </label>
    </div>
  </div>
  <div class="form-group" data-bind="if: person.role() != 'vendor'">
    <label for="points" class="col-sm-2 control-label">Points</label>
    <div class="col-sm-4" data-bind="if: person.points_available()">
      <p class="form-control-static"
         data-bind="text: 'Available: ' + person.points_available()">
      </p>
    </div>
    <div class="col-sm-4" data-bind="if: person.points_pending()">
      <p class="form-control-static"
         data-bind="text: 'Pending: ' + person.points_pending().toString()">
      </p>
    </div>
  </div>
  <div class="form-group">
    <label for="company" class="col-sm-2 control-label">Company</label>
    <div class="col-sm-8">
      <input type="text" class="form-control" id="company" placeholder="Company"
             data-bind="value: person.company">
    </div>
  </div>
  <div class="form-group">
    <label for="email" class="col-sm-2 control-label">Email</label>
    <div class="col-sm-6">
      <input type="text" class="form-control" id="email" placeholder="Email"
             data-bind="value: person.email">
    </div>
    <div class="col-sm-4 checkbox disabled">
      <label>
        Email OK?
        <input type="checkbox" id="email_ok" data-bind="value: person.email_ok" disabled>
      </label>
    </div>
  </div>
  <div class="form-group">
    <label for="phone" class="col-sm-2 control-label">Phone</label>
    <div class="col-sm-6">
      <input type="text" class="form-control" id="phone" placeholder="Phone"
             data-bind="value: person.phone">
    </div>
    <div class="col-sm-4 checkbox disabled">
      <label>
        SMS OK?
        <input type="checkbox" id="sms_ok" data-bind="value: person.sms_ok" disabled>
      </label>
    </div>
  </div>
  <div class="form-group">
    <label for="address" class="col-sm-2 control-label">Address</label>
    <div class="col-sm-8">
      <textarea class="form-control" id="address" placeholder="Address"
             data-bind="value: person.address"></textarea>
    </div>
  </div>
  <div class="form-group">
    <label for="notes" class="col-sm-2 control-label">Notes</label>
    <div class="col-sm-8">
      <textarea class="form-control" id="notes" placeholder="Notes"
             data-bind="value: person.notes"></textarea>
    </div>
  </div>
  <div class="form-group">
    <label for="tax_id" class="col-sm-2 control-label">Tax ID</label>
    <div class="col-sm-8">
      <input type="text" class="form-control" id="tax_id" placeholder="Tax ID"
             data-bind="value: person.tax_id">
    </div>
  </div>

  <div class="form-group" data-bind="visible: changed">
    <div class="col-sm-offset-2 col-sm-8">
      <button type="submit" class="btn btn-primary"
              data-loading-text="Processing...">
        Save
      </button>
    </div>
  </div>
</form>

<h2>
  Activity
  <button class="btn btn-default"
          data-loading-text="Processing..."
          data-bind="click: createPurchaseOrder,
                     visible: person.role() == 'vendor'">
    Create Purchase Order
  </button>
  <button class="btn btn-default"
          data-loading-text="Processing..."
          data-bind="click: reorder,
                     visible: person.role() == 'vendor'">
    Reorder
  </button>
  <button class="btn btn-default"
          id="upload-items"
          data-loading-text="Processing..."
          data-bind="click: uploadItems, visible: person.role() == 'vendor'">
    Upload Items
  </Button>
</h2>

<div data-bind="visible: loading()" class="progress progress-striped active" style="height: 1.5em">
  <div class="progress-bar" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%;">
    Loading&hellip;.
  </div>
</div>

<nav data-bind="if: !loading()">
  <ul class="pager">
    <li class="previous" data-bind="css: { disabled: activity_page() == 0 }">
      <a href="#" data-bind="click: function(data, event) { loadActivity(person.id(), activity_page() - 1) }"><span aria-hidden="true">&larr;</span> Newer</a>
    </li>

    <span>
      Page <!--ko text: activity_page() + 1 --><!--/ko--> of ?
    </span>

    <li class="next" data-bind="css: { enabled: !loading() }">
      <a href="#" data-bind="click: function(data, event) { loadActivity(person.id(), activity_page() + 1) }">Older <span aria-hidden="true">&rarr;</span></a>
    </li>
  </ul>
</li>

<table class="table table-condensed table-striped"
       data-bind="if: activity().length">
 <thead>
  <tr>
    <th>#</th>
    <th>Number</th>
    <th>Created</th>
    <th>Ordered</th>
    <th>Allocated</th>
    <th class="text-right">Total</th>
    <th class="text-right">Paid</th>
  </tr>
 </thead>
 <tbody data-bind="foreach: { data: activity, as: 'action' }">
  <tr>
   <td data-bind="text: $index() + 1"></td>
   <td data-bind="html: linkTransaction(action[1])"></td>
   <td data-bind="text: action[2]"></td>
   <td data-bind="text: action[3]"></td>
   <td data-bind="text: action[4]"></td>
   <td data-bind="text: amount(action[5])" class="text-right"></td>
   <td data-bind="text: amount(action[6])" class="text-right"></td>
  </tr>
 </tbody>
</table>

<?

end:

foot();
?>
<script>
var model= {
  search: '<?=ashtml($search);?>',
  all: <?=(int)$all?>,
  person: <?=json_encode($person);?>,
  activity: [],
  activity_page: 0,
  loading: 1,
  people: <?=json_encode($people);?>,
};

var viewModel= ko.mapping.fromJS(model);

// ghetto change tracking
viewModel.saved= ko.observable(ko.toJSON(viewModel.person));
viewModel.changed= ko.computed(function() {
  return ko.toJSON(viewModel.person) != viewModel.saved();
});

ko.applyBindings(viewModel);

function loadActivity(person, page) {
  viewModel.loading(1);
  $.getJSON('api/person-load-activity.php?callback=?',
            { person: person, page: page })
    .done(function (data) {
      ko.mapping.fromJS(data, viewModel);
      viewModel.loading(0);
    })
    .fail(function (jqxhr, textStatus, error) {
      viewModel.loading(0);
      var data= $.parseJSON(jqxhr.responseText);
      alert(textStatus + ', ' + error + ': ' + data.text);
    });
}

loadActivity(<?=$id?>, 0);

function loadPerson(person) {
  ko.mapping.fromJS({ person: person }, viewModel);
  viewModel.saved(ko.toJSON(viewModel.person));
}

function savePerson(place) {
  $.getJSON("api/person-update.php?callback=?",
            ko.mapping.toJS(viewModel.person),
            function (data) {
              if (data.error) {
                displayError(data);
                return;
              }
              loadPerson(data.person);
            });
}

function createPurchaseOrder(place, ev) {
  $(ev.target).button('loading');
  $.getJSON("api/txn-create.php?callback=?",
            { type: 'vendor', person: place.person.id() },
            function (data) {
              if (data.error) {
                displayError(data);
              }
              window.location= 'txn.php?id=' + data.txn.id;
            });
}

function reorder(place, ev) {
  $(ev.target).button('loading');
  window.location= 'reorder.php?vendor=' + place.person.id();
}

function uploadItems(place, ev) {
  displayError("Just drag & drop a file on this window.<br><br>File format: item_no, sku, name, retail_price, net_price, promo_price, barcode, purchase_quantity");
}

$("body").html5Uploader({
  name: 'src',
  postUrl: 'api/vendor-upload.php?vendor=<?=$id?>',
  onClientLoadStart: function (e, file, response) {
    $('#upload-items')
      .html('<i class="fa fa-spinner fa-spin"></i> Reading...');
  },
  onServerLoadStart: function (e, file, response) {
    $('#upload-items')
      .html('<i class="fa fa-spinner fa-spin"></i> Uploading...');
  },
  onServerLoad: function (e, file, response) {
    $('#upload-items')
      .html('<i class="fa fa-spinner fa-spin"></i> Processing...');
  },
  onSuccess: function(e, file, response) {
    data= $.parseJSON(response);
    if (data.error) {
      displayError(data);
      return;
    }
    displayError({ title: "Upload Successful", error: data.result });
    $('#upload-items')
      .html('Upload Items');
  },
  onServerError: function(e, file) {
    displayError("File upload failed.");
    $('#upload-items')
      .html($('Upload Items'));
  },
});
$('body').on('dragbetterenter', function () {
  $('#upload-items').addClass("active btn-success");
});
$('body').on('dragbetterleave', function () {
  $('#upload-items').removeClass("active btn-success");
});

function linkTransaction(components) {
  var m= components.split(/\|/);
  var base= { correction: 'txn.php',
              drawer: 'txn.php',
              customer: './',
              vendor: './' };
  var desc= { correction: 'Correction',
              drawer: 'Till Count',
              customer: 'Invoice',
              vendor: 'Purchase Order' };
  return '<a href="' + base[m[1]] + '?id=' + m[0] + '">'
         + desc[m[1]] + ' ' + m[2]
         + '</a>';
}
</script>
