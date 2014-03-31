<?php
require '../scat.php';

$month= $_REQUEST['month'];
if (!$month) die("No month given.");

$q= "SELECT payment.id, method,
            DATE_FORMAT(processed, '%m/%d/%Y') processed,
            cc_type, amount, txn, txn.type,
            CONCAT(YEAR(filled), '-', number) num
       FROM payment
       JOIN txn ON payment.txn = txn.id
      WHERE processed BETWEEN '$month-01' AND '$month-01' + INTERVAL 1 MONTH
      ORDER BY 1";

$r= $db->query($q)
  or die_query($db, $q);

header('Content-Type: text/plain');
#header('Content-Disposition: attachment; filename="payments.txt"');

echo "Journal Number\tDate\tMemo\tAccount Number\tDebit Amount\tCredit Amount\r\n";

while ($pay= $r->fetch_assoc()) {
  if ($pay['amount'] == 0)
    continue;

  $accts= array(
                'drawer' => array(
                  'cash' =>       array('11130', '61220'),
                  'withdrawal' => array('11130', '11160'),
                 ),
                'customer' => array(
                  'cash' =>   array('11130', '11200'),
                  'change' => array('11130', '11200'),
                  'credit' => array('11190', '11200'),
                  'square' => array('11180', '11200'),
                  'gift' =>   array('21700', '11200'),
                  'check' =>  array('11160', '11200'),
                  'dwolla'=>  array('11170', '11200'),
                  'discount'=>array('53000', '11200'),
                  'bad'=>     array('61900', '11200'),
                  'donation'=>array('63150', '11200'),
                  'internal'=>array('64500', '11200'),
                 ));

  list($debit, $credit)= $accts[$pay['type']][$pay['method']];

  if (!$debit) {
    die("Don't know how to handle $pay[method] for $pay[type] payment");
  }

  switch ($pay['type']) {
  case 'drawer':
    $memo= "Till Count $pay[num]";
    break;

  case 'customer':
    $memo= "Payment for invoice $pay[num]";
    break;

  default:
    die("Don't know how to handle $pay[type] payment");
  }

  // debit entry
  echo "\t$pay[processed]\t$pay[id]: $memo\t$debit\t";
  if ($pay['amount'] < 0) {
    echo "0\t" . substr($pay['amount'], 1);
  } else {
    echo $pay['amount'] . "\t0";
  }
  echo "\r\n";

  // credit entry
  echo "\t$pay[processed]\t$pay[id]: $memo\t$credit\t";
  if ($pay['amount'] < 0) {
    echo substr($pay['amount'], 1) . "\t0";
  } else {
    echo "0\t" . $pay['amount'];
  }
  echo "\r\n";

  echo "\r\n";
}
