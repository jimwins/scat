<?
require 'scat.php';
require 'lib/txn.php';

head("Scat");
?>
<style>
.choices, .errors {
  margin: 8px 4px;
  padding: 6px;
  background: rgba(0,0,0,0.1);
  border-radius: 4px;
  width: 70%;
  position: relative;
}
.errors {
  background: rgba(64,0,0,0.1);
}
.choices ul { margin: 0; padding-left: 1.2em; list-style: circle; }
.choices li {
  text-decoration: underline;
  color: #339;
  cursor:pointer;
}
.choices img, .errors img {
  position: absolute; bottom: 0.5em; right: 0.5em;
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
#txn.paid #pay,
#txn.paid .choices, #txn.paid .errors
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

#txn h2 {
  margin-bottom: 0;
}
#txn #details {
  margin: 0 0 0.5em;
  font-weight: normal;
  font-size: 1em;
  color: #333;
}

#notes {
  width: 100%;
}

#notes tr {
  vertical-align: top;
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
var lastItem;

function updateItems(items) {
  $.each(items, function(i,item) {
    var row= $("#txn tbody tr:data(line_id=" + item.line_id + ")");
    if (!row.length) {
      addNewItem(item);
    } else {
      row.data(item);
      updateRow(row);
    }
  });
  updateTotal();
}

function updateRow(row) {
  $('.quantity', row).text(row.data('quantity'));
  if (row.data('quantity') > row.data('stock')) {
    $('.quantity', row).parent().addClass('over');
  } else {
    $('.quantity', row).parent().removeClass('over');
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
              if (data.error) {
                $.modal(data.error);
              }
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
    var val= $('<span>Updating</span>');
    val.attr("class", key);
    $(this).replaceWith(val);
    updateValue(row, key, value);

    return false;
  });

  val.replaceWith(fld);
  fld.focus().select();
});

$('.remove').live('click', function() {
  var txn= $('#txn').data('txn');
  var id= $(this).closest('tr').data('line_id');

  $.getJSON("api/txn-remove-item.php?callback=?",
            { txn: txn, id: id },
            function(data) {
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
            });

  return false;
});

