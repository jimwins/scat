<?
require 'scat.php';
require 'lib/txn.php';

head("Scat");
?>
<style>
.choices {
  max-height: 300px;
  overflow: scroll;
}

.choices tr.stocked {
  color: #339;
}

.choices tr {
  cursor:pointer;
}
.choices tr:hover {
  text-decoration: underline;
}
.over {
  font-weight: bold;
  color: #600;
}
.code, .discount, .person {
  font-size: smaller;
}

.payment-buttons {
  text-align: right;
}

.pay-method {
  text-align: center;
}
</style>
<script>
var Txn = {};

Txn.id= function() {
  return viewModel.txn.id ? viewModel.txn.id() : undefined;
}

Txn.due= function() {
  return (viewModel.txn.total() - viewModel.txn.total_paid()).toFixed(2);
}

Txn.delete= function (id) {
  $.getJSON("api/txn-delete?callback=?",
            { txn: id },
            function(data) {
              if (data.error) {
                displayError(data);
              } else {
                window.location.href= './';
              }
            });
}

Txn.loadData= function (data) {
  viewModel.load(data);
  if (data.new_line) {
    setActiveRow($('#items tbody tr[data-line_id=' + data.new_line + ']'));
  }
}

Txn.loadId= function (id) {
  $.getJSON("api/txn-load.php?callback=?",
            { type: "customer",
              id: id },
            function (data) {
              if (data.error) {
                displayError(data);
              } else {
                Txn.loadData(data);
              }
            });
}

Txn.loadNumber= function(num) {
  $.getJSON("api/txn-load.php?callback=?",
            { type: "customer",
              number: num },
            function (data) {
              if (data.error) {
                displayError(data);
              } else {
                Txn.loadData(data);
              }
            });
}

Txn.addNote= function(id, note, pub) {
  $.getJSON("api/txn-add-note.php?callback=?",
            { id: id, note: note, public: pub },
            function (data) {
              Txn.loadData(data);
            });
}

Txn.addPayment= function (id, options) {
  options.id= id;
  $.ajax({ type: 'GET',
           url: "api/txn-add-payment.php?callback=?",
           dataType: 'json',
           data: options,
           async: false,
           success: function(data) {
              if (data.error) {
                displayError(data);
              } else {
                Txn.loadData(data);
                $.smodal.close();
                if (options.method == 'credit' && options.amount >= 25.00) {
                  printChargeRecord(data.payment);
                }
              }
           }});
}

Txn.addItem= function (txn, item) {
  $.getJSON("api/txn-add-item.php?callback=?",
            { txn: txn, item: item.id },
            function(data) {
              if (data.error) {
                displayError(data);
              } else if (data.matches) {
                // this shouldn't happen!
                play("no");
              } else {
                Txn.loadData(data);
              }
            });
}

Txn.removeItem= function (id, item) {
  $.getJSON("api/txn-remove-item.php?callback=?",
            { txn: id, id: item },
            function(data) {
              if (data.error) {
                displayError(data);
                return;
              }
              Txn.loadData(data);
            });
};

Txn.findAndAddItem= function(q) {
  // go find!
  $.ajax({ type: 'GET',
           url: "api/txn-add-item.php?callback=?",
           dataType: 'json',
           data: { txn: Txn.id(), q: q },
           async: false,
           success: function(data) {
              if (data.error) {
                displayError(data);
              } else if (data.matches) {
                if (data.matches.length == 0) {
                  play("no");
                  $("#lookup").addClass("error");
                  var errors= $('<div class="alert alert-danger"/>');
                  errors.text(" Didn't find anything for '" + q + "'.");
                  errors.prepend('<button type="button" class="close" onclick="$(this).parent().remove(); return false">&times;</button>');
                  $("#items").before(errors);
                } else {
                  play("maybe");
                  var choices= $('<div class="choices alert alert-warning"/>');
                  choices.prepend('<button type="button" class="close" onclick="$(this).parent().remove(); return false">&times;</button>');
                  var list= $('<table class="table table-condensed" style="width: 95%;">');
                  $.each(data.matches, function(i,item) {
                    var n= $("<tr" + (item.stock > 0 ? " class='stocked'" : "") + ">" +
                             "<td>" + item.name + "</td>" +
                             "<td>" + item.brand + "</td>" +
                             "<td align='right'>" + (item.sale_price ? ("<s>" + amount(item.retail_price) + "</s>") : "") + "</td>" +
                             "<td align='right'>" + amount(item.sale_price ? item.sale_price : item.retail_price) + "</td>" +
                             "</tr>");
                    n.click(item, function(ev) {
                      Txn.addItem(Txn.id(), ev.data);
                      $(this).closest(".choices").remove();
                    });
                    list.append(n);
                  });
                  choices.append(list);
                  $("#items").before(choices);
                }
              } else {
                play("yes");
                Txn.loadData(data);
              }
            }});
};

Txn.updatePerson= function (txn, person) {
  $.getJSON("api/txn-update-person.php?callback=?",
            { txn: txn, person: person },
            function (data) {
              if (data.error) {
                displayError(data);
                return;
              }
              Txn.loadData(data);
            });
}

Txn.isSpecialOrder = function() {
  return viewModel.txn.special_order ?
           viewModel.txn.special_order() : undefined;
}

