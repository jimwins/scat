<?php
require '../scat.php';

$q= "SELECT payment.id, method,
            DATE_FORMAT(processed, '%m/%d/%Y') processed,
            cc_type, amount, txn, txn.type,
            CONCAT(YEAR(filled), '-', number) num
       FROM payment
       JOIN txn ON payment.txn = txn.id
      WHERE processed BETWEEN '2012-01-01' AND '2013-01-01'
      ORDER BY 1";

$r= $db->query($q)
  or die_query($db, $q);

header('Content-Type: text/plain');
//header('Content-Disposition: attachment; filename="payments.txt"');

echo "Journal Number\tDate\tMemo\tAccount Number\tDebit Amount\tCredit Amount\r\n";

$amt= 0.0;
$memo= '';
$last= array();

function finish($amt, $last, $memo) {
    switch ($last['type']) {
    case 'drawer':
      $account= '11130';
      break;
    case 'customer':
      $account= '11200';
      break;
    default:
      die("Don't know how to handle $last[type] payment");
    }
    echo "\t$last[processed]\t$last[id]: $memo\t$account\t";

    if ($amt < 0) {
      echo "0\t" . sprintf('%.2f', abs($amt));
    } else {
      echo "$amt\t0";
    }

    echo "\r\n";
    echo "\r\n";
}

while ($pay= $r->fetch_assoc()) {
  if ($last && $last['txn'] != $pay['txn']) {
    finish($amt, $last, $memo);
    // end entry
    $amt= 0.0;
  }
  $txn= $pay['txn'];
  $last= $pay;

  switch ($pay['type']) {
  case 'drawer':
    $memo= "Till Count $pay[num]";
    switch ($pay['method']) {
    case 'cash':
      $account= "61220";
      break;

    case 'withdrawal':
      $account= "11160";
      break;

    default:
      die("Don't know how to handle $pay[method] for $pay[type] payment");
    }
    break;

  case 'customer':
    $memo= "Payment for invoice $pay[num]";

    $accts= array(
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
                 );

    $account= $accts[$pay['method']];
    if (!$account) {
      die("Don't know how to handle $pay[method] for $pay[type] payment");
    }
    break;

  default:
    die("Don't know how to handle $pay[type] payment");
  }
  $amt-= $pay['amount'];

  echo "\t$pay[processed]\t$pay[id]: $memo\t$account\t";

  if ($pay['amount'] < 0) {
    echo "0\t" . substr($pay['amount'], 1);
  } else {
    echo $pay['amount'] . "\t0";
  }

  echo "\r\n";
}
finish($amt, $last, $memo);
