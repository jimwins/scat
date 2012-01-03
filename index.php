<?
require 'scat.php';

head("Scat");
?>
<style>
.choices {
  margin: 8px 4px;
  padding: 6px;
  background: rgba(0,0,0,0.1);
  border-radius: 4px;
  width: 70%;
}
.choices span {
  margin-right: 8px;
  text-decoration: underline;
  color: #339;
  cursor:pointer;
}
.choices img {
  vertical-align: middle;
}
tbody tr.active {
  background-color:rgba(255,192,192,0.4);
}
tfoot td {
  background-color:rgba(255,255,255,0.5);
  font-weight: bold;
}
#subtotal-row td, #subtotal-row th {
  border-bottom: 2px solid rgba(255,255,255,0.2);
}
#tax-row td, #tax-row th {
  border-bottom: 4px solid rgba(255,255,255,0.2);
}
#due-row td, #due-row th {
  border-top: 4px solid rgba(255,255,255,0.2);
  border-bottom: 4px solid rgba(255,255,255,0.2);
}
.over {
  font-weight: bold;
  color: #600;
}
.code, .discount, .person {
  font-size: smaller;
}
.dollar:before {
  content: '$';
}

#txn {
  width: 70%;
}

/* Hide/show some elements from paid invoices. */
#txn.paid .remove,
#txn.paid #pay
{
  display: none;
}
#txn #return {
  display: none;
}
#txn.paid #return
{
  display: inline;
}

#sale-buttons {
  float: right;
}

.payment-buttons {
  text-align: right;
}

.pay-method {
  text-align: center;
}

#sidebar {
  width: 22%;
  float: right;
  border: 2px solid rgba(0,0,0,0.3);
  padding: 1em;
  margin: 0em 0.5em;
  font-size: smaller;
}
#sidebar caption {
  font-weight: bold;
  font-size: larger;
  padding-bottom: 0.2em;
  text-align: left;
}
#sidebar caption:before {
  content: "\0025B6 ";
}
#sidebar caption.open:before {
  content: "\0025BD ";
}
</style>
<script>
var snd= new Object;
snd.yes= new Audio("./sound/yes.wav");
snd.no= new Audio("./sound/no.wav");
snd.maybe= new Audio("./sound/maybe.wav");

$.getFocusedElement = function() {
  var elem = document.activeElement;
  return $( elem && ( elem.type || elem.href ) ? elem : [] );
};

// http://stackoverflow.com/a/3109234
function round_to_even(num, decimalPlaces) {
  var d = decimalPlaces || 0;
  var m = Math.pow(10, d);
  var n = d ? num * m : num;
  var i = Math.floor(n), f = n - i;
  var r = (f == 0.5) ? ((i % 2 == 0) ? i : i + 1) : Math.round(n);
  return d ? r / m : r;
}

// format number as $3.00 or ($3.00)
function amount(amount) {
  if (amount < 0.0) {
    return '($' + Math.abs(amount).toFixed(2) + ')';
  } else {
    return '$' + amount.toFixed(2);
  }
}

var lastItem;

function updateItems(items) {
  $.each(items, function(i,item) {
    var row= $("#txn tbody tr:data(line_id=" + item.line_id + ")");
    row.data(item);
    updateRow(row);
  });
  updateTotal();
}

function updateRow(row) {
  $('.quantity', row).text(row.data('quantity'));
  if (row.data('quantity') > row.data('.stock')) {
    $('.quantity', row).addClass('over');
  } else {
    $('.quantity', row).removeClass('over');
  }
  $('.code', row).text(row.data('code'));
  $('.name', row).text(row.data('name'));
  $('.discount', row).text(row.data('discount'));
  $('.price', row).text(row.data('price').toFixed(2));
  var ext= row.data('quantity') * row.data('price');
  $('.ext', row).text(amount(ext));
}

function updateValue(row, key, value) {
  var txn= $('#txn').data('txn');
  var line= $(row).data('line_id');
  
  var data= { txn: txn, id: line };
  data[key] = value;

  $.getJSON("api/txn-update-item.php?callback=?",
            data,
            function (data) {
              updateOrderData(data.txn);
              updateItems(data.items);
            });
}

function setActiveRow(row) {
  if (lastItem) {
    lastItem.removeClass('active');
  }
  lastItem= row;
  lastItem.addClass('active');
}

