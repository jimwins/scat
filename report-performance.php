<?
require 'scat.php';
require 'lib/item.php';

$items= $_REQUEST['items'];
if (!$items && ($product= (int)$_REQUEST['product'])) {
  $items= "product:$product";
}

if (($saved= (int)$_GET['saved']) && !$items) {
  $items= $db->get_one("SELECT search FROM saved_search WHERE id = $saved");
}

$sql_criteria= "1=1";
if ($items) {
  list($sql_criteria, $x)= item_terms_to_sql($db, $items,
                                             FIND_ALL|FIND_LIMITED);
}

$begin= $_REQUEST['begin'];
$end= $_REQUEST['end'];

if (!$begin) {
  $begin= date('Y-m-d', time() - 365.25*24*3600);
} else {
  $begin= $db->escape($begin);
}

if (!$end) {
  $end= date('Y-m-d', time());
} else {
  $end= $db->escape($end);
}

head("Performance @ Scat", true);

?>
<form id="report-params" class="form-horizontal" role="form"
      action="<?=$_SERVER['PHP_SELF']?>">
  <div class="form-group">
    <label for="datepicker" class="col-sm-2 control-label">
      Dates
    </label>
    <div class="col-sm-10">
      <div class="input-daterange input-group" id="datepicker">
        <input type="text" class="form-control" name="begin"
               value="<?=ashtml($begin)?>" />
        <span class="input-group-addon">to</span>
        <input type="text" class="form-control" name="end"
               value="<?=ashtml($end)?>" />
      </div>
    </div>
  </div>
  <div class="form-group">
    <label for="items" class="col-sm-2 control-label">
      Items
    </label>
    <div class="col-sm-10">
      <input id="items" name="items" type="text"
             class="form-control" style="width: 20em"
             value="<?=ashtml($items)?>">
    </div>
  </div>
  <div class="form-group">
    <div class="col-sm-offset-2 col-sm-10">
      <input type="submit" class="btn btn-primary" value="Show">
    </div>
  </div>
</form>
<div id="results">
<?
if ($product) {
  $name= $db->get_one("SELECT name FROM product WHERE id = $product");
  echo '<div class="page-header"><h2>', ashtml($name), '</h2></div>';
}

$q= "SELECT SUM(ordered *
                sale_price(txn_line.retail_price, txn_line.discount_type,
                           txn_line.discount)) total
       FROM txn 
       JOIN txn_line ON (txn.id = txn_line.txn)
       JOIN item ON (txn_line.item = item.id)
       LEFT JOIN brand ON item.brand = brand.id
      WHERE type = 'vendor'
        AND ($sql_criteria)
        AND created BETWEEN '$begin' AND '$end' + INTERVAL 1 DAY";

$purchased= $db->get_one($q);

$q= "SELECT SUM(ordered * -1 *
                sale_price(txn_line.retail_price, txn_line.discount_type,
                           txn_line.discount)) total
       FROM txn 
       JOIN txn_line ON (txn.id = txn_line.txn)
       JOIN item ON (txn_line.item = item.id)
       LEFT JOIN brand ON item.brand = brand.id
      WHERE type = 'customer'
        AND ($sql_criteria)
        AND filled BETWEEN '$begin' AND '$end' + INTERVAL 1 DAY";

$sold= $db->get_one($q);

$q= "SELECT SUM((SELECT SUM(allocated) FROM txn_line WHERE item = item.id) *
                sale_price(item.retail_price, item.discount_type,
                           item.discount))
       FROM item
       LEFT JOIN brand ON item.brand = brand.id
      WHERE ($sql_criteria)";

$stock= $db->get_one($q);

$q= "SELECT SUM(minimum_quantity *
                sale_price(item.retail_price, item.discount_type,
                           item.discount))
       FROM item
       LEFT JOIN brand ON item.brand = brand.id
      WHERE ($sql_criteria) AND item.active";

