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
  width: 80%;
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
.over {
  font-weight: bold;
  color: #600;
}
.discount {
  font-size: smaller;
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

function setQuantity(row, qty) {
  $('.qty', row).text(qty);
  row.data('quantity', qty);
  var ext= qty * row.data('price');
  $('.ext', row).text(ext.toFixed(2));
  if (qty > row.data('stock')) {
    $('.qty', row).addClass('over');
  } else {
    $('.qty', row).removeClass('over');
  }
}
function updatePrice(row, price) {
  //XXX validate price
  if (row.data('price') != price) {
    row.data('price_changed', 1);
  }
  row.data('price', price);
  var ext= row.data('quantity') * price;
  $('.ext', row).text(ext.toFixed(2));
}

function updateName(row, name) {
  if (row.data('name') != name) {
    row.data('name_changed', 1);
  }
  row.data('name', name);
}

function setActiveRow(row) {
  if (lastItem) {
    lastItem.removeClass('active');
  }
  lastItem= row;
  lastItem.addClass('active');
}

$('.price').live('dblclick', function() {
  fld= $('<input type="text" size="6">');
  fld.val($(this).text());

  fld.bind('keypress blur', function(event) {
    if (event.type == 'keypress' && event.which != '13') {
      return true;
    }
  
    price= parseFloat($(this).val());
    prc= $('<span class="price">' + price.toFixed(2) +  '</span>');
    updatePrice($(this).closest('tr'), price);
    $(this).replaceWith(prc);
    updateTotal();

    return true;
  });

  $(this).replaceWith(fld);
  fld.focus().select();
});

$('.name').live('dblclick', function() {
  fld= $('<input type="text" size="40">');
  fld.val($(this).text());

  fld.bind('keypress blur', function(event) {
    if (event.type == 'keypress' && event.which != '13') {
      return true;
    }
  
    name= $(this).val();
    prc= $('<span class="name">' + name +  '</span>');
    updateName($(this).closest('tr'), name);
    $(this).replaceWith(prc);

    return true;
  });

  $(this).replaceWith(fld);
  fld.focus().select();
});

$('.qty').live('dblclick', function() {
  fld= $('<input type="text" size="2">');
  fld.val($(this).text());

  fld.bind('keypress blur', function(event) {
    if (event.type == 'keypress' && event.which != '13') {
      return true;
    }
  
    qty= parseInt($(this).val());
    prc= $('<span class="qty">' + qty +  '</span>');
    setQuantity($(this).closest('tr'), qty);
    $(this).replaceWith(prc);
    updateTotal();

    return true;
  });

  $(this).replaceWith(fld);
  fld.focus().select();
});

$('#tax_rate').live('dblclick', function() {
  fld= $('<input type="text" size="2">');
  fld.val($('#txn').data('tax_rate'));

  fld.bind('keypress blur', function(event) {
    if (event.type == 'keypress' && event.which != '13') {
      return true;
    }
  
    tax_rate= parseFloat($(this).val()).toFixed(2);
    prc= $('<span class="val">' + tax_rate +  '</span>');
    $('#txn').data('tax_rate', tax_rate);
    $(this).replaceWith(prc);
    updateTotal();

    return true;
  });

  $(this).children('.val').replaceWith(fld);
  fld.focus().select();
});


function addItem(item) {
  // check for a matching row
  var row= $("#items tbody tr").filter(function(index) { return $(this).data('code') == item.code; });

  // have one? just increment quantity
  if (row.length) {
    setQuantity(row, row.data('quantity') + item.quantity);
    setActiveRow(row);
  }
  // otherwise add the row
  else {
    // build name/description
    var desc = '<span class="name">' + item.name + '</span><div class="discount">';
    if (item.discount) {
      desc+= 'MSRP $' + item.msrp.toFixed(2) + ' / ' + item.discount;
    }
    desc+= '</div>';

    // add the new row
    row= $('<tr valign="top"><td><a class="remove" href="#"><img src="./icons/tag_blue_delete.png" width=16 height=16 alt="Remove"></a></td><td align="center"><span class="qty">1</span></td><td>' + desc + '</td><td class="dollar right"><span class="price">' + item.price.toFixed(2) + '</span></td><td class="dollar right"><span class="ext">' + item.price.toFixed(2) + '</span></td></tr>');
    row.data(item);
    setQuantity(row, item.quantity); // so 'over' class gets set
    row.appendTo('#items tbody');
    $('.remove', row).click(function () {
      if ($(this).closest('tr').is('.active')) {
        lastItem= null;
      }
      $(this).closest('tr').remove();
      updateTotal();
      return false;
    });
    row.click(function() { setActiveRow($(this)); });
    setActiveRow(row);
  }

  updateTotal();
}

function updateTotal() {
  var total= 0;
  $('#items .ext').each(function() {
    total= total + parseFloat($(this).text());
  });
  $('#items #subtotal').text(total.toFixed(2))
  var tax_rate= $('#txn').data('tax_rate');
  var tax= round_to_even(total * (tax_rate / 100), 2);
  $('#items #tax').text(tax.toFixed(2))
  $('#items #total').text((total + tax).toFixed(2))
}

$(function() {
  $('#txn').data('tax_rate', 0.00);

  $(document).keydown(function(event) {
    var el = $.getFocusedElement();
    if (!el.length) {
      var inp= $('input[name="q"]', this);
      if (event.keyCode != 13) {
        inp.val('');
      }
      inp.focus();
    }
  });

  $('#lookup').submit(function() {
    $('#items .error').hide(); // hide old error messages
    $('input[name="q"]', this).focus().select();

    var q = $('input[name="q"]', this).val();

    // short integer and recently scanned? adjust quantity
    if (q.length < 4 && lastItem && parseInt(q) != 0) {
      snd.yes.play();
      setQuantity(lastItem, parseInt(q));
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
          $('#txn').data('txn', data.details.txn);
          if (data.details.tax_rate) {
            tax_rate= parseFloat(data.details.tax_rate).toFixed(2);
            $('#txn').data('tax_rate', tax_rate)
            prc= $('<span class="val">' + tax_rate +  '</span>');
            $('#txn #tax_rate .val').replaceWith(prc);
          }
          if (data.details.description) {
            $('#txn #description').text(data.details.description);
          }
          if (data.items.length == 0) {
            snd.no.play();
          } else if (data.items.length == 1) {
            snd.yes.play();
            addItem(data.items[0]);
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
});
</script>
<form id="lookup" method="get" action="items.php">
<input type="text" name="q" size="100" autocomplete="off" placeholder="Scan item or enter search terms" value="<?=htmlspecialchars($q)?>">
<input type="submit" value="Find Items">
</form>
<div id="txn">
<h2 id="description">New Sale</h2>
<div id="items">
 <div class="error"></div>
 <table width="80%">
 <thead>
  <tr><th></th><th>Qty</th><th width="50%">Name</th><th>Price</th><th>Ext</th></tr>
 </thead>
 <tfoot>
  <tr><th colspan=3></th><th align="right">Subtotal:</th><td id="subtotal" class="dollar">0.00</td></tr>
  <tr><th colspan=3></th><th align="right" id="tax_rate">Tax (<span class="val">0.00</span>%):</th><td id="tax" class="dollar">0.00</td></tr>
  <tr><th colspan=3></th><th align="right">Total:</th><td id="total" class="dollar">0.00</td></tr>
 </tfoot>
 <tbody>
 </tbody>
</table>
</div>
</div>
<?foot();
