<?
require 'scat.php';

head("report");
?>
<form id="report-params">
From:
<input name="begin" type="date">
to:
<input name="end" type="date">
Grouped by:
<select name="span">
 <option value="day">Day</span>
 <option value="week">Week</span>
 <option value="month">Month</span>
 <option value="all">All</span>
</select>
Items: <input id="items" name="items" type="text">
<input type="submit" value="Show">
</form>
<br>
<table id="results-template" style="display: none">
 <caption><span>All Sales</span><button type="button" class="close" align="right" onclick="$(this).closest('table').remove(); return false" title="Close">&times;</button></caption>
 <thead>
  <tr><th>When</th><th>Subtotal</th><th>Resale</th><th>Tax</th><th>Total</th></tr>
 </thead>
 <tbody>
 </tbody>
</table>
<script>
$(function() {
  $('input[type="date"]').datepicker({ dateFormat: 'yy-mm-dd' });
});
$("#report-params").on('submit', function(ev) {
  ev.preventDefault();
  var params= $(this).serializeArray();
  $.getJSON("./api/report-sales.php?callback=?",
            params,
            function(data) {
              if (data.error) {
                $.modal(data.error);
              } else {
                var table= $("#results-template").clone();
                table.removeAttr('id');
                var t= $("tbody", table);
                $.each(data.sales, function(i, sales) {
                  t.append($('<tr><td>' + sales.span +
                             '<td align="right">' + amount(sales.total) +
                             '<td align="right">' + amount(sales.resale) +
                             '<td align="right">' + amount(sales.tax) +
                             '<td align="right">' + amount(sales.total_taxed) +
                             '</tr>'));
                });
                var cap= $('#items').val();
                if (cap) {
                  $("caption span", table).text(cap);
                  $("caption span", table).editable(function(v,s) { return v; });
                }
                table.appendTo($("body"));
                table.show();
                table.draggable();
              }
            });
});
</script>
<?
foot();