$('.editable').live('dblclick', function() {
  // Just stop now if transaction is paid
  if ($('#txn').hasClass('paid')) {
    return false;
  }

  var val= $(this).children('span').eq(0);
  var key= val.attr("class");
  var fld= $('<input type="text">');
  fld.val(val.text());
  fld.attr("class", key);
  fld.width($(this).width());
  fld.data('default', fld.val());

  fld.on('keyup blur', function(event) {
    // Handle ESC key
    if (event.type == 'keyup' && event.which == 27) {
      var val=$('<span>');
      val.text($(this).data('default'));
      val.attr("class", $(this).attr('class'));
      $(this).replaceWith(val);
      return false;
    }

    // Everything else but RETURN just gets passed along
    if (event.type == 'keyup' && event.which != '13') {
      return true;
    }

    var row= $(this).closest('tr');
    var key= $(this).attr('class');
    var value= $(this).val();
    var val= $('<span>Updating</span>');
    val.attr("class", key);
    $(this).replaceWith(val);
    updateValue(row, key, value);

    return false;
  });

  val.replaceWith(fld);
  fld.focus().select();
});

$('#tax_rate').live('dblclick', function() {
  fld= $('<input type="text" size="2">');
  fld.val($('#txn').data('tax_rate'));

  fld.bind('keypress blur', function(event) {
    if (event.type == 'keypress' && event.which != '13') {
      return true;
    }
  
    var tax_rate= $(this).val();
    var txn= $('#txn').data('txn');

    $.getJSON("api/txn-update-tax-rate.php?callback=?",
              { txn: txn, tax_rate: tax_rate},
              function (data) {
                // XXX handle error
                var prc= $('<span class="val"></span>');
                $('#tax_rate').children().replaceWith(prc);
                updateOrderData(data.txn);
                updateTotal();
              });
    return true;
  });

  $(this).children('.val').replaceWith(fld);
  fld.focus().select();
});

$('.remove').live('click', function() {
  var txn= $('#txn').data('txn');
  var id= $(this).closest('tr').data('line_id');

  $.ajax({
    url: "api/txn-remove-item.php",
    dataType: "json",
    data: ({ txn: txn, id: id }),
    success: function(data) {
      if (data.error) {
        $.modal(data.error);
        return;
      }
      var row= $("#txn tbody tr:data(line_id=" + data.removed + ")");
      if (row.is('.active')) {
        lastItem= null;
      }
      row.remove();
      updateOrderData(data.txn);
      updateTotal();
    }
  });
  return false;
});

function addItem(item) {
  var txn= $("#txn").data("txn");
  $.ajax({
    url: "api/txn-add-item.php",
    dataType: "json",
    data: ({ txn: txn, item: item.id }),
    success: function(data) {
      if (data.error) {
        snd.no.play();
        $("#items .error").html("<p>" + data.error + "</p>");
        $("#items .error").show();
      } else {
        updateOrderData(data.txn);
        if (data.items.length == 1) {
          snd.yes.play();
          addNewItem(data.items[0]);
          updateTotal();
        } else {
          snd.no.play();
        }
      }
    }
  });
}

var protoRow= $('<tr class="item" valign="top"><td><a class="remove"><img src="./icons/tag_blue_delete.png" width=16 height=16 alt="Remove"></a><td align="center" class="editable"><span class="quantity"></span></td><td align="left"><span class="code"></span></td><td class="editable"><span class="name"></span><div class="discount"></div></td><td class="editable dollar" align="right"><span class="price"></span></td><td align="right"><span class="ext"></span></td></tr>');

function addNewItem(item) {
  var row= $("#items tbody tr:data(line_id=" + item.line_id + ")").first();

  if (!row.length) {
    // add the new row
    row= protoRow.clone();
    row.on('click', function() { setActiveRow($(this)); });
    row.appendTo('#items tbody');
  }

  row.data(item);
  updateRow(row);
  setActiveRow(row);
}

var paymentRow= $('<tr class="payment-row"><th colspan=4 class="payment-buttons"></th><th class="payment-method" align="right">Method:</th><td class="payment-amount" align="right">$0.00</td></tr>');

var paymentMethods= {
  cash: "Cash",
  change: "Change",
  credit: "Credit Card",
  gift: "Gift Card",
  check: "Check",
  discount: "Discount",
};