$ideal= $db->get_one($q);
?>
</div>
<div class="row">
  <div class="col-sm-6">
    <div class="panel panel-default">
      <div class="panel-heading">
        <h1 class="panel-title">Purchased</h1>
      </div>
      <div class="panel-body text-center text-center">
        <span style="font-size: 300%">
          <?=amount($purchased)?>
        </span>
      </div>
    </div>
  </div>
  <div class="col-sm-6">
    <div class="panel panel-default">
      <div class="panel-heading">
        <h1 class="panel-title">Sold</h1>
      </div>
      <div class="panel-body text-center">
        <span style="font-size: 300%">
          <?=amount($sold)?>
        </span>
      </div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="panel panel-default">
      <div class="panel-heading">
        <h1 class="panel-title">Stock</h1>
      </div>
      <div class="panel-body text-center">
        <span style="font-size: 300%">
          <?=amount($stock)?>
        </span>
      </div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="panel panel-default">
      <div class="panel-heading">
        <h1 class="panel-title">Ideal</h1>
      </div>
      <div class="panel-body text-center">
        <span style="font-size: 300%">
          <?=amount($ideal)?>
        </span>
      </div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="panel panel-default">
      <div class="panel-heading">
        <h1 class="panel-title">Turns</h1>
      </div>
      <div class="panel-body text-center">
        <span style="font-size: 300%">
          <?=sprintf('%.2f', $sold/$ideal)?>
        </span>
      </div>
    </div>
  </div>
</div>
<?
$span= $_REQUEST['span'];
if (!$span) $span= 'month';
switch ($span) {
case 'all':
  $format= 'All';
  break;
case 'year':
  $format= '%Y';
  break;
case 'week':
  $format= '%X-W%v';
  break;
case 'hour':
  $format= '%w (%a) %H:00';
  break;
case 'day':
  $format= '%Y-%m-%d %a';
  break;
default:
case 'month':
  $format= '%Y-%m';
  break;
}

$q= "SELECT DATE_FORMAT(created, '$format') AS span,
           SUM(ordered * -1 *
                sale_price(txn_line.retail_price, txn_line.discount_type,
                           txn_line.discount)) total
       FROM txn 
       JOIN txn_line ON (txn.id = txn_line.txn)
       JOIN item ON (txn_line.item = item.id)
       LEFT JOIN brand ON item.brand = brand.id
      WHERE type = 'customer'
        AND ($sql_criteria)
        AND filled BETWEEN '$begin' AND '$end' + INTERVAL 1 DAY
      GROUP BY 1 DESC";

$r= $db->query($q)
  or die_query($db, $q);

$data= "[";
while ($row= $r->fetch_assoc()) {
  $data.= "{ x: '{$row['span']}', y: {$row['total']} },";
}
$data.= "]";
?>
<div class="panel panel-default">
  <div class="panel-heading">
    <h1 class="panel-title">Sales</h1>
  </div>
  <div class="panel-body">
    <div class="chart-container" style="position: relative">
     <canvas id="sales-chart"></canvas>
    </div>
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

  var data= {
    datasets: [{
      label: 'Sales',
      data: <?=$data?>
    }]
  };

  var options= {
    legend: false,
    scales: {
      xAxes: [{
        type: 'time',
        time: {
          unit: '<?=$span?>',
          min: '<?=$begin?>',
          max: '<?=$end?>'
        },
        barPercentage: 1.0,
        categoryPercentage: 1.0,
        barThickness: 50,
      }],
      yAxes: [{
        position: 'left',
        ticks: {
          callback: function(value, index, values) {
            return amount(value);
          }
        }
      }],
    },
    tooltips: {
      intersect: false,
      callbacks: {
        label: function (tooltipItem, data) {
          return (tooltipItem.datasetIndex ?
                  tooltipItem.yLabel :
                  amount(tooltipItem.yLabel));
        }
      }
    }
  };

  var salesChart= new Chart(document.getElementById('sales-chart'), {
                                 type: 'bar',
                                 data: data,
                                 options: options
                           });
  });
</script>

