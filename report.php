<?
require 'scat.php';

head("Sales Report @ Scat");
?>
<form id="report-params" class="form-horizontal" role="form">
  <div class="form-group">
    <label for="datepicker" class="col-sm-2 control-label">
      Dates
    </label>
    <div class="col-sm-10">
      <div class="input-daterange input-group" id="datepicker">
        <input type="text" class="form-control" name="begin" />
        <span class="input-group-addon">to</span>
        <input type="text" class="form-control" name="end" />
      </div>
    </div>
  </div>
  <div class="form-group">
    <label for="span" class="col-sm-2 control-label">
      Grouped by
    </label>
    <div class="col-sm-10">
      <select name="span" class="form-control" style="width: auto">
        <option value="day">Day</span>
        <option value="week">Week</span>
        <option value="month">Month</span>
        <option value="all">All</span>
      </select>
    </div>
  </div>
  <div class="form-group">
    <label for="items" class="col-sm-2 control-label">
      Items
    </label>
    <div class="col-sm-10">
      <input id="items" name="items" type="text" class="form-control" style="width: 20em">
    </div>
  </div>
  <div class="form-group">
    <div class="col-sm-offset-2 col-sm-10">
      <input type="submit" class="btn btn-primary" value="Show">
    </div>
  </div>
</form>
<br>
<table id="results-template" class="table" style="display: none; width: auto">
 <caption><span>All Sales</span><button type="button" class="close" align="right" onclick="$(this).closest('table').remove(); return false" title="Close">&times;</button></caption>
 <thead>
  <tr><th>When</th><th>Subtotal</th><th>Resale</th><th>Tax</th><th>Total</th></tr>
 </thead>
 <tbody>
 </tbody>
</table>
<?
foot();
?>
<script>
$(function() {
  $('#report-params .input-daterange').datepicker({
      format: "yyyy-mm-dd",
      todayHighlight: true
  });
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
