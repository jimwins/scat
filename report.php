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
</select>
<input type="submit" value="Show">
</form>
<br>
<table id="report">
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
  $.getJSON("./api/report-sales.php?callback=?",
            $(this).serializeArray(),
            function(data) {
              if (data.error) {
                $.modal(data.error);
              } else {
                var t= $("#report tbody");
                t.empty();
                $.each(data.sales, function(i, sales) {
                  t.append($('<tr><td>' + sales.span +
                             '<td align="right">' + amount(sales.total) +
                             '<td align="right">' + amount(sales.resale) +
                             '<td align="right">' + amount(sales.tax) +
                             '<td align="right">' + amount(sales.total_taxed) +
                             '</tr>'));
                });
              }
            });
});
</script>
<?
foot();