function updateTotal() {
  var total= $("#txn").data("total");
  var subtotal= $("#txn").data("subtotal");
  $('#items #subtotal').text(amount(subtotal));
  var tax_rate= $('#txn').data('tax_rate');
  var tax= total - subtotal;
  $('#items #tax').text(amount(tax));
  $('#items #total').text(amount(total));

  $('.payment-row').remove();

  $.each($('#txn').data('payments'), function(i, payment) {
    var row= paymentRow.clone();
    row.data(payment);
    if (payment.method == 'discount' && payment.discount) {
      $('.payment-method', row).text('Discount (' + payment.discount + '%):');
    } else {
      $('.payment-method', row).text(paymentMethods[payment.method] + ':');
    }
    $('.payment-amount', row).text(amount(payment.amount));

    if (payment.method == 'credit') {
      $('.payment-buttons', row).append($('<button name="print">Print</button>'));
    }
    if (payment.method == 'discount') {
      $('.payment-buttons', row).append($('<button name="remove">Remove</button>'));
    }


    $('#due-row').before(row);
  });

  var paid= $('#txn').data('paid');;
  if (paid > 0) {
    $('#items #due').text(Math.abs(total - paid).toFixed(2))
    $('#due-row').show();
  } else {
    $('#due-row').hide();
  }

}

function updateOrderData(txn) {
  // set transaction data
  $('#txn').data('txn', txn.id);
  $('#txn').data('subtotal', txn.subtotal)
  $('#txn').data('total', txn.total)
  $('#txn').data('paid', txn.total_paid)
  $('#txn').toggleClass('paid', txn.paid != null);
  var tax_rate= parseFloat(txn.tax_rate).toFixed(2);
  $('#txn').data('tax_rate', tax_rate)
  var prc= $('<span class="val">' + tax_rate +  '</span>');
  $('#txn #tax_rate .val').replaceWith(prc);
  $('#txn #description').text("Sale " + txn.number);
}

function loadOrder(data) {
  updateOrderData(data.txn)

  $('#txn').data('payments', data.payments);

  // dump existing item rows
  $("#items tbody tr").remove();

  // load rows
  $.each(data.items, function(i, item) {
    addNewItem(item);
  });

  updateTotal();
}

function showOpenOrders(data) {
  $('#sales tbody').empty();
  $.each(data, function(i, txn) {
    var row=$('<tr><td>' + txn.number + '</td>' +
              '<td>' + Date.parse(txn.created).toString('d MMM HH:mm') +
              '<div class="person">' + txn.person_name + '</div>' + '</td>' +
              '<td>' + txn.ordered + '</td></tr>');
    row.click(txn, function(ev) {
      $("#status").text("Loading sale...").show();
      $.getJSON("api/txn-load.php?callback=?",
                { id: ev.data.id },
                function (data) {
                  if (data.error) {
                    $.modal(data.error);
                  } else {
                    loadOrder(data);
                  }
                  $("#status").text("Loaded sale.").fadeOut('slow');
                });
    });
    $('#sales tbody').append(row);
  });
  $('#sales').show();
}

function printReceipt() {
  var txn= $('#txn').data('txn');
  if (!txn) {
    $.modal("No sale to print.");
    return false;
  }
  var lpr= $('<iframe id="receipt" src="receipt.php?print=1&amp;id=' + txn + '"></iframe>').hide();
  $("#receipt").remove();
  $('body').append(lpr);
  return false;
}

function printChargeRecord(id) {
  var lpr= $('<iframe id="receipt" src="charge-record.php?print=1&amp;id=' + id + '"></iframe>').hide();
  $("#receipt").remove();
  $('body').append(lpr);
  return false;
}