function addItem(item) {
  var txn= $("#txn").data("txn");
  $.getJSON("api/txn-add-item.php?callback=?",
            { txn: txn, item: item.id },
            function(data) {
              if (data.error) {
                play("no");
                $.modal(data.error);
              } else if (data.matches) {
                // this shouldn't happen!
                  play("no");
              } else {
                updateOrderData(data.txn);
                updateItems(data.items);
                updateTotal();
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
  square: "Square",
  dwolla: "Dwolla",
  gift: "Gift Card",
  check: "Check",
  discount: "Discount",
  bad: "Bad Debt",
  donation: "Donation",
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

  var paid_date= $('#txn').data('paid_date');
  var paid= $('#txn').data('paid');
  if (paid || paid_date != null) {
    $('#items #due').text(amount(total - paid));
    $('#due-row').show();
  } else {
    $('#due-row').hide();
  }

  var payments= $('#txn').data('payments');
  if (!payments) return;

  $.each(payments, function(i, payment) {
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
}

function updateOrderData(txn) {
  // set transaction data
  $('#txn').data('txn_raw', txn);
  $('#txn').data('txn', txn.id);
  $('#txn').data('subtotal', txn.subtotal)
  $('#txn').data('total', txn.total)
  $('#txn').data('paid', txn.total_paid)
  $('#txn').toggleClass('paid', txn.paid != null);
  $('#txn').data('paid_date', txn.paid)
  var tax_rate= parseFloat(txn.tax_rate).toFixed(2);
  $('#txn').data('tax_rate', tax_rate)
  $('#txn #tax_rate .val').text(tax_rate);
  var type= (txn.total_paid ? 'Invoice' :
             (txn.returned_from ? 'Return' : 'Sale'));
  $('#txn #description').text(type + ' ' +
                              Date.parse(txn.created).toString('yyyy') +
                              '-' + txn.number);
  $('#txn').data('person', txn.person)
  $('#txn #person .val').text(txn.person_name ? txn.person_name : 'Anonymous');
  var format= 'MMM d yyyy h:mmtt';
  var dates= Date.parse(txn.created).toString(format);
  if (txn.filled) {
//    dates = dates + ' / Filled: ' + Date.parse(txn.filled).toString(format);
  }
  if (txn.paid) {
    dates = dates + ' / Paid: ' + Date.parse(txn.paid).toString(format);
  }
  $('#txn #dates').text(dates);
}

var protoNote= $("<tr><td></td><td></td><td></td></tr>");

function loadOrder(data) {
  updateOrderData(data.txn)

  if (data.payments != undefined) {
    $('#txn').data('payments', data.payments);
  }

  if (data.person != undefined) {
    $('#txn').data('person_raw', data.person);
  }

  if (data.items != undefined) {
    $('#txn').data('items', data.items);

    // dump existing item rows
    $("#items tbody tr").remove();

    // load rows
    $.each(data.items, function(i, item) {
      addNewItem(item);
    });
  }

  // update notes
  if (data.notes != undefined) {
    $('#txn').data('notes', data.notes);
    $("#notes tbody tr").remove();
    $.each(data.notes, function(i, note) {
      var row= protoNote.clone();
      $("td:nth(1)", row).text(note.entered);
      $("td:nth(2)", row).text(note.content);
      $("#notes tbody").append(row);
    });
  }

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

function txn_add_payment(options) {
  $.ajax({ type: 'GET',
           url: "api/txn-add-payment.php?callback=?",
           dataType: 'json',
           data: options,
           async: false,
           success: function(data) {
              if (data.error) {
                alert(data.error);
              } else {
                updateOrderData(data.txn);
                $('#txn').data('payments', data.payments);
                updateTotal();
                $.modal.close();
                if (options.method == 'credit' && options.amount >= 25.00) {
                  printChargeRecord(data.payment);
                }
              }
           }});
}

function printReceipt() {
  var txn= $('#txn').data('txn');
  if (!txn) {
    $.modal("No sale to print.");
    return false;
  }
  var lpr= $('<iframe id="receipt" src="print/receipt.php?print=1&amp;id=' + txn + '"></iframe>').hide();
  $("#receipt").remove();
  $('body').append(lpr);
  return false;
}

function printInvoice() {
  var txn= $('#txn').data('txn');
  if (!txn) {
    $.modal("No sale to print.");
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
  $('#txn').data('tax_rate', 0.00);

  $(document).keydown(function(ev) {
    if (ev.metaKey || ev.altKey || ev.ctrlKey || ev.shiftKey) {
      return true;
    }
    var el = $.getFocusedElement();
    if (!el.length) {
      var inp= $('input[name="q"]', this);
      if (ev.keyCode != 13) {
        inp.val('');
      }
      inp.focus();
    }
  });

  $(document).bind('keydown', 'meta+p', function(ev) {
    return printReceipt();
  });

  $(document).bind('keydown', 'meta+shift+backspace', function(ev) {
    txn= $('#txn').data('txn');
    if (!txn) {
      return;
    }
    $.getJSON("api/txn-delete?callback=?",
              { txn: txn },
              function(data) {
                if (data.error) {
                  play("no");
                  $.modal(data.error);
                } else {
                  window.location.href= './';
                }
              });
  });

  $('#lookup').submit(function(ev) {
    ev.preventDefault();
    $("#lookup").removeClass("error");

    $('input[name="q"]', this).focus().select();

    var q = $('input[name="q"]', this).val();

    // short integer and recently scanned? adjust quantity
    if (q.length < 4 && lastItem && parseInt(q) != 0) {
      updateValue(lastItem, 'quantity', parseInt(q));
      return false;
    }

    txn= $('#txn').data('txn');

    // go find!
    $.ajax({ type: 'GET',
             url: "api/txn-add-item.php?callback=?",
             dataType: 'json',
             data: { txn: txn, q: q },
             async: false,
             success: function(data) {
                if (data.error) {
                  play("no");
                  $.modal(data.error);
                } else if (data.matches) {
                  if (data.matches.length == 0) {
                    play("no");
                    $("#lookup").addClass("error");
                    var errors= $('<div class="errors"/>');
                    errors.text(" Didn't find anything for '" + q + "'.");
                    errors.prepend('<span onclick="$(this).parent().remove(); return false"><img src="icons/control_eject_blue.png" style="vertical-align:absmiddle" width=16 height=16 alt="Remove"></span>');
                    $("#items").before(errors);
                  } else {
                    play("maybe");
                    var choices= $('<div class="choices"/>');
                    choices.append('<span onclick="$(this).parent().remove(); return false"><img src="icons/control_eject_blue.png" style="vertical-align:absmiddle" width=16 height=16 alt="Skip"></span>');
                    var list= $('<ul>');
                    $.each(data.matches, function(i,item) {
                      var n= $("<li>" + item.name + "</li>");
                      n.click(item, function(ev) {
                        addItem(ev.data);
                        $(this).closest(".choices").remove();
                      });
                      list.append(n);
                    });
                    choices.append(list);
                    $("#items").before(choices);
                  }
                } else {
                  updateOrderData(data.txn);
                  play("yes");
                  updateItems(data.items);
                  updateTotal();
                }
              }});

    return false;
  });

  $("#sidebar button[name='unpaid']").click();

<?
  $id= (int)$_REQUEST['id'];
  $number= (int)$_REQUEST['number'];
  if ($number) {
    $q= "SELECT id FROM txn WHERE type = 'customer' AND number = $number";
    $id= $db->get_one($q);
  }

  if ($id) {
    $data= txn_load_full($db, $id);
    echo 'loadOrder(', json_encode($data), ");\n";
  }
?>
});
</script>
<div id="sidebar">
<button name="unpaid">Unpaid</button>
<button name="recent">Recent</button>
<script>
$("#sidebar button").click(function() {
  var params= {
    open: { type: 'customer', unfilled: true },
    unpaid: { type: 'customer', unpaid: true },
    recent: { type: 'customer', limit: 20 },
  };
  $("#sales").hide();
  $.getJSON("api/txn-list.php?callback=?",
            params[$(this).attr('name')],
            function (data) {
              if (data.error) {
                $.modal(data.error);
              } else {
                showOpenOrders(data);
              }
              $("#status").text("Loaded.").fadeOut('slow');
            });
  $(this).addClass('open');
  $("#status").text("Loading...").show();
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
$("#txn-load").submit(function(ev) {
  ev.preventDefault();
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
  <button id="invoice">Invoice</button>
  <button id="print">Print</button>
  <button id="pay">Pay</button>
  <button id="return">Return</button>
</div>
<script>
$("#invoice").on("click", function() {
  printInvoice();
});
$("#print").on("click", function() {
  if ($("#txn").data("paid_date") != null ||
      confirm("Invoice isn't paid. Sure you want to print?"))
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

              // Show 'Return Credit Card' if it is possible
              var txn_raw= $('#txn').data('txn_raw');
              if (txn_raw.returned_from) {
                $.getJSON("api/txn-load.php?callback=?",
                          { id: txn_raw.returned_from },
                          function (data) {
                            $.each(data.payments, function(i, payment) {
                              if (payment.method == 'credit' &&
                                  payment.amount > 0) {
                                $('#choose-pay-method #credit-refund').show();
                                $('#pay-credit-refund').data('from', payment.id);
                              }
                            });
                          });
              }
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
<style>
#choose-pay-method {
  text-align: center;
}
#choose-pay-method .important {
  font-size: larger;
  font-weight: bold;
}
</style>
<div id="choose-pay-method" style="display: none">
 <button class="important" data-value="cash">Cash</button>
<?if ($DEBUG) {?>
 <button id="credit-refund" class="important" data-value="credit-refund" style="display: none">Refund Credit Card</button>
 <button class="important" data-value="credit">Credit Card</button>
<?}?>
 <button class="important" data-value="credit-manual">Credit Card (Manual)</button>
 <br>
 <button data-value="gift">Gift Card</button>
 <button data-value="check">Check</button>
 <button data-value="square">Square</button>
 <button data-value="dwolla">Dwolla</button>
 <br>
 <button data-value="discount">Discount</button>
 <button data-value="donation">Donation</button>
 <button data-value="bad-debt">Bad Debt</button>
</div>
<script>
$("#choose-pay-method").on("click", "button", function(ev) {
  var method= $(this).data("value");
  $.modal.close();
  var id= "#pay-" + method;
  var due= ($("#txn").data("total") - $("#txn").data("paid")).toFixed(2);
  $(".amount", id).val(due);
  $.modal($(id), { persist: true, overlayClose: false });
  $(".amount", id).focus().select();
});
</script>
<form id="pay-cash" class="pay-method" style="display: none">
 <input class="amount" type="text" pattern="\d*">
 <br>
 <input type="submit" name="Pay">
 <button name="cancel">Cancel</button>
</form>
<script>
$("#pay-cash").on("submit", function (ev) {
  ev.preventDefault();
  var txn= $("#txn").data("txn");
  var amount= $("#pay-cash .amount").val();
  txn_add_payment({ id: txn, method: "cash", amount: amount, change: true });
});
</script>
<form id="pay-credit-refund" class="pay-method" style="display: none">
 <input class="amount" type="text" pattern="\d*">
 <br>
 <input type="submit" value="Refund">
 <button name="cancel">Cancel</button>
</form>
<script>
$("#pay-credit-refund").on("submit", function (ev) {
  ev.preventDefault();
  var txn= $("#txn").data("txn");
  var amount= $("#pay-credit-refund .amount").val();
  var refund_from= $("#pay-credit-refund").data('from');
  $.getJSON("api/cc-refund.php?callback=?",
            { id: txn, amount: parseFloat(amount).toFixed(2),
              from: refund_from },
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
<form id="pay-credit" class="pay-method" style="display: none">
 <input class="amount" type="text" pattern="\d*">
 <br>
 <input type="submit" value="Swipe">
 <button name="cancel">Cancel</button>
</form>
<script>
$("#pay-credit").on("submit", function (ev) {
  ev.preventDefault();
  var txn= $("#txn").data("txn");
  var amount= $("#pay-credit .amount").val();
  $.getJSON("api/cc-begin.php?callback=?",
            { id: txn, amount: parseFloat(amount).toFixed(2) },
            function (data) {
              if (data.error) {
                alert(data.error);
              } else {
                $.modal.close();
                $.modal('<iframe src="' + data.url +
                        '" height=500" width="600" style="border:0">',
                        {
                          closeHTML: "",
                          containerCss: {
                            backgroundColor: "#fff",
                            borderColor: "#fff",
                            height: 520,
                            padding: 0,
                            width: 620,
                          },
                          position: undefined,
                          overlayClose: false,
                        });
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
$("#pay-credit-manual").on("click", "button", function (ev) {
  var txn= $("#txn").data("txn");
  var amount= $("#pay-credit-manual .amount").val();
  var cc_type= $(this).attr('name');
  if (cc_type == 'cancel') {
    $.modal.close();
    return false;
  }
  txn_add_payment({ id: txn, method: "credit", amount: amount, change: false,
                    cc_type: cc_type });
});
</script>
<form id="pay-square" class="pay-method" style="display: none">
 <input class="amount" type="text" pattern="\d*">
 <br>
 <input type="submit" name="Pay">
 <button name="cancel">Cancel</button>
</form>
<script>
$("#pay-square").on("submit", function (ev) {
  ev.preventDefault();
  var txn= $("#txn").data("txn");
  var amount= $("#pay-square .amount").val();
  txn_add_payment({ id: txn, method: "square", amount: amount, change: false });
});
</script>
<form id="pay-dwolla" class="pay-method" style="display: none">
 <input class="amount" type="text" pattern="\d*">
 <br>
 <input type="submit" name="Pay">
 <button name="cancel">Cancel</button>
</form>
<script>
$("#pay-dwolla").on("submit", function (ev) {
  ev.preventDefault();
  var txn= $("#txn").data("txn");
  var amount= $("#pay-dwolla .amount").val();
  txn_add_payment({ id: txn, method: "dwolla", amount: amount, change: false });
});
</script>
<div id="pay-gift" class="pay-method" style="display: none">
 Card: <input class="card" type="text" size="15">
 <br>
 <button name="lookup">Check Card</button>
 <button name="old">Old Card</button>
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
$("#pay-gift").on("click", "button[name='old']", function (ev) {
  var due= ($("#txn").data("total") - $("#txn").data("paid"));
  var def= due;
  $("#pay-gift-complete .amount").val(def);
  $.modal.close();
  $("#pay-gift-complete").data(null);
  $.modal($("#pay-gift-complete"), { overlayClose: false, persist: true });
});
$("#pay-gift-complete").on("click", "button[name='pay']", function (ev) {
  var txn= $("#txn").data("txn");
  var amount= $("#pay-gift-complete .amount").val();
  var card= $("#pay-gift-complete").data('card');
  if (card) {
    $.getJSON("<?=GIFT_BACKEND?>/add-txn.php?callback=?",
              { card: card, amount: -amount },
              function (data) {
                if (data.error) {
                  alert(data.error);
                } else {
                  var balance= $("#pay-gift-complete").data('balance');
                  txn_add_payment({ id: txn, method: "gift", amount: amount,
                                    change: (balance - amount <= 10.00) });
                }
              });
  } else {
    txn_add_payment({ id: txn, method: "gift", amount: amount, change: true });
  }
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
  txn_add_payment({ id: txn, method: "check", amount: amount, change: false });
});
</script>
<form id="pay-discount" class="pay-method" style="display: none">
 <input class="amount" type="text" pattern="\d*">
 <br>
 <button name="pay">Discount</button>
 <button name="cancel">Cancel</button>
</form>
<script>
$("#pay-discount").on("submit", function (ev) {
  ev.preventDefault();
  var txn= $("#txn").data("txn");
  var amount= $("#pay-discount .amount").val();
  txn_add_payment({ id: txn, method: "discount",
                    amount: amount, change: false });
});
</script>
<div id="pay-bad-debt" class="pay-method" style="display: none">
 <input class="amount" type="text" pattern="\d*">
 <br>
 <button name="pay">Pay</button>
 <button name="cancel">Cancel</button>
</div>
<script>
$("#pay-bad-debt").on("click", "button[name='pay']", function (ev) {
  var txn= $("#txn").data("txn");
  var amount= $("#pay-bad-debt .amount").val();
  txn_add_payment({ id: txn, method: "bad", amount: amount, change: false });
});
</script>
<form id="pay-donation" class="pay-method" style="display: none">
 <input class="amount" type="text" pattern="\d*">
 <br>
 <button name="pay">Pay</button>
 <button name="cancel">Cancel</button>
</form>
<script>
$("#pay-donation").on("submit", function (ev) {
  ev.preventDefault();
  var txn= $("#txn").data("txn");
  var amount= $("#pay-donation .amount").val();
  txn_add_payment({ id: txn, method: "donation", amount: amount,
                    change: false });
});
</script>
<script>
$(".pay-method").on("click", "button[name='cancel']", function(ev) {
  ev.preventDefault();
  $.modal.close();
});
</script>
<div id="details">
<h2 id="description">New Sale</h2>
<div id="dates"></div>
<div id="person"><span class="val">Anonymous</span> <img style="vertical-align: text-bottom" id="info-person" src="icons/information.png" width="16" height="16"></div>
</div>
<script>
$("#txn #person").on("dblclick", function(ev) {
  if (typeof $("#txn").data("txn") == "undefined") {
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

    $("#person-create input[name='name']").val($(this).val());
    $("#person-create").modal();

    var val= $(this).data('default');
    $(this).parent().text(val);
    $(this).remove();

    return false;
  });

  fld.autocomplete({
    source: "./api/person-list.php?callback=?",
    minLength: 2,
    select: function(ev, ui) {
      $(this).parent().text(ui.item.value);
      $(this).remove();
      $.getJSON("api/txn-update-person.php?callback=?",
                { txn: $("#txn").data("txn"), person: ui.item.id },
                function (data) {
                  if (data.error) {
                    $.modal(data.error);
                    return;
                  }
                  loadOrder(data);
                });
    },
  });

  $(".val", this).empty().append(fld);
  fld.focus().select();
});
$("#txn #info-person").on("click", function(ev) {
  var person= $('#txn').data('person');
  if (!person)
    return false;
  $.getJSON("api/person-load.php?callback=?",
            { person: person },
            function (data) {
              if (data.error) {
                $.modal(data.error);
                return;
              }
              loadPerson(data.person);
              $.modal($('#person-info'));
            });
});
function loadPerson(person) {
  $('#person-info').data('person', person);
  var active= parseInt(person.active);
  if (active) {
    $('#person-info #active').attr('src', 'icons/accept.png');
  } else {
    $('#person-info #active').attr('src', 'icons/cross.png');
  }
  $('#person-info #name').text(person.name);
  $('#person-info #company').text(person.company);
  $('#person-info #email').text(person.email);
  $('#person-info #phone').text(person.phone);
  $('#person-info #address').text(person.address);
  $('#person-info #tax_id').text(person.tax_id);
}
</script>
<table id="person-info" style="display: none">
  <tr>
   <th>Name:</th>
   <td><span id="name"></span></td>
  </tr>
  <tr>
   <th>Company:</th>
   <td id="company"></td>
  </tr>
  <tr>
   <th>Email:</th>
   <td id="email"></td>
  </tr>
  <tr>
   <th>Phone:</th>
   <td id="phone"></td>
  </tr>
  <tr>
   <th>Address:</th>
   <td id="address"></td>
  </tr>
  <tr>
   <th>Tax ID:</th>
   <td id="tax_id"></td>
  </tr>
</table>
<form id="person-create" style="display:none">
 <label>Name: <input type="text" width="60" name="name"></label>
 <br>
 <label>Company: <input type="text" width="60" name="company"></label>
 <br>
 <label>Email: <input type="text" width="40" name="email"></label>
 <br>
 <label>Phone: <input type="text" width="20" name="phone"></label>
 <br>
 <input type="submit" name="Create">
 <button name="cancel">Cancel</button>
</form>
<script>
$('#person-create').on('submit', function(ev) {
  ev.preventDefault();

  var data= {
    name: $("input[name='name']", this).val(),
    company: $("input[name='company']", this).val(),
    email: $("input[name='email']", this).val(),
    phone: $("input[name='phone']", this).val(),
  };

  $.getJSON("api/person-add.php?callback=?",
            data,
            function (data) {
              if (data.error) {
                alert(data.error);
                return;
              }
              $.getJSON("api/txn-update-person.php?callback=?",
                        { txn: $("#txn").data("txn"), person: data.person },
                        function (data) {
                          if (data.error) {
                            alert(data.error);
                            return;
                          }
                          updateOrderData(data.txn);
                          $.modal.close();
                        });
            });
});
$('#person-create').on('click', "button[name='cancel'", function(ev) {
  ev.preventDefault();
  $.modal.close();
});
</script>
<table id="items" width="100%">
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
$('#tax_rate .val').editable(function(value, settings) {
  var txn= $('#txn').data('txn');

  $.getJSON("api/txn-update-tax-rate.php?callback=?",
            { txn: txn, tax_rate: value },
            function (data) {
              if (data.error) {
                $.modal(data.error);
                return;
              }
              updateOrderData(data.txn);
              updateTotal();
            });
  return "...";
}, { event: 'dblclick', style: 'display: inline' });
</script>
 <tbody>
 </tbody>
</table>
<table id="notes">
 <thead>
  <tr><th style="width: 20px"><img src="icons/note_add.png" width="16" height="16"></th><th style="width: 10em">Date</th><th>Note</th></tr>
 </thead>
 <tbody>
 </tbody>
</table>
<form id="add-note" style="display: none">
  <input type="text" name="note" size="40">
  <input type="submit" value="Add">
</form>
<script>
$("#notes img").on("click", function(ev) {
  var txn= $("#txn").data("txn");
  if (!txn) return;
  $.modal($("#add-note"));
});
$("#add-note").on("submit", function(ev) {
  ev.preventDefault();

  var txn= $("#txn").data("txn");
  var note= $('input[name="note"]', this).val();
  $.getJSON("api/txn-add-note.php?callback=?",
            { id: txn, note: note},
            function (data) {
              loadOrder(data);
              $.modal.close();
            });
});
</script>
</div>
<?foot();
