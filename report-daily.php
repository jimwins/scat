<?
require 'scat.php';

head("Daily Flow @ Scat", true);

$q= "SELECT DATE_FORMAT(processed, '%Y-%m-%d %a') AS date,
            method, cc_type, SUM(amount) amount
       FROM payment
      WHERE processed > DATE(NOW() - INTERVAL 8 DAY)
      GROUP BY date, method, cc_type
      ORDER BY date DESC";

$r= $db->query($q)
  or die($db->error);

bcscale(2);

?>
<table class="table table-striped sortable" style="width: auto">
<thead>
 <tr><th>Date</th><th>Cash</th><th>Credit</th><th>Amex</th><th>Check</th><th>Other</th><th>Total</th></tr>
</thead>
<tbody>
<?
$day= null;
$total= $cash= $credit= $amex= $check= $other= 0.00;
while ($row= $r->fetch_assoc()) {
  if ($row['date'] != $day && $day) {
    echo '<tr><td>',
         ashtml($day), '</td><td align="right">',
         amount($cash), '</td><td align="right">',
         amount($credit), '</td><td align="right">',
         amount($amex), '</td><td align="right">',
         amount($check), '</td><td align="right">',
         amount($other), '</td><td align="right">',
         amount($total), "</td></tr>\n";
    $total= $cash= $credit= $amex= $check= $other= 0.00;
  }

  switch ($row['method']) {
  case 'cash':
  case 'change':
    $cash= bcadd($cash, $row['amount']);
    break;
  case 'credit':
    if ($row['cc_type'] == 'AmericanExpress' || $row['cc_type'] == 'AMEX') {
      $amex= bcadd($amex, $row['amount']);
    } else {
      $credit= bcadd($credit, $row['amount']);
    }
    break;
  case 'check':
    $check= bcadd($cash, $row['amount']);
    break;
  case 'withdrawal':
    break;
  default:
    $other= bcadd($other, $row['amount']);
  }
  $total= bcadd($total, $row['amount']);

  $day= $row['date'];
}
?>
 </tbody>
</table>
<?

foot();
