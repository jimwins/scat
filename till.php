<?
include 'scat.php';

head("Till @ Scat", true);
?>
<div class="col-sm-6">
  <form class="form-horizontal" data-bind="submit: saveCount">
    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">Till Count</h3>
      </div>
      <div class="panel-body">
        <div class="form-group">
          <label for="expected" class="col-sm-2 control-label">Expected</label>
          <div class="col-sm-10">
            <input type="text" class="form-control" disabled
                   id="expected" data-bind="value: amount(expected())">
          </div>
        </div>
        <div class="form-group">
          <label for="counted" class="col-sm-2 control-label">Counted</label>
          <div class="col-sm-10">
            <input type="text" class="form-control"
                   id="counted" name="count" data-bind="value: current">
          </div>
        </div>
        <div class="form-group"
             data-bind="css: { 'has-error' : overshort() < 0 }">
          <label for="overshort" class="col-sm-2 control-label">
            Over/(Short)
          </label>
          <div class="col-sm-10">
            <input type="text" class="form-control" disabled
                   id="overshort" data-bind="value: amount(overshort())">
          </div>
        </div>
        <div class="form-group">
          <label for="withdraw" class="col-sm-2 control-label">
            Withdrawal
          </label>
          <div class="col-sm-10">
            <input type="text" class="form-control"
                   id="withdraw" name="withdrawal" data-bind="value: withdraw"
                   placeholder="$0.00">
          </div>
        </div>
        <div class="form-group"
             data-bind="css: { 'has-error' : remaining() < 0 }">
          <label for="remaining" class="col-sm-2 control-label">
            Remaining
          </label>
          <div class="col-sm-10">
            <input type="text" class="form-control" disabled
                   id="remaining" data-bind="value: amount(remaining())">
          </div>
        </div>
        <div data-bind="visible: checks, text: checks_pending"></div>
      </div>
      <div class="panel-footer">
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </div>
  </form>

  <form class="form-horizontal" data-bind="submit: withdrawPettyCash">
    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">Petty Cash</h3>
      </div>
      <div class="panel-body form-horizontal">
        <div class="form-group">
          <label for="withdraw" class="col-sm-2 control-label">
            Withdrawal
          </label>
          <div class="col-sm-10">
            <input type="text" class="form-control"
                   id="withdraw" name="withdrawal" data-bind="value: withdraw"
                   placeholder="$0.00">
          </div>
        </div>
        <div class="form-group">
          <label for="reason" class="col-sm-2 control-label">
            Reason
          </label>
          <div class="col-sm-10">
            <input type="text" class="form-control"
                   id="withdraw" name="withdrawal" data-bind="value: reason"
                   placeholder="Reason for withdrawing petty cash.">
          </div>
        </div>
      </div>
      <div class="panel-footer">
        <button type="submit" class="btn btn-primary">
          Process
        </button>
      </div>
    </div>
  </form>
</div>

<div class="col-sm-5">
  <form class="form" data-bind="submit: printChange">
    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">Change Order</h3>
      </div>
      <table class="table">
        <thead>
          <tr>
            <th class="col-sm-3">Quantity</th>
            <th class="col-sm-6">Type</th>
            <th class="col-sm-3">Total</th>
          </tr>
        </thead>
        <tbody data-bind="foreach: changeTypes">
          <tr>
            <td>
              <input type="number" size="4" class="form-control"
                     data-bind="attr: { step: $data.step },
                                value: $data.quantity">
            </td>
            <td data-bind="text: $data.label">$5 bills</td>
            <td data-bind="text: amount($data.quantity() * $data.value)">
              $0.00
            </td>
          </tr>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="2" align="right">Total:</td>
            <td data-bind="text: amount(totalChange)"></td>
          </tr>
        </tfoot>
      </table>
      <div class="panel-footer">
        <button type="submit" class="btn btn-primary">
          Print
        </button>
      </div>
    </div>
  </form>
</div>
<script>
var tillModel= function() {
  var self= this;

  self.expected= ko.observable(0);
  self.current= ko.observable(0);
  self.withdraw= ko.observable(null);
  self.checks= ko.observable(0);
  self.reason= ko.observable('');

  self.overshort= ko.computed(function() {
    return (self.current() - self.expected()).toFixed(2);
  });

  self.remaining= ko.computed(function() {
    return (self.current() - self.withdraw()).toFixed(2);
  });

  self.checks_pending= ko.computed(function() {
    return "(" + self.checks() + " check" + ((self.checks() > 1) ? 's' : '')
           + " pending)";
  });

  self.saveCount= function (place, ev) {
    Scat.api('till-count', { count: self.current(),
                             withdrawal: self.withdraw() })
        .done(function (data) {
          self.reset();
          self.printDepositSlip(data.txn_id);
        });
  }

  self.printDepositSlip= function(txn_id) {
    Scat.print('deposit-slip', { id: txn_id });
  }

  self.changeTypes= ko.observableArray([
   { name: 'fives', label: '$5 bills', value: 5.00, step: 20, quantity: ko.observable(0) },
   { name: 'ones', label: '$1 bills', value: 1.00, step: 25, quantity: ko.observable(0) },
   { name: 'quarters', label: 'Rolls of Quarters', value: 10.00, step: 1, quantity: ko.observable(0) },
   { name: 'dimes', label: 'Rolls of Dimes', value: 5.00, step: 1, quantity: ko.observable(0) },
   { name: 'nickels', label: 'Rolls of Nickels', value: 2.00, step: 1, quantity: ko.observable(0) },
   { name: 'pennies', label: 'Rolls of Pennies', value: 0.50, step: 1, quantity: ko.observable(0) },
  ]);

  self.totalChange= ko.computed(function() {
    return self.changeTypes().reduce(function (sum, cur) {
                                     return sum + (cur.quantity() * cur.value)
                                   }, 0);
  });

  self.printChange= function (place, ev) {
    var types= self.changeTypes().filter(function (el) {
                                    return el.quantity() > 0
                                 })
                                 .map(function (el) {
                                   return {
                                     quantity: el.quantity(),
                                     label: el.label,
                                     total: (el.quantity() * el.value)
                                   };
                                 });

    Scat.print('change-order', { types: types });
  };

  self.withdrawPettyCash= function (place, ev) {
    Scat.api('till-withdraw', { reason: self.reason(),
                                amount: self.withdraw() })
        .done(function (data) {
          Scat.alert(data.message);
          self.reset();
        });
  }

  self.reset= function () {
    Scat.api('till-load')
        .done(function (data) {
          self.expected(data.current);
          self.checks(data.checks);
          /* Reset inputs */
          self.current(self.expected());
          self.withdraw(null);
          self.reason(null);
        });
  }
};

var till= new tillModel();
ko.applyBindings(till);
till.reset();
</script>
<?
foot();
