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
tfoot td {
  background-color:rgba(255,255,255,0.5);
  font-weight: bold;
}
</style>
<script>
var snd= new Object;
snd.yes= new Audio("./sound/yes.wav");
snd.no= new Audio("./sound/no.wav");
snd.maybe= new Audio("./sound/maybe.wav");

$.expr[":"].match = function(obj, index, meta, stack){
    return (obj.textContent || obj.innerText || $(obj).text() || "") == meta[3];
}

$.getFocusedElement = function() {
  var elem = document.activeElement;
  return $( elem && ( elem.type || elem.href ) ? elem : [] );
};

var lastItem;

function addItem(item) {
  // look for matching row
  var row= $("#items td:match(" + item.code + ")").parent()

  // have one? just increment quantity
  if (row.length) {
    var qty= parseInt($('.qty', row).text());
    $('.qty', row).text(++qty);
    var ext= qty * parseFloat($(row).children(":eq(3)").text());
    $(row).children(":eq(4)").text(ext.toFixed(2));
    lastItem= row;
  }
  // otherwise add the row
  else {
    // build name/description
    var desc = item.name;
    if (item.discount) {
      desc+= '<br><small>MSRP $' + item.msrp + ' / ' + item.discount + '</small>';
    }

    // add the new row
    $('#items tbody').append('<tr valign="top"><td align="center"><a style="float:left; text-align: left" onclick="$(this).parent().parent().remove(); updateTotal(); return false"><img src="./icons/tag_blue_delete.png" width=16 height=16 alt="Remove"></a><span class="qty">1</span></td><td align="center">' + item.code + '</td><td>' + desc + '</td><td class="dollar right">' + item.price + '</td><td class="dollar right ext">' + item.price + '</td></tr>');
    lastItem= $('#items tbody tr:last');
  }

  updateTotal();
}

function updateTotal() {
  var total= 0;
  $('#items .ext').each(function() {
    total= total + parseFloat($(this).text());
  });
  $('#items #subtotal').text(total.toFixed(2))
  var tax= total * 0.0975;
  $('#items #tax').text(tax.toFixed(2))
  $('#items #total').text((total + tax).toFixed(2))
}

$(function() {
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
    if (q.length < 3 && lastItem && parseInt(q) > 0) {
      $('.qty', lastItem).text(parseInt(q));
      snd.yes.play();
      updateTotal();
      return false;
    }

    // go find!
    $.ajax({
      url: "api/item-find.php",
      dataType: "json",
      data: ({ q: q }),
      success: function(data) {
        if (data.error) {
          snd.no.play();
          $("#items .error").html("<p>" + data.error + "</p>");
          $("#items .error").show();
        } else {
          if (data.length == 0) {
            snd.no.play();
          } else if (data.length == 1) {
            snd.yes.play();
            addItem(data[0]);
          } else {
            snd.maybe.play();
            var choices= $('<div class="choices"/>');
            choices.append('<span onclick="$(this).parent().remove(); return false"><img src="icons/control_eject_blue.png" style="vertical-align:absmiddle" width=16 height=16 alt="Skip"></span>');
            $.each(data, function(i,item) {
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
<div id="items">
 <div class="error"></div>
 <table width="80%">
 <thead>
  <tr><th>Qty</th><th>Code</th><th width="50%">Name</th><th>Price</th><th>Ext</th></tr>
 </thead>
 <tfoot>
  <tr><th colspan=3></th><th align="right">Subtotal:</th><td id="subtotal" class="dollar">0.00</td></tr>
  <tr><th colspan=3></th><th align="right">Tax:</th><td id="tax" class="dollar">0.00</td></tr>
  <tr><th colspan=3></th><th align="right">Total:</th><td id="total" class="dollar">0.00</td></tr>
 </tfoot>
 <tbody>
 </tbody>
</table>
</div>
