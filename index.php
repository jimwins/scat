<?
require 'scat.php';

head("Scat");
?>
<script>
var snd= new Object;
snd.yes= new Audio("./sound/yes.wav");
snd.no= new Audio("./sound/no.wav");
snd.maybe= new Audio("./sound/maybe.wav");

$.expr[":"].match = function(obj, index, meta, stack){
    return (obj.textContent || obj.innerText || $(obj).text() || "") == meta[3];
}

var lastItem;

$(function() {
  $('#lookup').submit(function() {
    $('#items .error').hide(); // hide old error messages
    $('#lookup input[name="q"]').focus().select();

    var q = $('#lookup input[name="q"]').val();

    // short integer and recently scanned? adjust quantity
    if (q.length < 3 && lastItem && parseInt(q) > 0) {
      $(lastItem).children(":eq(0)").text(parseInt(q));
      return false;
    }

    // add to existing line item?

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

            // look for matching row
            var row= $("#items td:match(" + data[0].code + ")").parent()

            // have one? just increment quantity
            if (row.length) {
              var qty= parseInt($(row).children(":eq(0)").text());
              $(row).children(":eq(0)").text(++qty);
              var ext= qty * parseFloat($(row).children(":eq(3)").text());
              $(row).children(":eq(4)").text(ext.toFixed(2));
              lastItem= row;
            }
            // otherwise add the row
            else {
              // build name/description
              var desc = data[0].name;
              if (data[0].discount) {
                desc+= '<br><small>MSRP $' + data[0].msrp + ' / ' + data[0].discount + '</small>';
              }

              // add the new row
              $('#items tbody').append('<tr valign="top"><td align="center">1</td><td align="center">' + data[0].code + '</td><td>' + desc + '</td><td class="dollar right">' + data[0].price + '</td><td class="dollar right">' + data[0].price + '</td></tr>');
              lastItem= $('#items tbody tr:first');
            }

          } else {
            snd.maybe.play();
          }
        }
      }
    });

    return false;
  });
});
</script>
<form id="lookup" method="get" action="items.php">
<input autofocus type="text" name="q" size="100" autocomplete="off" placeholder="Scan item or enter search terms" value="<?=htmlspecialchars($q)?>">
<input type="submit" value="Find Items">
</form>
<div id="items">
 <div class="error"></div>
 <table width="80%">
 <thead>
  <tr><th>Qty</th><th>Code</th><th width="50%">Name</th><th>Price</th><th>Ext</th></tr>
 </thead>
 <tbody>
 </tbody>
</table>
</div>
