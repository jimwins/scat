<?
require 'scat.php';

head("Sales Report @ Scat", true);
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
        <option value="year">Year</span>
        <option value="hour">Day/Hour</span>
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
<div id="results-template" class="panel panel-default"
     style="display: none; width: auto">
  <div class="panel-heading">
    <button type="button" class="close" data-dismiss="panel"
            onclick="$(this).closest('.panel').remove(); return false"
            title="Close">&times;</button>
    <h3 class="panel-title">All Sales</h3>
  </div>
  <div class="panel-body">
    <div class="chart-container" style="position: relative">
      <canvas class="graph"></canvas>
    </div>
  </div>
  <table class="table">
   <thead>
    <tr>
      <th>When</th>
      <th class="text-right">Subtotal</th>
      <th class="text-right">Transactions</th>
      <th class="text-right">Average</th>
      <th class="text-right">Resale</th>
      <th class="text-right">Tax</th>
      <th class="text-right">Total</th>
    </tr>
   </thead>
   <tbody>
   </tbody>
  </table>
  </div>
</div>
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
  Scat.api('report-sales', params)
      .done(function(data) {
        var table= $("#results-template").clone();
        table.removeAttr('id');
        var t= $("tbody", table);
        var gdata= [];
        $.each(data.sales, function(i, sales) {
          t.append($('<tr><td>' + sales.span +
                     '<td align="right">' + amount(sales.total) +
                     '<td align="right">' + sales.transactions +
                     '<td align="right">' + amount(sales.total / sales.transactions) +
                     '<td align="right">' + amount(sales.resale) +
                     '<td align="right">' + amount(sales.tax) +
                     '<td align="right">' + amount(sales.total_taxed) +
                     '</tr>'));
          gdata.unshift({ x: sales.raw_date, y: sales.total });
        });
        var cap= $('#items').val();
        if (cap) {
          $(".panel-title", table).text(cap);
        }
        table.appendTo($("body"));
        table.show();

        // Only show chart when we have multiple data points
        if (gdata.length > 1) {
          var data= {
            datasets: [{
              label: 'Sales',
              data: gdata
            }]
          };

          var options= {
            legend: {
              display: false,
            },
            scales: {
              xAxes: [{
                type: 'time',
                time: {
                  unit: 'day'
                }
              }],
              yAxes: [{
                ticks: {
                  callback: function(value, index, values) {
                    return amount(value);
                  }
                }
              }]
            },
            tooltips: {
              callbacks: {
                label: function (tooltipItem, data) {
                  return amount(tooltipItem.yLabel);
                }
              }
            }
          };

          var chart= new Chart($('.graph', table)[0],
                               {
                                 type: 'line',
                                 data: data,
                                 options: options
                               });
        } else {
          $('.graph', table).hide();
        }

        table.udraggable({ handle: '.panel-heading' });
      });
});
</script>