Txn.setSpecialOrder= function(txn, special) {
  $.getJSON("api/txn-update.php?callback=?",
            { txn: txn, special_order: special ? 1 : 0 },
            function (data) {
              if (data.error) {
                displayError(data);
                return;
              }
              Txn.loadData(data);
            });
}

Txn.choosePayMethod= function() {
  $.get('ui/pay-choose-method.html').done(function (html) {
    var panel= $(html);

    var data= { due: Txn.due() }
    var dataModel= ko.mapping.fromJS(data);

    ko.applyBindings(dataModel, panel[0]);

    panel.on("click", "button", function(ev) {
      var method= $(this).data("value");
      $.smodal.close();
      var id= "#pay-" + method;
      var due= Txn.due();
      $(".amount", id).val(due);
      $.smodal($(id), { persist: true, overlayClose: false });
      $(".amount", id).focus().select();
    });

    // XXX SimpleModal
    $.smodal(panel);
  });
}

Txn.allocate= function(txn) {
  $.getJSON(
    "api/txn-allocate.php?callback=?",
    { txn: txn },
    function (data) {
      if (data.error) {
        displayError(data);
      }

      Txn.loadData(data);
    });
}

var lastItem;

function updateValue(row, key, value) {
  var txn= Txn.id();
  var line= $(row).data('line_id');
  
  var data= { txn: txn, id: line };
  data[key] = value;

  $.getJSON("api/txn-update-item.php?callback=?",
            data,
            function (data) {
              if (data.error) {
                displayError(data);
              }
              // Force this to be active line
              data.new_line= line;
              Txn.loadData(data);
            });
}

function setActiveRow(row) {
  if (lastItem) {
    lastItem.removeClass('active');
  }
  lastItem= row;
  lastItem.addClass('active');
}

$(document).on('click', '#items tbody tr', function() {
  setActiveRow($(this));
});

$(document).on('dblclick', '.editable', function() {
  // Just stop now if transaction is closed
  if (viewModel.txn.paid() !== null && viewModel.txn.filled() !== null) {
    return false;
  }

  var val= $(this).children('span').eq(0);
  var key= val.attr("class");
  var fld= $('<input type="text">');
  fld.val(val.text());
  fld.attr("class", key);
  fld.width($(this).width());
  fld.data('default', fld.val());

  fld.on('keyup blur', function(ev) {
    // Handle ESC key
    if (ev.type == 'keyup' && ev.which == 27) {
      var val=$('<span>');
      val.text($(this).data('default'));
      val.attr("class", $(this).attr('class'));
      $(this).replaceWith(val);
      return false;
    }

    // Everything else but RETURN just gets passed along
    if (ev.type == 'keyup' && ev.which != '13') {
      return true;
    }

    var row= $(this).closest('tr');
    var key= $(this).attr('class');
    var value= $(this).val();
    var val= $('<span><i class="fa fa-spinner fa-spin"></i></span>');
    val.attr("class", key);
    $(this).replaceWith(val);
    updateValue(row, key, value);

    return false;
  });

  val.replaceWith(fld);
  fld.focus().select();
});

var paymentMethods= {
  cash: "Cash",
  change: "Change",
  credit: "Credit Card",
  square: "Square",
  stripe: "Stripe",
  dwolla: "Dwolla",
  paypal: "PayPal",
  gift: "Gift Card",
  check: "Check",
  discount: "Discount",
  bad: "Bad Debt",
  donation: "Donation",
};

function formatMethod(payment) {
  if (payment.method() == 'discount' && payment.discount()) {
    return 'Discount (' + payment.discount() + '%):';
  } else {
    return paymentMethods[payment.method()] + ':';
  }
}

function printReceipt() {
  var txn= Txn.id();
  if (!txn) {
    displayError("No sale to print.");
    return false;
  }
  var lpr= $('<iframe id="receipt" src="print/receipt.php?print=1&amp;id=' + txn + '"></iframe>').hide();
  $("#receipt").remove();
  $('body').append(lpr);
  return false;
}

function printInvoice() {
  var txn= Txn.id();
  if (!txn) {
    displayError("No sale to print.");
    return false;
  }
  var lpr= $('<iframe id="receipt" src="print/invoice.php?print=1&amp;id=' + txn + '"></iframe>').hide();
  $("#receipt").remove();
  $('body').append(lpr);
  return false;
}

function printChargeRecord(id) {
  var lpr= $('<iframe id="receipt" src="print/charge-record.php?print=1&amp;id=' + id + '"></iframe>').hide();
  $("#receipt").remove();
  $('body').append(lpr);
  return false;
}

