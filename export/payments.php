<?php
require '../scat.php';

$begin= '2013-01-01';
$end= '2013-02-01';

$q= "SELECT payment.id, method,
            DATE_FORMAT(processed, '%m/%d/%Y') processed,
            cc_type, amount, txn, txn.type,
            CONCAT(YEAR(filled), '-', number) num
       FROM payment
       JOIN txn ON payment.txn = txn.id
      WHERE processed BETWEEN '$begin' AND '$end'
      ORDER BY 1";

$r= $db->query($q)
  or die_query($db, $q);

header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="payments.txt"');

echo "Journal Number\tDate\tMemo\tAccount Number\tDebit Amount\tCredit Amount\r\n";

while ($pay= $r->fetch_assoc()) {
  if ($pay['amount'] == 0)
    continue;

  $accts= array(
                'drawer' => array(
                  'cash' =>   '61220',
                  'withdrawal' => '11130',
                 ),
                'customer' => array(
                  'cash' =>   '11130',
                  'change' => '11130',
                  'credit' => '11190',
                  'square' => '11180',
                  'gift' =>   '21700',
                  'check' =>  '11160',
                  'dwolla'=>  '11170',
                  'discount'=>'53000',
                  'bad'=>     '61900',
                  'donation'=>'63150',
                  'internal'=>'64500',
                 ));

  $debit= $accts[$pay['type']][$pay['method']];

  if (!$debit) {
    die("Don't know how to handle $pay[method] for $pay[type] payment");
  }

  switch ($pay['type']) {
  case 'drawer':
    $memo= "Till Count $pay[num]";
    $credit= '11160';
    break;

  case 'customer':
    $memo= "Payment for invoice $pay[num]";
    $credit= '11200';
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
