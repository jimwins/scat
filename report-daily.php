<?
require 'scat.php';
require 'lib/txn.php';

head("Daily Flow @ Scat", true);

$begin= $_REQUEST['begin'];
$end= $_REQUEST['end'];

if (!$begin) {
  $begin= (new \Datetime('8 days ago'))->format('Y-m-d');
} else {
  $begin= $db->escape($begin);
}

if (!$end) {
  $end= (new \Datetime())->format('Y-m-d');
} else {
  $end= $db->escape($end);
}

?>
<form id="report-params" class="form-horizontal" role="form"
      action="<?=$_SERVER['PHP_SELF']?>">
  <div class="form-group">
    <label for="datepicker" class="col-sm-1 control-label">
      Dates
    </label>
    <div class="col-sm-11">
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
    <div class="col-sm-offset-1 col-sm-11">
      <input type="submit" class="btn btn-primary" value="Show">
    </div>
  </div>
</form>
<?
$q= "SELECT DATE_FORMAT(processed, '%Y-%m-%d %a') AS date,
            method, cc_type, SUM(amount) amount
       FROM payment
      WHERE processed BETWEEN '$begin' AND '$end'
      GROUP BY date, method, cc_type
      ORDER BY date DESC";

$r= $db->query($q)
  or die($db->error);

$data= $seen= array();

while ($row= $r->fetch_assoc()) {
  $method= $row['method'];
  /* Treat change as cash */
  if ($method == 'change') $method= 'cash';
  $data[$row['date']][$method]=
    bcadd($data[$row['date']][$method], $row['amount']);
  $seen[$method]++; /* Track methods we've seen */
  /* Don't add withdrawals to total */
  if ($method != 'withdrawal')
    $data[$row['date']]['total']=
      bcadd($data[$row['date']]['total'], $row['amount']);
}

$total= 0;
?>
<table class="table table-striped sortable" style="width: auto">
<thead>
 <tr>
   <th>Date</th>
<?
foreach (\Payment::$methods as $method => $name) {
  if ($seen[$method])
    echo '<th>', $name, '</th>';
}
?>
   <th>Total</th>
 </tr>
</thead>
<tbody>
<?
foreach ($data as $date => $data) {
  echo '<tr><td>', $date, '</td>';
  foreach (\Payment::$methods as $method => $name) {
    if ($seen[$method])
      echo '<td>', $data[$method] ? amount($data[$method]) : '', '</td>';
  }
  echo '<td>', amount($data['total']), '</td></tr>';
  $total= bcadd($total, $data['total']);
}
?>
 </tbody>
 <tfoot>
   <tr>
     <th colspan="<?=1 + count($seen)?>" class="text-right">Total:</th>
     <th><?=amount($total)?></th>
   <tr>
 </tfoot>
</table>
<script>
$(function() {
  $('#report-params .input-daterange').datepicker({
      format: "yyyy-mm-dd",
      todayHighlight: true
  });
});
</script>
<?
foot();