$(function() {
  $(document).bind('keydown', 'meta+p', function(ev) {
    return printReceipt();
  });
  $('input').bind('keydown', 'meta+p', function(ev) {
    return printReceipt();
  });

  $(document).bind('keydown', 'meta+shift+backspace', function(ev) {
    var txn= Txn.id();
    if (!txn) {
      return;
    }
    Txn.delete(txn);
  });

  $('#lookup').submit(function(ev) {
    ev.preventDefault();
    $("#lookup").removeClass("error");

    $('input[name="q"]', this).focus().select();

    var q= $('input[name="q"]', this).val();

    // short integer and recently scanned? adjust quantity
    var val= parseInt(q, 10);
    if (q.length < 4 && lastItem && val != 0 && !isNaN(val)) {
      updateValue(lastItem, 'quantity', val);
      return false;
    }

    // (%V|@)INV-(\d+) is an invoice to load
    var m= q.match(/^(%V|@)INV-(\d+)/);
    if (m) {
      Txn.loadId(m[2]);
      return false;
    }

    Txn.findAndAddItem(q);

    return false;
  });

  $("#sidebar a[id='active']").click();
});
</script>
<div class="row">
<div class="col-md-3 col-md-push-9" id="sidebar">
<div class="panel panel-default">
 <div class="panel-heading">
  <h3 class="panel-title">
   <span data-bind="text: description">New Sale</span>
   <button class="btn btn-xs btn-link"
           data-bind="visible: txn.returned_from(), click: loadReturnedFrom">
     <i class="fa fa-reply"></i>
   </button>
  </h3>
 </div> 
 <div class="panel-body">
  <h1 class="text-center" style="margin: 0px; padding: 0px"
      data-bind="text: Scat.amount(txn.due()),
                 css: { 'text-danger': txn.due() < 0 }">
  </h1>
  <h4 class="text-center text-success" style="margin: 0px; padding: 0px"
      data-bind="visible: txn.change(),
                 text: 'Change: ' + Scat.amount(txn.change())">
  </h4>
 </div>
 <div class="panel-footer">
  <div class="btn-group btn-group-lg">
   <button id="print" type="button" class="print-button btn btn-default"
           data-bind="enable: txn.id()">
    Print
   </button>
   <button type="button" class="btn btn-default dropdown-toggle" 
           data-bind="enable: txn.id()"
           data-toggle="dropdown" aria-expanded="false">
    <span class="caret"></span>
    <span class="sr-only">Toggle Dropdown</span>
   </button>
   <ul class="dropdown-menu" role="menu">
    <li><a href="#" class="invoice-button" id="invoice">Invoice</a></li>
    <li><a href="#" class="print-button" id="print">Receipt</a></li>
   </ul>
  </div>
  <button id="pay" type="button" class="pay-button btn btn-lg btn-default"
          data-bind="visible: txn.type() != 'vendor',
                     enable: txn.id() && !txn.paid()">
    Pay
  </button>
  <button type="button" class="btn btn-lg btn-default"
          data-bind="visible: txn.type() == 'vendor',
                     enable: txn.filled() === null,
                     click: allocateTransaction">
    Fill
  </button>
 </div>
</div>
<div class="panel panel-default">
  <div class="panel-heading">
  <ul class="nav nav-pills nav-justified">
    <li class="active"><a id="active">Active</a></li>
    <li><a id="recent">Recent</a></li>
  </ul>
  </div>
<script>
$("#sidebar .nav a").click(function() {
  var params= {
    active: { type: 'customer', active: true },
    unpaid: { type: 'customer', unpaid: true },
    recent: { type: 'customer', limit: 20 },
  };
  $(this).parent().siblings().removeClass('active');
  $.getJSON("api/txn-list.php?callback=?",
            params[$(this).attr('id')],
            function (data) {
              if (data.error) {
                displayError(data);
              } else {
                ko.mapping.fromJS({ orders: data }, viewModel);
              }
            });
  $(this).parent().addClass('active');
});
</script>
<table class="table table-condensed table-striped"
       id="sales">
 <thead>
  <tr><th>#</th><th>Date/Name</th><th>Items</th></tr>
 </thead>
 <tbody>
  <!-- ko foreach: orders -->
  <tr data-bind="click: $parent.loadOrder">
    <td data-bind="text: $data.number"></td>
    <td>
      <span data-bind="text: Date.parse($data.created()).toString('d MMM HH:mm')"></span>
      <div class="person" data-bind="text: $data.person_name()"></div>
    </td>
    <td data-bind="text: $data.ordered"></td>
  </tr>
  <!-- /ko -->
 </tbody>
</table>
</div>
<div class="well">
<form id="txn-load">
  <div class="input-group">
    <input type="text" class="form-control"
           name="invoice" size="8"
           placeholder="Invoice">
    <span class="input-group-btn">
      <button class="btn btn-default" type="button">Load</button>
    </span>
  </div>
</form>
<script>
$("#txn-load").submit(function(ev) {
  ev.preventDefault();
  Txn.loadNumber($("#txn-load input[name='invoice']").val());
  return false;
});
</script>
</div>
</div><!-- /sidebar -->

<div class="col-md-9 col-md-pull-3" id="txn">
<form class="form form-inline" id="lookup">
  <div class="input-group">
    <span class="input-group-addon"><i class="fa fa-barcode"></i></span>
    <input type="text" class="form-control autofocus"
           name="q"
           autocomplete="off" autocorrect="off" autocapitalize="off"
           placeholder="Scan item or enter search terms"
           value="">
    <span class="input-group-btn">
      <input type="submit" class="btn btn-default" value="Find Items">
      <div class="btn-group">
        <button type="button" class="btn btn-default dropdown-toggle"
                data-toggle="dropdown" aria-haspopup="true"
                aria-expanded="false">
          Custom <span class="caret"></span>
        </button>
        <ul class="dropdown-menu">
          <li><a href="#" data-bind="click: function () { Txn.findAndAddItem('ZZ-GIFTCARD'); }">Gift Card</a></li>
          <li role="separator" class="divider"></li>
          <li><a href="#" data-bind="click: function () { Txn.findAndAddItem('ZZ-FRAME'); }">Custom Frame</a></li>
          <li><a href="#" data-bind="click: function () { Txn.findAndAddItem('ZZ-STRETCH'); }">Canvas Stretch</a></li>
          <li><a href="#" data-bind="click: function () { Txn.findAndAddItem('ZZ-PRINT'); }">Digital Print</a></li>
          <li role="separator" class="divider"></li>
          <li><a href="#" data-bind="click: function () { Txn.findAndAddItem('ZZ-FLOAT'); }">Floater Frame</a></li>
          <li><a href="#" data-bind="click: function () { Txn.findAndAddItem('ZZ-CANVAS'); }">Canvas</a></li>
          <li><a href="#" data-bind="click: function () { Txn.findAndAddItem('ZZ-PANEL'); }">Wood Panel</a></li>
        </ul>
      </div>
    </span>
  </div>
