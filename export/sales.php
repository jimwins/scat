<?php
require '../scat.php';

bcscale(2);

header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="sales.txt"');

$begin= '2013-01-01';
$end= '2013-02-01';

$q= "SELECT id, type, created,
            DATE_FORMAT(IF(type = 'customer', paid, created), '%m/%d/%Y') date,
            CONCAT(YEAR(IF(type = 'customer', paid, created)), '-', number) num,
            taxed, untaxed,
            CAST(tax_rate AS DECIMAL(9,2)) tax_rate, 
            taxed + untaxed subtotal,
            CAST(ROUND_TO_EVEN(taxed * (tax_rate / 100), 2)
                 AS DECIMAL(9,2)) tax,
            CAST(ROUND_TO_EVEN(taxed * (1 + tax_rate / 100), 2) + untaxed
                 AS DECIMAL(9,2)) total
      FROM (SELECT
            txn.id, txn.type, txn.number,
            txn.created, txn.filled, txn.paid,
            SUM(ordered) * IF(txn.type = 'customer', -1, 1) AS ordered,
            SUM(allocated) * IF(txn.type = 'customer', -1, 1) AS allocated,
            CAST(ROUND_TO_EVEN(
              SUM(IF(txn_line.taxfree, 1, 0) *
                IF(type = 'customer', -1, 1) * allocated *
                CASE discount_type
                  WHEN 'percentage' THEN retail_price * ((100 - discount) / 100)
                  WHEN 'relative' THEN (retail_price - discount) 
                  WHEN 'fixed' THEN (discount)
                  ELSE retail_price
                END),
              2) AS DECIMAL(9,2))
            untaxed,
            CAST(ROUND_TO_EVEN(
              SUM(IF(txn_line.taxfree, 0, 1) *
                IF(type = 'customer', -1, 1) * allocated *
                CASE discount_type
                  WHEN 'percentage' THEN retail_price * ((100 - discount) / 100)
                  WHEN 'relative' THEN (retail_price - discount) 
                  WHEN 'fixed' THEN (discount)
                  ELSE retail_price
                END),
              2) AS DECIMAL(9,2))
            taxed,
            tax_rate
       FROM txn
       LEFT JOIN txn_line ON (txn.id = txn_line.txn)
      WHERE (type = 'correction' AND created BETWEEN '$begin' AND '$end')
         OR (type = 'customer'   AND paid    BETWEEN '$begin' AND '$end')
      GROUP BY txn.id
      ORDER BY id) t";

$r= $db->query($q)
  or die_query($db, $q);

echo "Journal Number\tDate\tMemo\tAccount Number\tDebit Amount\tCredit Amount\r\n";

$account= array(
                // assets
                'receivable' => '11200',
                'inventory'  => '11300',
                // liabilities
                'gift'       => '21700',
                'salestax'   => '23000',
                // sales
                'art'        => '41100',
                'supplies'   => '41200',
                'framing'    => '41300',
                'printing'   => '41400',
                'online'     => '42100',
                'class'      => '42100',
                'freight'    => '45000',
                'location'   => '47000',
                // cost of sales
                'costofgoods'=> '51100',
                // shrink
                'shrink'     => '61900',
               );

function entry($date, $memo, $account, $dbcr) {
  if ($dbcr == 0)
    return;

  echo "\t$date\t$memo\t$account\t";
  if ($dbcr > 0) {
    echo "$dbcr\t0";
  } else {
    echo "0\t" . substr($dbcr, 1);
  }
  echo "\r\n";
}

while ($sale= $r->fetch_assoc()) {

  // Journal Number\tDate\tMemo\tAccount Number\tDebit Amount\tCredit Amount
  switch ($sale['type']) {
  case 'correction':
    if ($sale['total'] == 0)
      continue;

    $memo= "$sale[id]: Correction $sale[num]";
    entry($sale['date'], $memo, $account['shrink'],    bcmul($sale['total'],-1));
    entry($sale['date'], $memo, $account['inventory'], $sale['total']);

    break;

  case 'customer':
    $memo= "$sale[id]: Invoice $sale[num]";
    // receivable
    entry($sale['date'], $memo, $account['receivable'], $sale['total']);
    if ($sale['tax_rate']) {
      entry($sale['date'], $memo, $account['salestax'], bcmul($sale['tax'], -1));
    }

    $q= "SELECT code,
                CAST(IFNULL(ROUND_TO_EVEN(
                    allocated *
                    (SELECT ROUND_TO_EVEN(AVG(tl.retail_price), 2)
                       FROM txn JOIN txn_line tl ON txn.id = tl.txn
                      WHERE type = 'vendor'
                        AND item = txn_line.item
                        AND filled < '$sale[created]'
                     ),
                    2), 0.00) AS DECIMAL(9,2)) AS cost,
                CAST(ROUND_TO_EVEN(
                  allocated *
                  CASE txn_line.discount_type
                    WHEN 'percentage' THEN txn_line.retail_price *
                                         ((100 - txn_line.discount) / 100)
                    WHEN 'relative' THEN (txn_line.retail_price -
                                          txn_line.discount) 
                    WHEN 'fixed' THEN (txn_line.discount)
                    ELSE txn_line.retail_price
                  END, 2) AS DECIMAL(9,2)) AS price
           FROM txn_line
           JOIN item ON txn_line.item = item.id
          WHERE txn = $sale[id]";

    $in= $db->query($q)
      or die_query($db, $q);

    $sales= array();
    $costs= $total= "0.00";

    while ($line= $in->fetch_assoc()) {
      $category= 'supplies';
      if (preg_match('/^ZZ-frame/i', $line['code'])) {
        $category= 'framing';
      } elseif (preg_match('/^ZZ-(print|scan)/i', $line['code'])) {
        $category= 'printing';
      } elseif (preg_match('/^ZZ-art/i', $line['code'])) {
        $category= 'art';
      } elseif (preg_match('/^ZZ-online/i', $line['code'])) {
        $category= 'online';
      } elseif (preg_match('/^ZZ-class/i', $line['code'])) {
        $category= 'class';
      } elseif (preg_match('/^ZZ-gift/i', $line['code'])) {
        $category= 'gift';
      }

      $sales[$category]= bcadd($sales[$category], $line['price']);
      $total= bcadd($total, $line['price']);
      $costs= bcadd($costs, $line['cost']);
    }

    // $sale[subtotal] is has polarity opposite what we've done for total
    if ($total != bcmul($sale['subtotal'], -1)) {
      $sales['supplies']= bcsub($sales['supplies'],
                                bcadd($sale['subtotal'], $total));
    }

    foreach ($sales as $category => $amount) {
      // sale
      entry($sale['date'], $memo, $account[$category], $amount);
    }

    if ($costs != "0.00") {
      entry($sale['date'], $memo, $account['inventory'],   $costs);
      entry($sale['date'], $memo, $account['costofgoods'], bcmul($costs, -1));
    }

    break;

  default:
    die("Unable to handle transaction type '$sale[type]'");
  }

  echo "\r\n";
}