$(function() {
  $('#txn').data('tax_rate', 0.00);

  $(document).keydown(function(event) {
    if (event.metaKey || event.altKey || event.ctrlKey || event.shiftKey) {
      return true;
    }
    var el = $.getFocusedElement();
    if (!el.length) {
      var inp= $('input[name="q"]', this);
      if (event.keyCode != 13) {
        inp.val('');
      }
      inp.focus();
    }
  });

  $(document).bind('keydown', 'meta+p', function(ev) {
    return printReceipt();
  });

  $('#lookup').submit(function() {
    $('#items .error').hide(); // hide old error messages
    $('input[name="q"]', this).focus().select();

    var q = $('input[name="q"]', this).val();

    // short integer and recently scanned? adjust quantity
    if (q.length < 4 && lastItem && parseInt(q) != 0) {
      updateValue(lastItem, 'quantity', parseInt(q));
      return false;
    }

    txn= $('#txn').data('txn');

    // go find!
    $.ajax({
      url: "api/txn-add-item.php",
      dataType: "json",
      data: ({ txn: txn, q: q }),
      success: function(data) {
        if (data.error) {
          snd.no.play();
          $("#items .error").html("<p>" + data.error + "</p>");
          $("#items .error").show();
        } else {
          updateOrderData(data.txn);
          if (data.items.length == 0) {
            snd.no.play();
          } else if (data.items.length == 1) {
            snd.yes.play();
            addNewItem(data.items[0]);
            updateTotal();
          } else {
            snd.maybe.play();
            var choices= $('<div class="choices"/>');
            choices.append('<span onclick="$(this).parent().remove(); return false"><img src="icons/control_eject_blue.png" style="vertical-align:absmiddle" width=16 height=16 alt="Skip"></span>');
            $.each(data.items, function(i,item) {
              var n= $("<span>" + item.name + "</span>");
              n.click(item, function(event) {
                addItem(event.data);
                $(this).parent().remove();
              });
              choices.append(n);
            });
            $("#items .error").after(choices);
          }
        }
      }
    });

    return false;
  });

  $("#open-orders").click();

<?
  $number= (int)$_REQUEST['number'];
  if ($number) {
    echo "$('#txn-load input').val($number).parent().submit();";
  }
?>
});
</script>
<div id="sidebar">
<button id="open-orders">Open Orders</button>
<button id="unpaid-invoices">Unpaid Invoices</button>
<script>
$("#open-orders").click(function() {
  $("#sales").hide();
  $.getJSON("api/txn-list.php?callback=?",
            { type: 'customer', unfilled: true },
            function (data) {
              if (data.error) {
                $.modal(data.error);
              } else {
                showOpenOrders(data);
              }
              $("#status").text("Loaded open orders.").fadeOut('slow');
            });
  $(this).addClass('open');
  $("#status").text("Loading open orders...").show();
});
$("#unpaid-invoices").click(function() {
  $("#sales").hide();
  $.getJSON("api/txn-list.php?callback=?",
            { type: 'customer', unpaid: true },
            function (data) {
              if (data.error) {
                $.modal(data.error);
              } else {
                showOpenOrders(data);
              }
              $("#status").text("Loaded unpaid invoices.").fadeOut('slow');
            });
  $(this).addClass('open');
  $("#status").text("Loading unpaid invoices...").show();
});
</script>
<table id="sales" width="100%" style="display: none">
 <thead>
  <tr><th>#</th><th>Date/Name</th><th>Items</th></tr>
 </thead>
 <tbody>
 </tbody>
</table>
<br>
<form id="txn-load">
 Invoice: <input type="text" name="invoice" size="8">
 <button>Load</button>
</form>
<script>
$("#txn-load").submit(function() {
  $.getJSON("api/txn-load.php?callback=?",
            { type: "customer",
              number: $("#txn-load input[name='invoice']").val() },
            function (data) {
              if (data.error) {
                $.modal(data.error);
              } else {
                loadOrder(data);
              }
              $("#status").text("Loaded sale.").fadeOut('slow');
            });
  return false;
});
</script>
</div>
</div>
<form id="lookup">
<input type="text" name="q" size="100" autocomplete="off" placeholder="Scan item or enter search terms" value="">
<input type="submit" value="Find Items">
</form>
<div id="txn">
<div id="sale-buttons">
  <button id="print">Print</button>
  <button id="pay">Pay</button>
  <button id="return">Return</button>
</div>
<script>
$("#print").on("click", function() {
  printReceipt();
});
$("#pay").on("click", function() {
  var txn= $('#txn').data('txn');
  $.getJSON("api/txn-allocate.php?callback=?",
            { txn: txn },
            function (data) {
              if (data.error) {
                $.modal(data.error);
              }
              $.modal($("#choose-pay-method"), { persist: true});
            });
});
$("#return").on("click", function() {
  var txn= $('#txn').data('txn');
  if (!txn || !confirm("Are you sure you want to create a return?")) {
    return false;
  }
  $.getJSON("api/txn-return.php?callback=?",
            { txn: txn },
            function (data) {
              if (data.error) {
                $.modal(data.error);
              } else {
                loadOrder(data);
              }
            });
});
</script>
<div id="choose-pay-method" style="display: none">
 <button data-value="cash">Cash</button>
