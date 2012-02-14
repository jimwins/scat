<?
require '../scat.php';
require '../lib/txn.php';

if ($_REQUEST['HostedPaymentStatus'] == 'Cancelled') {
  echo '<script>parent.$.modal.close();</script>';
  exit;
}

if ($_REQUEST['HostedPaymentStatus'] == 'Complete') {
  $payment= $db->escape($_REQUEST['TransactionSetupID']);
  $valid= $db->escape($_REQUEST['ValidationCode']);
  $id= $db->get_one("SELECT txn FROM hostedpayment_txn
                      WHERE hostedpayment = '$payment'
                        AND validationcode = '$valid'");

  // TODO Handle error here.

  $txn= txn_load($db, $id);

  $method= 'credit';
  $amount= $_REQUEST['ApprovedAmount'];

  $cc[]= "cc_txn = '" . addslashes($_REQUEST['TransactionID']) . "', ";
  $cc[]= "cc_approval = '" . addslashes($_REQUEST['ApprovalNumber']) . "', ";
  $cc[]= "cc_lastfour= '" . addslashes($_REQUEST['LastFour']) . "', ";
  $cc[]= "cc_type= '" . addslashes($_REQUEST['CardLogo']) . "', ";
  $extra_fields= join('', $cc);

  // add payment record
  $q= "INSERT INTO payment
          SET txn = $id, method = '$method', amount = $amount,
          $extra_fields
          processed = NOW()";
  $r= $db->query($q)
    or die_query($db, $q);

  $payment= $db->insert_id;

  $txn['total_paid'] = bcadd($txn['total_paid'], $amount);

  // if we're all paid up, record that the txn is paid
  if (!bccomp($txn['total_paid'], $txn['total'])) {
    $q= "UPDATE txn SET paid = NOW() WHERE id = $id";
    $r= $db->query($q)
      or die_query($db, $q);
  }
?>
<script>
var data= <?=json_encode(array('payment' => $payment,
                               'txn' => txn_load($db, $id),
                               'payments' => txn_load_payments($db, $id)))?>;
parent.loadOrder(data);
if (<?=$amount?> >= 25.00) {
  parent.printChargeRecord(data.payment);
}
parent.$.modal.close();
</script>
<?
  exit;
}
?>
<button onclick="javascript:parent.$.modal.close()">Close</button>
<?

echo '<pre>';
var_dump($_REQUEST);