</form>
<br>
<div class="panel panel-default">
  <div class="panel-heading">
    <div class="row">
      <div id="sale-buttons" class="col-md-6 col-md-push-6">
        <div class="pull-right">
          <button id="notes" type="button" class="notes-button btn btn-default"
                  data-bind="enable: txn.id(), click: showNotes">
           Notes
           <span class="badge"
                 data-bind="text: notes().length, visible: notes().length">
           </span>
          </button>
          <div class="btn-group">
           <button id="print" type="button" class="print-button btn btn-default"
                   data-bind="enable: txn.id()">
            Print
           </button>
           <button type="button" class="btn btn-default dropdown-toggle" 
                   data-bind="enable: txn.id()"
                   data-toggle="dropdown" aria-expanded="false">
            <span class="caret"></span>
            <span class="sr-only">Toggle Dropdown</span>
           </button>
           <ul class="dropdown-menu">
            <li><a href="#" class="invoice-button" id="invoice">Invoice</a></li>
            <li><a href="#" class="print-button" id="print">Receipt</a></li>
           </ul>
          </div>
          <button id="delete" class="btn btn-default"
                  data-bind="enable: txn.id() && items().length == 0,
                             click: deleteTransaction">
            Delete
          </button>
          <button id="pay" class="pay-button btn btn-default"
                  data-bind="enable: txn.id() && !txn.paid(),
                             visible: !txn.paid() && txn.type() != 'vendor'">
            Pay
          </button>
          <button id="return" class="return-button btn btn-default"
                  data-bind="visible: txn.id() && txn.paid()">
            Return
          </button>
        </div>
      </div>
<script>
$(".invoice-button").on("click", function() {
  printInvoice();
});
$(".print-button").on("click", function() {
  if (viewModel.txn.paid() != null ||
      confirm("Invoice isn't paid. Sure you want to print?"))
  printReceipt();
});

$(".pay-button").on("click", function() {
  var txn= Txn.id();
  if (!Txn.isSpecialOrder()) {
    $.getJSON(
      "api/txn-allocate.php?callback=?",
      { txn: txn },
      function (data) {
        if (data.error) {
          displayError(data);
        }

        Txn.loadData(data);

        Txn.choosePayMethod();
      });
    } else {
      Txn.choosePayMethod();
    }
});

$(".return-button").on("click", function() {
  var txn= Txn.id();
  if (!txn || !confirm("Are you sure you want to create a return?")) {
    return false;
  }
  $.getJSON("api/txn-return.php?callback=?",
            { txn: txn },
            function (data) {
              if (data.error) {
                displayError(data);
              } else {
                Txn.loadData(data);
              }
            });
});
</script>
<form role="form" id="pay-cash" class="pay-method" style="display: none">
 <div class="form-group">
   <input class="amount form-control input-lg text-center"
          type="text" pattern="\d*">
 </div>
 <input type="submit" class="btn btn-primary" name="Pay">
 <button name="cancel" class="btn btn-default">Cancel</button>
</form>
<script>
$("#pay-cash").on("submit", function (ev) {
  ev.preventDefault();
  var txn= Txn.id();
  var amount= $("#pay-cash .amount").val();
  Txn.addPayment(txn, { method: "cash", amount: amount, change: true });
});
</script>
<form id="pay-credit-refund" class="pay-method" style="display: none">
 <div class="form-group">
   <input class="amount form-control input-lg text-center"
          type="text" pattern="\d*">
 </div>
 <input class="btn btn-default" type="submit" value="Refund">
 <button class="btn btn-default" name="cancel">Cancel</button>
</form>
<script>
$("#pay-credit-refund").on("submit", function (ev) {
  ev.preventDefault();
  var txn= Txn.id();
  var amount= $("#pay-credit-refund .amount").val();
  var refund_from= $("#pay-credit-refund").data('from');
  $.getJSON("api/cc-terminal.php?callback=?",
            { id: txn, type: 'Return',
              amount: parseFloat(-1 * amount).toFixed(2),
              from: refund_from },
            function (data) {
              if (data.error) {
                $.smodal.close();
                displayError(data);
              } else {
                Txn.loadData(data);
                $.smodal.close();
              }
            });
  $.smodal.close();
  $("#pay-credit-progress .amount").val(amount);
  $.smodal($("#pay-credit-progress"), { persist: true, overlayClose: false });
});
</script>
<form id="pay-credit" class="pay-method" style="display: none">
 <div class="form-group">
   <input class="amount form-control input-lg text-center"
          type="text" pattern="\d*">
 </div>
 <input class="btn btn-default" type="submit" value="Start">
 <button class="btn btn-default" name="cancel">Cancel</button>
