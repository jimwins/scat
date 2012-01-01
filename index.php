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

#pay {
  float: right;
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
  if (ext < 0.0) {
    ext= '($' + Math.abs(ext).toFixed(2) + ')';
  } else {
    ext= '$' + ext.toFixed(2);
  }
  $('.ext', row).text(ext);
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
  $.ajax({
    url: "api/txn-add-item.php",
    dataType: "json",
    data: ({ txn: txn, id: item.id }),
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

var paymentRow= $('<tr class="payment-row"><th colspan=4></th><th class="payment-method" align="right">Method:</th><td class="payment-amount" align="right">$0.00</td></tr>');

var paymentMethods= {
  cash: "Cash",
  change: "Change",
  credit: "Credit Card",
  gift: "Gift Card",
  check: "Check",
  discount: "Discount",
};

function updateTotal() {
  var total= $("#txn").data("total");;
  var subtotal= $("#txn").data("subtotal");;
  $('#items #subtotal').text(subtotal.toFixed(2))
  var tax_rate= $('#txn').data('tax_rate');
  var tax= total - subtotal;
  $('#items #tax').text(tax.toFixed(2))
  $('#items #total').text(total.toFixed(2))

  $('.payment-row').remove();
  $.each($('#txn').data('payments'), function(i, payment) {
    var row= paymentRow.clone();
    $('.payment-method', row).text(paymentMethods[payment.method] + ':');
    var amount= payment.amount;
    if (amount < 0.0) {
      amount= '($' + Math.abs(amount).toFixed(2) + ')';
    } else {
      amount= '$' + amount.toFixed(2);
    }
    $('.payment-amount', row).text(amount);
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
                { id: txn.id },
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
    var txn= $('#txn').data('txn');
    if (!txn) {
      $.modal("No sale to print.");
      return false;
    }
    var lpr= $('<iframe id="receipt" src="receipt.php?print=1&amp;id=' + txn + '"></iframe>').hide();
    $("#receipt").remove();
    $('body').append(lpr);
    return false;
  });

  $('#lookup').submit(function() {
    $('#items .error').hide(); // hide old error messages
    $('input[name="q"]', this).focus().select();

    var q = $('input[name="q"]', this).val();

    // short integer and recently scanned? adjust quantity
    if (q.length < 4 && lastItem && parseInt(q) != 0) {
      snd.yes.play();
      updateValue(lastItem, 'quantity', parseInt(q));
      updateTotal();
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

  // Load open sales
  $("#sales caption").click(function() {
    if ($(this).hasClass('open')) {
      $(this).removeClass('open');
      $('tbody', $(this).parent()).empty();
    } else {
      $.getJSON("api/txn-list.php?callback=?",
                { type: 'customer', unfilled: true },
                function (data) {
                  if (data.error) {
                    $.modal(data.error);
                  } else {
                    showOpenOrders(data);
                  }
                  $("#status").text("Loaded open sales.").fadeOut('slow');
                });
      $(this).addClass('open');
      $("#status").text("Loading open sales...").show();
    }
  });
});
</script>
<div id="sidebar">
<table id="sales" width="100%">
 <caption>Open Sales</caption>
 <thead>
  <tr><th>#</th><th>Date/Name</th><th>Items</th></tr>
 </thead>
 <tbody>
 </tbody>
</table>
</div>
<form id="lookup" method="get" action="items.php">
<input type="text" name="q" size="100" autocomplete="off" placeholder="Scan item or enter search terms" value="">
<input type="submit" value="Find Items">
</form>
<div id="txn" class="disabled">
<button id="pay">Pay</button>
<script>
$("#pay").on("click", function() {
  var txn= $('#txn').data('txn');
  $.getJSON("api/txn-allocate.php?callback=?",
            { txn: txn },
            function (data) {
              if (data.error) {
                $.modal(data.error);
              }
              $.modal($("#payment-methods"), { persist: true});
            });
});
</script>
<div id="payment-methods" style="display: none">
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
$("#payment-methods").on("click", "button", function(ev) {
  var method= $(this).data("value");
  $.modal.close();
  var id= "#pay-" + method;
  var due= ($("#txn").data("total") - $("#txn").data("paid")).toFixed(2);
  $(".amount", id).val(due);
  $.modal($(id), { overlayClose: false });
  $(".amount", id).focus().select();
});
$(".pay-method").on("click", "button[name='cancel']", function(ev) {
  $.modal.close();
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
<h2 id="description">New Sale</h2>
<div id="items">
 <div class="error"></div>
 <table width="100%">
 <thead>
  <tr><th></th><th>Qty</th><th>Code</th><th width="50%">Name</th><th>Price</th><th>Ext</th></tr>
 </thead>
 <tfoot>
  <tr id="subtotal-row"><th colspan=4></th><th align="right">Subtotal:</th><td id="subtotal" class="dollar">0.00</td></tr>
  <tr id="tax-row"><th colspan=4></th><th align="right" id="tax_rate">Tax (<span class="val">0.00</span>%):</th><td id="tax" class="dollar">0.00</td></tr>
  <tr id="total-row"><th colspan=4></th><th align="right">Total:</th><td id="total" class="dollar">0.00</td></tr>
  <tr id="due-row" style="display:none"><th colspan=4></th><th align="right">Due:</th><td id="due" class="dollar">0.00</td></tr>
 </tfoot>
 <tbody>
 </tbody>
</table>
</div>
</div>
<?foot();
