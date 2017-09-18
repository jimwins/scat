<?
require 'scat.php';

head("Daily Summary @ Scat", true);

$date= $_REQUEST['date'];
if (!$date) {
  $date= (new \Datetime())->format('Y-m-d');
}

$q= "SELECT SUM(taxed + untaxed) AS sales,
            SUM(ROUND_TO_EVEN(taxed * (tax_rate / 100), 2)) AS tax,
            SUM(ROUND_TO_EVEN(taxed * (1 + (tax_rate / 100)), 2) + untaxed)
              AS total
       FROM (SELECT 
                    filled,
                    CAST(ROUND_TO_EVEN(
                      SUM(IF(txn_line.taxfree, 1, 0) *
                        IF(type = 'customer', -1, 1) * ordered *
                        sale_price(txn_line.retail_price,
                                   txn_line.discount_type,
                                   txn_line.discount)),
                      2) AS DECIMAL(9,2))
                    AS untaxed,
                    CAST(ROUND_TO_EVEN(
                      SUM(IF(txn_line.taxfree, 0, 1) *
                        IF(type = 'customer', -1, 1) * ordered *
                        sale_price(txn_line.retail_price,
                                   txn_line.discount_type,
                                   txn_line.discount)),
                      2) AS DECIMAL(9,2))
                    AS taxed,
                    tax_rate
               FROM txn
               LEFT JOIN txn_line ON (txn.id = txn_line.txn)
                    JOIN item ON (txn_line.item = item.id)
              WHERE filled IS NOT NULL
                AND filled BETWEEN '$date' AND '$date' + INTERVAL 1 DAY
                AND type = 'customer'
                AND code NOT LIKE 'ZZ-gift%'
              GROUP BY txn.id
            ) t";

$sales= $db->get_one_assoc($q);
?>
<form id="report-params" role="form">
  <div class="input-group col-sm-6">
    <span class="input-group-addon">Date</span>
    <div class="input-daterange" id="datepicker">
      <input type="text" class="form-control" name="date" value="<?=ashtml($date)?>" />
    </div>
    <div class="input-group-btn">
      <input type="submit" class="btn btn-primary" value="Show">
    </div>
  </div>
</form>
<script>
$(function() {
  $('#report-params .input-daterange').datepicker({
      format: "yyyy-mm-dd",
      todayHighlight: true
  });
});
</script>
<br>

<div class="row text-center">
  <div class="col-sm-4">
    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">
          Sales
        </h3>
      </div>
      <div class="panel-body">
        <span style="font-size: larger"><?=amount($sales['sales'])?></span>
      </div>
    </div>
  </div>

  <div class="col-sm-4">
    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">
          Tax
        </h3>
      </div>
      <div class="panel-body">
        <span style="font-size: larger"><?=amount($sales['tax'])?></span>
      </div>
    </div>
  </div>

  <div class="col-sm-4">
    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">
          Total Collected
        </h3>
      </div>
      <div class="panel-body">
        <span style="font-size: larger"><?=amount($sales['total'])?></span>
      </div>
    </div>
  </div>
</div>

<div class="row text-center">
  <div class="col-sm-12">
    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">
          Hourly Sales
        </h3>
      </div>
      <div class="panel-body">
        <div class="chart-container" style="position: relative">
          <canvas id="hourly-sales-chart"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>
