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
  $search= $db->escape($search);

  $active= $_REQUEST['all'] ? "" : 'AND active';

  $q= "SELECT IF(deleted, 'deleted', '') AS meta,
              id, name, company
         FROM person
        WHERE (name like '%$search%' OR
               company LIKE '%$search%' OR
               email like '%$search%')
              $active
        ORDER BY CONCAT(name, company)";

  $r= $db->query($q)
    or die($db->error);

  if ($r->num_rows > 1) {
    while ($row= $r->fetch_row())
      $people[]= $row;
  } else {
    $person= $r->fetch_assoc();
    $id= (int)$person['id'];
  }
}
?>
<table class="table table-condensed table-striped table-hover"
       data-bind="if: people().length">
 <thead>
  <tr>
    <th>#</th>
    <th>Name</th>
    <th>Company</th>
  </tr>
 </thead>
 <tbody data-bind="foreach: { data: people, as: 'item' }">
  <tr>
   <td><a data-bind="text: $index() + 1,
                     attr: { href: '?id=' + item[1] }"></a></td>
   <td><a data-bind="text: item[2], attr: { href: '?id=' + item[1] }"></a></td>
   <td><a data-bind="text: item[3], attr: { href: '?id=' + item[1] }"></a></td>
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
  <div class="form-group">
    <label for="company" class="col-sm-2 control-label">Company</label>
    <div class="col-sm-8">
      <input type="text" class="form-control" id="company" placeholder="Company"
             data-bind="value: person.company">
    </div>
  </div>
  <div class="form-group">
    <label for="email" class="col-sm-2 control-label">Email</label>
    <div class="col-sm-8">
      <input type="text" class="form-control" id="email" placeholder="Email"
             data-bind="value: person.email">
    </div>
  </div>
  <div class="form-group">
    <label for="phone" class="col-sm-2 control-label">Phone</label>
    <div class="col-sm-8">
      <input type="text" class="form-control" id="phone" placeholder="Phone"
             data-bind="value: person.phone">
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
    .fail(function (jqhxr, textStatus, error) {
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
