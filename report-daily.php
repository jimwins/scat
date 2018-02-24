<?
require 'scat.php';
require 'lib/txn.php';

head("Daily Flow @ Scat", true);

$q= "SELECT DATE_FORMAT(processed, '%Y-%m-%d %a') AS date,
            method, cc_type, SUM(amount) amount
       FROM payment
      WHERE processed > DATE(NOW() - INTERVAL 8 DAY)
      GROUP BY date, method, cc_type
      ORDER BY date DESC";

$r= $db->query($q)
  or die($db->error);

$data= $seen= array();

while ($row= $r->fetch_assoc()) {
  $method= $row['method'];
  if ($method == 'change') $method= 'cash';
  $data[$row['date']][$method]=
    bcadd($data[$row['date']][$method], $row['amount']);
  $seen[$method]++;
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
<?

foot();