<?
function get_sales_data($db, $format, $begin, $end= null) {
  $begin= "'" . $db->escape($begin) . "'";
  if (!$end) {
    $end= "$begin + INTERVAL 1 DAY";
  } else {
    $end= "'" . $db->escape($end) . "' + INTERVAL 1 DAY";
  }

  $q= "SELECT DATE_FORMAT(filled, '$format') AS span,
              SUM(taxed + untaxed) AS total,
              SUM(IF(tax_rate, 0, taxed + untaxed)) AS resale,
              SUM(ROUND_TO_EVEN(taxed * (tax_rate / 100), 2)) AS tax,
              SUM(ROUND_TO_EVEN(taxed * (1 + (tax_rate / 100)), 2) + untaxed)
                AS total_taxed,
              MIN(DATE(filled)) AS raw_date
         FROM (SELECT 
                      filled,
                      CAST(ROUND_TO_EVEN(
                        SUM(IF(txn_line.taxfree, 1, 0) *
                          IF(type = 'customer', -1, 1) * ordered *
                          sale_price(txn_line.retail_price,
                                     txn_line.discount_type,
                                     txn_line.discount)),
                        2) AS DECIMAL(9,2))
                      AS untaxed,
                      CAST(ROUND_TO_EVEN(
                        SUM(IF(txn_line.taxfree, 0, 1) *
                          IF(type = 'customer', -1, 1) * ordered *
                          sale_price(txn_line.retail_price,
                                     txn_line.discount_type,
                                     txn_line.discount)),
                        2) AS DECIMAL(9,2))
                      AS taxed,
                      tax_rate
                 FROM txn
                 LEFT JOIN txn_line ON (txn.id = txn_line.txn)
                      JOIN item ON (txn_line.item = item.id)
                WHERE filled IS NOT NULL
                  AND filled BETWEEN $begin AND $end
                  AND type = 'customer'
                  AND code NOT LIKE 'ZZ-gift%'
                GROUP BY txn.id
              ) t
        GROUP BY 1 DESC";

  $r= $db->query($q)
    or die_query($db, $q);

  $data= array();
  while (($row= $r->fetch_assoc())) {
    $data[]= array('x' => $row['span'],
                   'y' => (float)$row['total']);
  }

  return $data;
}
?>
<script>
$(function() {
var data= {
  datasets: [{
    label: 'Sales',
    data: <?=json_encode(get_sales_data($db, '%Y-%m-%d %H:00', $date))?>
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
        unit: 'hour',
        min: '<?=$date?> 09:30',
        max: '<?=$date?> 19:30'
      },
      gridLines: {
        offsetGridLines: true
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
    intersect: false,
    callbacks: {
      label: function (tooltipItem, data) {
        return amount(tooltipItem.yLabel);
      }
    }
  }
};

var hourlySalesChart= new Chart(document.getElementById('hourly-sales-chart'), {
                               type: 'bar',
                               data: data,
                               options: options
                         });

});
</script>
<div class="row text-center">
  <div class="col-sm-6">
    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">
          Last 7 Days
        </h3>
      </div>
      <div class="panel-body">
        <div class="chart-container" style="position: relative">
          <canvas id="daily-sales-chart"></canvas>
        </div>
      </div>
    </div>
  </div>
<script>
$(function() {

var data= {
  datasets: [{
    label: 'Sales',
<?
$before= new Datetime($date);
$before->sub(new DateInterval('P8D'));
?>
    data: <?=json_encode(get_sales_data($db, '%Y-%m-%d', $before->format('Y-m-d'), $date))?>
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
      },
      gridLines: {
        offsetGridLines: true
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
    intersect: false,
    callbacks: {
      label: function (tooltipItem, data) {
        return amount(tooltipItem.yLabel);
      }
    }
  }
};

var dailySalesChart= new Chart(document.getElementById('daily-sales-chart'), {
                               type: 'bar',
                               data: data,
                               options: options
                         });

});
</script>

  <div class="col-sm-6">
    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">
          Comparison to Average
        </h3>
      </div>
      <div class="panel-body">
        <div class="chart-container" style="position: relative">
          <canvas id="comparison-chart"></canvas>
        </div>
      </div>
    </div>
  </div>
<?
$q= "SELECT AVG(sales) FROM (SELECT DATE(filled),
            SUM(taxed + untaxed) AS sales
       FROM (SELECT 
                    filled,
                    CAST(ROUND_TO_EVEN(
                      SUM(IF(txn_line.taxfree, 1, 0) *
                        IF(type = 'customer', -1, 1) * ordered *
                        sale_price(txn_line.retail_price,
                                   txn_line.discount_type,
                                   txn_line.discount)),
                      2) AS DECIMAL(9,2))
                    AS untaxed,
                    CAST(ROUND_TO_EVEN(
                      SUM(IF(txn_line.taxfree, 0, 1) *
                        IF(type = 'customer', -1, 1) * ordered *
                        sale_price(txn_line.retail_price,
                                   txn_line.discount_type,
                                   txn_line.discount)),
                      2) AS DECIMAL(9,2))
                    AS taxed,
                    tax_rate
               FROM txn
               LEFT JOIN txn_line ON (txn.id = txn_line.txn)
                    JOIN item ON (txn_line.item = item.id)
              WHERE filled IS NOT NULL
                AND filled BETWEEN '$date' - INTERVAL 3 MONTH AND '$date' + INTERVAL 1 DAY
                AND DAYOFWEEK(filled) = DAYOFWEEK('$date')
                AND type = 'customer'
                AND code NOT LIKE 'ZZ-gift%'
              GROUP BY txn.id
            ) t
        GROUP BY 1) s";
$same_day= $db->get_one($q);

$q= "SELECT AVG(sales) FROM (SELECT DATE(filled),
            SUM(taxed + untaxed) AS sales
       FROM (SELECT 
                    filled,
                    CAST(ROUND_TO_EVEN(
                      SUM(IF(txn_line.taxfree, 1, 0) *
                        IF(type = 'customer', -1, 1) * ordered *
                        sale_price(txn_line.retail_price,
                                   txn_line.discount_type,
                                   txn_line.discount)),
                      2) AS DECIMAL(9,2))
                    AS untaxed,
                    CAST(ROUND_TO_EVEN(
                      SUM(IF(txn_line.taxfree, 0, 1) *
                        IF(type = 'customer', -1, 1) * ordered *
                        sale_price(txn_line.retail_price,
                                   txn_line.discount_type,
                                   txn_line.discount)),
                      2) AS DECIMAL(9,2))
                    AS taxed,
                    tax_rate
               FROM txn
               LEFT JOIN txn_line ON (txn.id = txn_line.txn)
                    JOIN item ON (txn_line.item = item.id)
              WHERE filled IS NOT NULL
                AND filled BETWEEN '$date' - INTERVAL 7 DAY AND '$date' + INTERVAL 1 DAY
                AND type = 'customer'
                AND code NOT LIKE 'ZZ-gift%'
              GROUP BY txn.id
            ) t
        GROUP BY 1) s";