</form>
<div id="pay-credit-progress" style="display: none">
 <div class="progress progress-striped active" style="width: 300px; height: 1.5em">
   <div class="progress-bar" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%;">
     Waiting for terminal&hellip;.
   </div>
 </div>
 <div class="form-group">
   <input class="amount form-control input-lg text-center"
          disabled type="text" pattern="\d*">
 </div>
</div>
<script>
$("#pay-credit").on("submit", function (ev) {
  ev.preventDefault();
  var txn= Txn.id();
  var amount= $("#pay-credit .amount").val();
  $.getJSON("api/cc-terminal.php?callback=?",
            { id: txn, type: 'Sale', amount: parseFloat(amount).toFixed(2) },
            function (data) {
              if (data.error) {
                $.smodal.close();
                displayError(data);
              } else {
                Txn.loadData(data);
                $.smodal.close();
              }
            });
  $.smodal.close();
  $("#pay-credit-progress .amount").val(amount);
  $.smodal($("#pay-credit-progress"), { persist: true, overlayClose: false });
});
</script>
<div id="pay-credit-manual" class="pay-method" style="display: none">
 <div class="form-group">
   <input class="amount form-control input-lg text-center"
          type="text" pattern="\d*">
 </div>
 <button class="btn btn-default" name="Visa">Visa</button>
 <button class="btn btn-default" name="MasterCard">MasterCard</button>
 <button class="btn btn-default" name="Discover">Discover</button>
 <button class="btn btn-default" name="AmericanExpress">American Express</button>
 <button class="btn btn-default" name="cancel">Cancel</button>
</div>
<script>
$("#pay-credit-manual").on("click", "button", function (ev) {
  var txn= Txn.id();
  var amount= $("#pay-credit-manual .amount").val();
  var cc_type= $(this).attr('name');
  if (cc_type == 'cancel') {
    $.smodal.close();
    return false;
  }
  Txn.addPayment(txn, { method: "credit", amount: amount, change: false,
                        cc_type: cc_type });
});
</script>
<form id="pay-other" class="pay-method" style="display: none">
 <div class="form-group">
   <input class="amount form-control input-lg text-center"
          type="text" pattern="\d*">
 </div>
 <button class="btn btn-default" data-value="square">Square</button>
 <button class="btn btn-default" data-value="stripe">Stripe</button>
 <button class="btn btn-default" data-value="dwolla">Dwolla</button>
 <button class="btn btn-default" data-value="paypal">PayPal</button>
 <button class="btn btn-default" data-value="cancel">Cancel</button>
</form>
<script>
$("#pay-other").on("click", "button", function (ev) {
  ev.preventDefault();
  var txn= Txn.id();
  var amount= $("#pay-other .amount").val();
  var method= $(this).data('value');
  if (method == 'cancel') {
    $.smodal.close();
    return false;
  }
  Txn.addPayment(txn, { method: method, amount: amount, change: false });
});
</script>
<div id="pay-gift" class="pay-method" style="display: none">
 <div class="form-group">
   <input class="card form-control" type="text" placeholder="Scan or type card number">
 </div>
 <button class="btn btn-default" name="lookup">Check Card</button>
 <button class="btn btn-default" name="old">Old Card</button>
 <button class="btn btn-default" name="cancel">Cancel</button>
</div>
<div id="pay-gift-complete" class="pay-method" style="display: none">
 <p class="small" id="pay-gift-balance"></p>
 <div class="form-group">
   <input class="amount form-control input-lg text-center"
          type="text" pattern="\d*">
 </div>
 <button class="btn btn-default" name="pay">Pay</button>
 <button class="btn btn-default" name="cancel">Cancel</button>
</div>
<script>
$("#pay-gift").on("click", "button[name='lookup']", function (ev) {
  var txn= Txn.id();
  var card= $("#pay-gift .card").val();
  if (card == '...') {
    card= "11111111111"; // Test card.
  }
  $.getJSON("<?=GIFT_BACKEND?>/check-balance.php?callback=?",
            { card: card },
            function (data) {
              if (data.error) {
                displayError(data);
              } else {
                var due= Txn.due();
                $('#pay-gift-balance').text("Balance: $" +
                                            data.balance +
                                            ", Last used " +
                                            data.latest + '.');
                var def= due;
                if (parseFloat(data.balance) < due) {
                  def= data.balance;
                }
                if (data.balance - due <= 10.00) {
                  def= data.balance;
                }
                $("#pay-gift-complete .amount").val(def);
                $.smodal.close();
                $("#pay-gift-complete").data(data);
                $.smodal($("#pay-gift-complete"), { overlayClose: false, persist: true });
              }
            });
});
$("#pay-gift").on("click", "button[name='old']", function (ev) {
  var due= Txn.due();
  var def= due;
  $("#pay-gift-complete .amount").val(def);
  $.smodal.close();
  $("#pay-gift-complete").data(null);
  $.smodal($("#pay-gift-complete"), { overlayClose: false, persist: true });
});
$("#pay-gift-complete").on("click", "button[name='pay']", function (ev) {
  var txn= Txn.id();
  var amount= $("#pay-gift-complete .amount").val();
  var card= $("#pay-gift-complete").data('card');
  if (card) {
    $.getJSON("<?=GIFT_BACKEND?>/add-txn.php?callback=?",
              { card: card, amount: -amount },
              function (data) {
                if (data.error) {
                  displayError(data);
                } else {
                  var balance= $("#pay-gift-complete").data('balance');
                  Txn.addPayment(txn, { method: "gift", amount: amount,
                                        card: card,
                                        change: (balance - amount <= 10.00) });
                }
              });
  } else {
    Txn.addPayment(txn, { method: "gift", amount: amount, change: true });
  }
});
</script>
<div id="pay-check" class="pay-method" style="display: none">
 <div class="form-group">
   <input class="amount form-control input-lg text-center"
          type="text" pattern="\d*">
 </div>
 <button class="btn btn-default" name="pay">Pay</button>
 <button class="btn btn-default" name="cancel">Cancel</button>