<?if ($DEBUG) {?>
 <button data-value="credit">Credit Card</button>
<?}?>
 <button data-value="credit-manual">Credit Card (Manual)</button>
 <button data-value="gift">Gift Card</button>
 <button data-value="check">Check</button>
 <button data-value="discount">Discount</button>
</div>
<script>
$("#choose-pay-method").on("click", "button", function(ev) {
  var method= $(this).data("value");
  $.modal.close();
  var id= "#pay-" + method;
  var due= ($("#txn").data("total") - $("#txn").data("paid")).toFixed(2);
  $(".amount", id).val(due);
  $.modal($(id), { overlayClose: false });
  $(".amount", id).focus().select();
});
</script>
<div id="pay-cash" class="pay-method" style="display: none">
 <input class="amount" type="text" pattern="\d*">
 <br>
 <button name="pay">Pay</button>
 <button name="cancel">Cancel</button>
</div>
<script>
$("#pay-cash").on("click", "button[name='pay']", function (ev) {
  var txn= $("#txn").data("txn");
  var amount= $("#pay-cash .amount").val();
  $.getJSON("api/txn-add-payment.php?callback=?",
            { id: txn, method: "cash", amount: amount, change: true },
            function (data) {
              if (data.error) {
                alert(data.error);
              } else {
                updateOrderData(data.txn);
                $('#txn').data('payments', data.payments);
                updateTotal();
                $.modal.close();
              }
            });
});
</script>
<div id="pay-credit-manual" class="pay-method" style="display: none">
 <input class="amount" type="text" pattern="\d*">
 <br>
 <button name="Visa">Visa</button>
 <button name="MasterCard">MasterCard</button>
 <button name="Discover">Discover</button>
 <button name="AmericanExpress">American Express</button>
 <button name="cancel">Cancel</button>
</div>
<script>
$("#pay-credit-manual").on("click", "button[name!='cancel']", function (ev) {
  var txn= $("#txn").data("txn");
  var amount= $("#pay-credit-manual .amount").val();
  var cc_type= $(this).attr('name');
  $.getJSON("api/txn-add-payment.php?callback=?",
            { id: txn, method: "credit", amount: amount, change: false,
              cc_type: cc_type },
            function (data) {
              if (data.error) {
                alert(data.error);
              } else {
                updateOrderData(data.txn);
                $('#txn').data('payments', data.payments);
                updateTotal();
                $.modal.close();
                if (amount >= 25.00) {
                  printChargeRecord(data.payment);
                }
              }
            });
});
</script>
<div id="pay-gift" class="pay-method" style="display: none">
 Card: <input class="card" type="text" size="15">
 <br>
 <button name="lookup">Check Card</button>
 <button name="cancel">Cancel</button>
</div>
<div id="pay-gift-complete" class="pay-method" style="display: none">
 <div id="pay-gift-balance"></div>
 Amount: <input class="amount" type="text" size="20">
 <br>
 <button name="pay">Pay</button>
 <button name="cancel">Cancel</button>
