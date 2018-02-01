<?
require 'scat.php';

head('Gift Cards', true);
?>
<div class="row">
  <div class="col-sm-6">
    <form data-bind="submit: checkGiftCard">
      <div class="input-group">
        <input type="text" class="autofocus form-control"
               data-bind="value: card"
               placeholder="Scan or enter card">
        </input>
        <span class="input-group-btn">
          <button type="submit" class="btn btn-primary">
            Check Card
          </button>
        </span>
      </div>
    </form>
  </div>

  <div class="col-sm-6">
    <div class="input-group input-daterange" id="datepicker">
      <input type="text" class="form-control"
             data-bind="value: expires"
             placeholder="Expiration date (optional)">
      <span class="input-group-btn">
        <button type="button" class="btn btn-success"
                data-bind="click: createGiftCard">
          Create Card
        </button>
      </span>
    </div>
  </div>

</div>

<br>

<div class="panel panel-default" data-bind="visible: id() != ''">
  <div class="panel-heading">
    <h3 class="panel-title">
      Gift Card
    </h3>
  </div>
  <table class="table table-striped">
    <thead>
      <tr>
        <th>Date</th>
        <th>Amount</th>
      </tr>
    </thead>
    <tfoot>
      <tr>
        <td align="right">
          <strong>Balance:</strong>
        </td>
        <td data-bind="text: amount(balance)">
        </td>
      </tr>
    </tfoot>
    <tbody data-bind="foreach: history">
      <tr>
        <td data-bind="text: $data.entered"></td>
        <td data-bind="text: Scat.amount($data.amount)"></td>
      </tr>
    </tbody>
  </table>
  <div class="panel-footer">
    <div class="col-sm-1">
      <button type="button" class="btn btn-primary"
              data-bind="click: printGiftCard, disable: balance() <= 0">
        Print
      </button>
    </div>
    <div class="input-group col-sm-4">
      <input type="text" class="form-control"
             data-bind="value: updateAmount"
             placeholder="$0.00">
      <div class="input-group-btn">
        <button type="button" class="btn btn-default"
                data-action="add" data-bind="click: updateGiftCard">
          Add
        </button>
        <button type="button" class="btn btn-default"
                data-action="spend" data-bind="click: updateGiftCard">
          Spend
        </button>
      </div>
    </div>
  </div>
</div>

<script>
$(function() {

  var model= {
    card: '', id: '',
    expires: '', active: 0,
    balance: 0, latest: '', history: [],
    updateAmount: '',
  };

  var viewModel= ko.mapping.fromJS(model);

  viewModel.checkGiftCard= function() {
    Scat.api('giftcard-check-balance', { card: this.card() })
        .done(function (data) {
                ko.mapping.fromJS(data, viewModel);
              });
  }

  viewModel.createGiftCard= function() {
    Scat.api('giftcard-create', { expires: this.expires() })
        .done(function (data) {
                ko.mapping.fromJS(data, viewModel);
              });
  }

  viewModel.printGiftCard= function() {
    var lpr= $('<iframe id="giftcard-print" src="print/gift-card.php?card=' +
               this.card() + '&amp;balance=' + this.balance() +
               '&amp;issued=' + this.latest() + '"></iframe>').hide();
    $("#giftcard-print").remove();
    lpr.on("load", function() {
      this.contentWindow.print();
    });
    $('body').append(lpr);
  }

  viewModel.updateGiftCard= function(place, ev) {
    var action= $(ev.target).data('action');
    Scat.api('giftcard-add-txn', { card: this.card(),
                                   amount: (action == 'add' ? 1 : -1) *
                                           this.updateAmount() })
        .done(function (data) {
                ko.mapping.fromJS(data, viewModel);
                viewModel.updateAmount('');
              });
  }

  ko.applyBindings(viewModel, document.getElementById('scat-page'));

  $('#datepicker').datepicker({
      format: "yyyy-mm-dd",
      todayHighlight: true
  });

});
</script>
<?
foot();