</div>
<script>
$("#pay-check").on("click", "button[name='pay']", function (ev) {
  var txn= Txn.id();
  var amount= $("#pay-check .amount").val();
  Txn.addPayment(txn, { method: "check", amount: amount, change: false });
});
</script>
<form id="pay-discount" class="pay-method" style="display: none">
 <div class="form-group">
   <input class="amount form-control input-lg text-center"
          type="text" pattern="\d*">
 </div>
 <button class="btn btn-default" name="pay">Discount</button>
 <button class="btn btn-default" name="cancel">Cancel</button>
</form>
<script>
$("#pay-discount").on("submit", function (ev) {
  ev.preventDefault();
  var txn= Txn.id();
  var amount= $("#pay-discount .amount").val();
  Txn.addPayment(txn, { method: "discount", amount: amount, change: false });
});
</script>
<div id="pay-bad-debt" class="pay-method" style="display: none">
 <div class="form-group">
   <input class="amount form-control input-lg text-center"
          type="text" pattern="\d*">
 </div>
 <button class="btn btn-default" name="pay">Pay</button>
 <button class="btn btn-default" name="cancel">Cancel</button>
</div>
<script>
$("#pay-bad-debt").on("click", "button[name='pay']", function (ev) {
  var txn= Txn.id();
  var amount= $("#pay-bad-debt .amount").val();
  Txn.addPayment(txn, { method: "bad", amount: amount, change: false });
});
</script>
<form id="pay-donation" class="pay-method" style="display: none">
 <div class="form-group">
   <input class="amount form-control input-lg text-center"
          type="text" pattern="\d*">
 </div>
 <button class="btn btn-default" name="pay">Pay</button>
 <button class="btn btn-default" name="cancel">Cancel</button>
</form>
<script>
$("#pay-donation").on("submit", function (ev) {
  ev.preventDefault();
  var txn= Txn.id();
  var amount= $("#pay-donation .amount").val();
  Txn.addPayment(txn, { method: "donation", amount: amount, change: false });
});
</script>
<script>
$(".pay-method").on("click", "button[name='cancel']", function(ev) {
  ev.preventDefault();
  $.smodal.close();
});
</script>
      <div id="details" class="col-md-6 col-md-pull-6">
        <div style="font-size: larger; font-weight: bold">
          <span data-bind="text: description, click: toggleSpecialOrder">
            New Sale
          </span>
          <button class="btn btn-xs btn-link"
                  data-bind="visible: txn.returned_from(),
                             click: loadReturnedFrom">
            <i class="fa fa-reply"></i>
          </button>
        </div>
        <div data-bind="text: txn.display_dates()"></div>
        <div id="person">
          <span class="val"
                data-bind="text: person.display_name()"></span>
          <i id="info-person" class="fa fa-info-circle"></i>
        </div>
      </div>
    </div>
  </div><!-- .panel-heading -->
<script>
$("#txn #person").on("dblclick", function(ev) {
  var txn= Txn.id();
  if (!txn) {
    return false;
  }

  var fld= $('<input type="text" size="40">');
  fld.val($(".val", this).text());
  fld.data('default', fld.val());

  fld.on('keyup', function(ev) {
    // Handle ESC key
    if (ev.type == 'keyup' && ev.which == 27) {
      var val= $(this).data('default');
      $(this).parent().text(val);
      $(this).remove();
      return false;
    }

    // Everything else but RETURN just gets passed along
    if (ev.type == 'keyup' && ev.which != '13') {
      return true;
    }

    ev.preventDefault();

    var person= {
      id: 0,
      name: $(this).val(),
      company: '',
      email: '',
      phone: '',
      address: '',
      tax_id: '',
    };

    displayPerson(person);

    var val= $(this).data('default');
    $(this).parent().text(val);
    $(this).remove();

    return false;
  });

  fld.autocomplete({
    source: "./api/person-list.php?callback=?",
    minLength: 2,
    select: function(ev, ui) {
      var txn= Txn.id();
      $(this).parent().text(ui.item.value);
      $(this).remove();
      Txn.updatePerson(txn, ui.item.id);
    },
  });

  $(".val", this).empty().append(fld);
  fld.focus().select();
});

$("#txn #info-person").on("click", function(ev) {
  if (!viewModel.person.id())
    return false;

  displayPerson(ko.mapping.toJS(viewModel.person));
});