</div>
<script>
$("#pay-gift").on("click", "button[name='lookup']", function (ev) {
  var txn= $("#txn").data("txn");
  var card= $("#pay-gift .card").val();
  if (card == '...') {
    card= "11111111111"; // Test card.
  }
  $.getJSON("<?=GIFT_BACKEND?>/check-balance.php?callback=?",
            { card: card },
            function (data) {
              if (data.error) {
                alert(data.error);
              } else {
                var due= ($("#txn").data("total") - $("#txn").data("paid"));
                $('#pay-gift-balance').text("Card has $" +
                                            data.balance +
                                            " remaining. Last used " +
                                            data.latest + '.');
                var def= due;
                if (data.balance < due) {
                  def= data.balance;
                }
                if (data.balance - due <= 10.00) {
                  def= data.balance;
                }
                $("#pay-gift-complete .amount").val(def);
                $.modal.close();
                $("#pay-gift-complete").data(data);
                $.modal($("#pay-gift-complete"), { overlayClose: false, persist: true });
              }
            });
});
$("#pay-gift-complete").on("click", "button[name='pay']", function (ev) {
  var txn= $("#txn").data("txn");
  var amount= $("#pay-gift-complete .amount").val();
  var card= $("#pay-gift-complete").data('card');
  $.getJSON("<?=GIFT_BACKEND?>/add-txn.php?callback=?",
            { card: card, amount: -amount },
            function (data) {
              if (data.error) {
                alert(data.error);
              } else {
                var balance= $("#pay-gift-complete").data('balance');
                $.getJSON("api/txn-add-payment.php?callback=?",
                          { id: txn, method: "gift", amount: amount,
                            change: (balance - amount <= 10.00) },
                          function (data) {
                            if (data.error) {
                              alert(data.error);
                            } else {
                              updateOrderData(data.txn);
                              $('#txn').data('payments', data.payments);
                              updateTotal();
                              $.modal.close();
                            }
                          });
              }
            });
});
</script>
<div id="pay-check" class="pay-method" style="display: none">
 <input class="amount" type="text" pattern="\d*">
 <br>
 <button name="pay">Pay</button>
 <button name="cancel">Cancel</button>
</div>
<script>
$("#pay-check").on("click", "button[name='pay']", function (ev) {
  var txn= $("#txn").data("txn");
  var amount= $("#pay-check .amount").val();
  $.getJSON("api/txn-add-payment.php?callback=?",
            { id: txn, method: "check", amount: amount, change: false },
            function (data) {
              if (data.error) {
                alert(data.error);
              } else {
                updateOrderData(data.txn);
                $('#txn').data('payments', data.payments);
                updateTotal();
                $.modal.close();
              }
            });
});
</script>
<div id="pay-discount" class="pay-method" style="display: none">
 <input class="amount" type="text" pattern="\d*">
 <br>
 <button name="pay">Discount</button>
 <button name="cancel">Cancel</button>
</div>
<script>
$("#pay-discount").on("click", "button[name='pay']", function (ev) {
  var txn= $("#txn").data("txn");
  var amount= $("#pay-discount .amount").val();
  $.getJSON("api/txn-add-payment.php?callback=?",
            { id: txn, method: "discount", amount: amount, change: false },
            function (data) {
              if (data.error) {
                alert(data.error);
              } else {
                updateOrderData(data.txn);
                $('#txn').data('payments', data.payments);
                updateTotal();
                $.modal.close();
              }
            });
});
</script>
<script>
$(".pay-method").on("click", "button[name='cancel']", function(ev) {
  $.modal.close();
});
</script>
<h2 id="description">New Sale</h2>
<div id="items">
 <div class="error"></div>
 <table width="100%">
 <thead>
  <tr><th></th><th>Qty</th><th>Code</th><th width="50%">Name</th><th>Price</th><th>Ext</th></tr>
 </thead>
 <tfoot>
  <tr id="subtotal-row"><th colspan=4></th><th align="right">Subtotal:</th><td id="subtotal" class="right">$0.00</td></tr>
  <tr id="tax-row"><th colspan=4></th><th align="right" id="tax_rate">Tax (<span class="val">0.00</span>%):</th><td id="tax" class="right">$0.00</td></tr>
  <tr id="total-row"><th colspan=4></th><th align="right">Total:</th><td id="total" class="right">$0.00</td></tr>
  <tr id="due-row" style="display:none"><th colspan=4></th><th align="right">Due:</th><td id="due" class="right">$0.00</td></tr>
 </tfoot>
<script>
$("#items").on("click", ".payment-row button[name='print']", function() {
  var row= $(this).closest(".payment-row");
  printChargeRecord(row.data("id"));
});
$("#items").on("click", ".payment-row button[name='remove']", function() {
  var row= $(this).closest(".payment-row");
  $.getJSON("api/txn-remove-payment.php?callback=?",
            { txn: $("#txn").data("txn"), id: row.data("id") },
            function (data) {
              if (data.error) {
                $.modal(data.error);
                return;
              }
              updateOrderData(data.txn);
              $("#txn").data("payments", data.payments);
              updateTotal();
            });
});
</script>
 <tbody>
 </tbody>
</table>
</div>
</div>
<?foot();
