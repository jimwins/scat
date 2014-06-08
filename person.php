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
    <input id="focus" name="search"
           type="text" class="form-control" size="60"
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
  <div class="form-group">
    <label for="payment" class="col-sm-2 control-label">Payment</label>
    <div class="col-sm-8">
      <button id="attach-payment" type="button" class="btn btn-default"
              data-loading-text="Processing..."
              data-bind="click: attachPaymentCard,
                         text: person.payment_account_id() ?
                                 'Update Credit Card' : 'Attach Credit Card'">
        Attach Credit Card
      </button>
      <button id="remove-payment" type="button" class="btn btn-danger"
              data-loading-text="Processing..."
              data-bind="click: removePaymentCard,
                         visible: person.payment_account_id()">
        Remove Credit Card
      </button>
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
$q= "SELECT meta, Number\$txn, Created\$date,
            Ordered, Allocated,
            CAST(ROUND_TO_EVEN(taxed * (1 + tax_rate / 100), 2) + untaxed
                 AS DECIMAL(9,2))
            Total\$dollar,
            Paid\$dollar
      FROM (SELECT
            txn.type AS meta,
            CONCAT(txn.id, '|', type, '|', txn.number) AS Number\$txn,
            txn.created AS Created\$date,
            CONCAT(txn.person, '|', IFNULL(person.company,''),
                   '|', IFNULL(person.name,''))
              AS Person\$person,
            SUM(ordered) * IF(txn.type = 'customer', -1, 1) AS Ordered,
            SUM(allocated) * IF(txn.type = 'customer', -1, 1) AS Allocated,
            CAST(ROUND_TO_EVEN(
              SUM(IF(txn_line.taxfree, 1, 0) *
                IF(type = 'customer', -1, 1) * allocated *
                CASE discount_type
                  WHEN 'percentage' THEN retail_price * ((100 - discount) / 100)
                  WHEN 'relative' THEN (retail_price - discount) 
                  WHEN 'fixed' THEN (discount)
                  ELSE retail_price
                END),
              2) AS DECIMAL(9,2))
            untaxed,
            CAST(ROUND_TO_EVEN(
              SUM(IF(txn_line.taxfree, 0, 1) *
                IF(type = 'customer', -1, 1) * allocated *
                CASE discount_type
                  WHEN 'percentage' THEN retail_price * ((100 - discount) / 100)
                  WHEN 'relative' THEN (retail_price - discount) 
                  WHEN 'fixed' THEN (discount)
                  ELSE retail_price
                END),
              2) AS DECIMAL(9,2))
            taxed,
            tax_rate,
            CAST((SELECT SUM(amount) FROM payment WHERE txn.id = payment.txn)
                 AS DECIMAL(9,2)) AS Paid\$dollar
       FROM txn
       LEFT JOIN txn_line ON (txn.id = txn_line.txn)
       LEFT JOIN person ON (txn.person = person.id)
      WHERE person = $id
      GROUP BY txn.id
      ORDER BY created DESC
      LIMIT 50) t";

$r= $db->query($q);

if ($r->num_rows) {
  while ($row= $r->fetch_row()) {
    $activity[]= $row;
  }
}

end:

foot();
?>
<script>
var model= {
  search: '<?=ashtml($search);?>',
  all: <?=(int)$all?>,
  person: <?=json_encode($person);?>,
  activity: <?=json_encode($activity);?>,
  people: <?=json_encode($people);?>,
};

var viewModel= ko.mapping.fromJS(model);

// ghetto change tracking
viewModel.saved= ko.observable(ko.toJSON(viewModel.person));
viewModel.changed= ko.computed(function() {
  return ko.toJSON(viewModel.person) != viewModel.saved();
});

ko.applyBindings(viewModel);

function attachPaymentCard(place, ev) {
  $(ev.target).button('loading');
  $.getJSON("api/cc-attach-begin.php?callback=?",
            { person: place.person.id(),
              payment_account_id: place.person.payment_account_id() },
            function (data) {
              if (data.error) {
                displayError(data);
              } else {
                $('#modal').remove();
                var modal= $('<div class="modal fade" data-backdrop="static" data-keyboard="false" id="modal" role="dialog"><div class="modal-dialog" style="width: 660px"><div class="modal-content"><div class="modal-header"><h4 class="modal-title" id="myModalLabel">Attach Payment Card</h4></div><div class="modal-body"><iframe src="' + data.url + '" height=500" width="600" style="border:0"><div class="modal-footer"></div></div></div></div>');
                modal.appendTo('body').modal('show');
              }
            });
}

function finishAttachPayment() {
  $('#modal').modal('hide')
             .on('hidden.bs.modal', function() { $(this).remove(); });
  $('#attach-payment').button('reset');
}

function removePaymentCard(place, ev) {
  $(ev.target).button('loading');
  $.getJSON("api/cc-attach-remove.php?callback=?",
            { person: place.person.id() },
            function (data) {
              if (data.error) {
                displayError(data);
              } else {
                $('#remove-payment').button('reset');
                loadPerson(data.person);
              }
            });
}

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
  var desc= { correction: 'Correction',
              drawer: 'Till Count',
              customer: 'Invoice',
              vendor: 'Purchase Order' };
  return '<a href="txn.php?id=' + m[0] + '">'
         + desc[m[1]] + ' ' + m[2]
         + '</a>';
}
</script>