function displayPerson(person) {
  $.ajax({ url: 'ui/person.html', cache: false }).done(function (html) {
    var panel= $(html);

    panel.on('hidden.bs.modal', function() {
      $(this).remove();
    });

    person.error= '';

    personModel= ko.mapping.fromJS(person);

    personModel.savePerson= function(place, ev) {
      var person= ko.mapping.toJS(personModel);
      $.getJSON(person.id ? "api/person-update.php?callback=?" :
                            "api/person-add.php?callback=?",
                person,
                function (data) {
                  if (data.error) {
                    displayError(data);
                    return;
                  }
                  if (person.id) {
                    viewModel.load(data);
                  } else {
                    Txn.updatePerson(Txn.id(), data.person);
                  }
                  $(place).closest('.modal').modal('hide');
                });
    }

    ko.applyBindings(personModel, panel[0]);

    panel.appendTo($('body')).modal();
  });
}
</script>
<table class="table table-condensed table-striped" id="items">
 <thead>
  <tr>
    <th></th>
    <th>Qty</th>
    <th data-bind="visible: showAllocated()">
      Fill
    </th>
    <th>Code</th>
    <th width="50%">Name</th>
    <th>Price</th>
    <th>Ext</th>
  </tr>
 </thead>
 <tfoot>
    <tr id="subtotal-row">
      <th data-bind="attr: { colspan: showAllocated() ? 5 : 4 }"></th>
      <th align="right">Subtotal:</th>
      <td data-bind="text: amount(txn.subtotal())" class="right">$0.00</td>
    </tr>
    <tr id="tax-row">
      <th data-bind="attr: { colspan: showAllocated() ? 5 : 4 }"></th>
      <th align="right" id="tax_rate">Tax (<span class="val" data-bind="text: txn.tax_rate">0.00</span>%):</th>
      <td data-bind="text: amount(txn.total() - txn.subtotal())" class="right">$0.00</td>
    </tr>
    <tr id="total-row">
      <th data-bind="attr: { colspan: showAllocated() ? 5 : 4 }"></th>
      <th align="right">Total:</th>
      <td data-bind="text: amount(txn.total())" class="right">$0.00</td>
    </tr>
    <!-- ko foreach: payments -->
    <tr class="payment-row" data-bind="attr: { 'data-id': $data.id }">
      <th data-bind="attr: { colspan: $parent.showAllocated() ? 5 : 4 }"
          class="payment-buttons">
        <a data-bind="visible: $parent.showAdmin()" name="remove">
          <i class="fa fa-trash-o"></i>
        </a>
        <a name="print" data-bind="visible: method() == 'credit'">
          <i class="fa fa-print"></i>
        </a>
      </th>
      <th class="payment-method" align="right"
          data-bind="text: formatMethod($data)">Method:</th>
      <td class="right" data-bind="text: Scat.amount($data.amount())">$0.00</td>
    </tr>
    <!-- /ko -->
    <tr id="due-row" data-bind="visible: txn.total()">
      <th data-bind="attr: { colspan: showAllocated() ? 5 : 4 }"
          style="text-align: right">
        <a id="lock" data-bind="visible: payments().length,
                                click: function () { showAdmin(!showAdmin()) }">
          <i data-bind="css: { fa: true,
                               'fa-lock': !showAdmin(),
                               'fa-unlock-alt': showAdmin() }"></i>
        </a>
      </th>
      <th align="right">Due:</th>
      <td data-bind="text: amount(txn.total() - txn.total_paid())"
          class="right">
        $0.00
      </td>
    </tr>
 </tfoot>
<script>
$("#items").on("click", ".payment-row a[name='print']", function() {
  var row= $(this).closest(".payment-row");
  printChargeRecord(row.data("id"));
});
$("#items").on("click", ".payment-row a[name='remove']", function() {
  var txn= Txn.id();
  var row= $(this).closest(".payment-row");
  $.getJSON("api/txn-remove-payment.php?callback=?",
            { txn: txn, id: row.data("id"),
              admin: (viewModel.showAdmin() ? 1 : 0) },
            function (data) {
              if (data.error) {
                displayError(data);
                return;
              }
              Txn.loadData(data);
            });
});
$('#tax_rate .val').editable(function(value, settings) {
  var txn= Txn.id();

  $.getJSON("api/txn-update-tax-rate.php?callback=?",
            { txn: txn, tax_rate: value },
            function (data) {
              if (data.error) {
                displayError(data);
                return;
              }
              Txn.loadData(data);
            });
  return "...";
}, { event: 'dblclick', style: 'display: inline', width: '4em' });
</script>
  <tbody data-bind="foreach: items">
    <tr class="item" valign="top"
        data-bind="attr: { 'data-line_id': $data.line_id }">
      <td>
        <a class="remove"
           data-bind="click: $parent.removeItem">
          <i class="fa fa-trash-o" title="Remove"></i>
        </a>
      </td>
      <td align="center" class="editable"
          data-bind="css: { over: $data.quantity() > $data.stock() }">
        <span class="quantity" data-bind="text: $data.quantity"></span>
      </td>
      <td align="center" class="editable"
          data-bind="visible: $parent.showAllocated(),
                     css: { over: $data.allocated() > $data.quantity() }">
        <span class="allocated" data-bind="text: $data.allocated"></span>
      </td>
      <td align="left">
        <span data-bind="text: $data.code"></span>
      </td>
      <td class="editable">
        <span class="name" data-bind="text: $data.name"></span>
        <div class="discount" data-bind="text: $data.discount"></div>
      </td>
      <td class="editable" class="right">
        <span class="price" data-bind="text: Scat.amount($data.price())"></span>
      </td>
      <td class="right">
        <span data-bind="text: Scat.amount($data.ext_price())"></span>
      </td>
    </tr>
  </tbody>