$last_week= $db->get_one($q);
?>
<script>
$(function() {

var data= {
  labels: [ 'Today', 'Same Weekday', 'Last Week' ],
  datasets: [{
    label: 'Sales',
    data: [ <?=$sales['sales']?>, <?=$same_day?>, <?=$last_week?> ]
  }]
};

var options= {
  legend: {
    display: false,
  },
  scales: {
    xAxes: [{
      ticks: {
        beginAtZero: true,
        callback: function(value, index, values) {
          return amount(value);
        }
      }
    }]
  },
  tooltips: {
    callbacks: {
      label: function (tooltipItem, data) {
        return amount(tooltipItem.xLabel);
      }
    }
  }
};

var comparisonChart= new Chart(document.getElementById('comparison-chart'), {
                               type: 'horizontalBar',
                               data: data,
                               options: options
                         });

});
</script>

</div>

<div class="row">
  <div class="col-sm-12">
    <div class="panel panel-default">
      <div class="panel-heading text-center">
        <h3 class="panel-title">
          Top Selling Items
        </h3>
      </div>
<?
$q= "SELECT
            item.code Code\$item,
            item.name Name\$name,
            SUM(-1 * allocated) Sold,
            AVG(sale_price(txn_line.retail_price, txn_line.discount_type,
                           txn_line.discount)) AvgPrice\$dollar,
            SUM(-1 * allocated * sale_price(txn_line.retail_price,
                                            txn_line.discount_type,
                                            txn_line.discount)) Total\$dollar
       FROM txn
       LEFT JOIN txn_line ON txn.id = txn_line.txn
       LEFT JOIN item ON txn_line.item = item.id
       LEFT JOIN brand ON item.brand = brand.id
      WHERE type = 'customer'
        AND filled BETWEEN '$date' AND '$date' + INTERVAL 1 DAY
      GROUP BY 1
      ORDER BY 5 DESC
      LIMIT 12";

dump_table($db->query($q));
?>
    </div>
  </div>
</div>
<?

foot();