</table>
</div>
</div>
<?foot();?>
<script>
var model= {
  txn: {
    id: 0,
    subtotal: 0.00,
    tax_rate: 0.00,
    tax: 0.00,
    total: 0.00,
    total_paid: 0.00,
    returned_from: 0,
    created: null,
    filled: null,
    paid: null,
    number: 0,
    formatted_number: 0,
    special_order: 0,
    type: '',
  },
  items: [],
  payments: [],
  notes: [],
  person: {
    id: 0,
    name: '',
    company: '',
    email: '',
    phone: '',
    address: '',
    tax_id: '',
  },
  orders: [],
  showAdmin: false,
};

var viewModel= ko.mapping.fromJS(model);

viewModel.description= ko.computed(function() {
  if (!viewModel.txn.created()) { return "New Sale"; }
  var type= (viewModel.txn.type() == 'vendor' ? 'PO' :
             (viewModel.txn.special_order() ? 'Special Order' :
              (viewModel.txn.total_paid() ? 'Invoice' :
               (viewModel.txn.returned_from() ? 'Return' : 'Sale'))));
  return type + ' ' + viewModel.txn.formatted_number();
}, viewModel);

viewModel.txn.due= ko.computed(function() {
  return (viewModel.txn.total() - viewModel.txn.total_paid());
}, viewModel);

viewModel.txn.change= ko.computed(function() {
  var change= 0.00;
  var len= viewModel.payments().length;
  for (var i= 0; i < len; i++) {
    if (viewModel.payments()[i].method() == 'change') {
      change+= viewModel.payments()[i].amount();
    }
  }
  return -1 * change;
}, viewModel);

viewModel.person.display_name= ko.computed(function() {
  var name= viewModel.person.name();
  if (name && viewModel.person.company()) {
    name= name + ' / ';
  }
  if (viewModel.person.company()) {
    name= name + viewModel.person.company();
  }
  if (!name) { name= 'Anonymous'; }
  return name;
}, viewModel);

viewModel.txn.display_dates= ko.computed(function() {
  if (!viewModel.txn.created()) { return ""; }
  var format= 'MMM d yyyy h:mmtt';
  var dates= Date.parse(viewModel.txn.created()).toString(format);
  if (viewModel.txn.filled()) {
    dates = dates + ' / Filled: ' + Date.parse(viewModel.txn.filled()).toString(format);
  }
  if (viewModel.txn.paid()) {
    dates = dates + ' / Paid: ' + Date.parse(viewModel.txn.paid()).toString(format);
  }
  return dates;
}, viewModel);

viewModel.load= function(txn) {
  ko.mapping.fromJS(txn, viewModel);
}

viewModel.loadReturnedFrom= function() {
  Txn.loadId(viewModel.txn.returned_from());
}

viewModel.deleteTransaction= function() {
  var txn= Txn.id();
  Txn.delete(txn);
}

viewModel.allocateTransaction= function() {
  var txn= Txn.id();
  Txn.allocate(txn);
}

viewModel.showNotes= function() {
  $.ajax({ url: 'ui/show-notes.html', cache: false }).done(function (html) {
    var panel= $(html);

    var data= { notes: viewModel.notes() }
    var dataModel= ko.mapping.fromJS(data);

    dataModel.addNote= function(place, ev) {
      var txn= Txn.id();
      var note= $('input[name="note"]', place).val();
      var pub= $('input[name="public"]', place).is(':checked') ? 1 : 0;

      Txn.addNote(txn, note, pub);

      $(place).closest('.modal').modal('hide');
    }

    ko.applyBindings(dataModel, panel[0]);

    panel.on('hidden.bs.modal', function() {
      $(this).remove();
    });
    panel.on('shown.bs.modal', function() {
      $('input[name="note"]', this).focus();
    });


    panel.appendTo($('body')).modal();
    $('input[name="note"]', panel).focus();
  });
}

viewModel.removeItem= function(item) {
  var txn= Txn.id();
  if (!txn) return;
  Txn.removeItem(txn, item.line_id());
}

viewModel.toggleSpecialOrder= function(item) {
  var txn= Txn.id();
  if (!txn) return;
  Txn.setSpecialOrder(txn, !Txn.isSpecialOrder());
}

viewModel.loadOrder= function(order) {
  Txn.loadId(order.id());
}

viewModel.showAllocated= function() {
  return (viewModel.txn.special_order() || viewModel.txn.type() == 'vendor');
}

ko.applyBindings(viewModel);

<?
  $id= (int)$_REQUEST['id'];
  $number= (int)$_REQUEST['number'];
  if ($number) {
    $q= "SELECT id FROM txn WHERE type = 'customer' AND number = $number";
    $id= $db->get_one($q);
  }

  if ($id) {
    $data= txn_load_full($db, $id);
    echo 'Txn.loadData(', json_encode($data), ");\n";
  }
?>
$("body").html5Uploader({
  name: 'src',
  postUrl: 'api/txn-upload-items.php?txn=' + Txn.id(),
  onSuccess: function(e, file, response) {
    data= $.parseJSON(response);
    if (data.error) {
      displayError(data);
      return;
    }
    Txn.loadData(data);
  },
  onServerError: function(e, file) {
    alert("File upload failed.");
  },
});
</script>
