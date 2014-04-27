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
  
  $txn= new Transaction($db, $id);

  $method= 'credit';
  $amount= $_REQUEST['ApprovedAmount'];

  $cc= array();
  $cc['cc_txn']= $_REQUEST['TransactionID'];
  $cc['cc_approval']= $_REQUEST['ApprovalNumber'];
  $cc['cc_lastfour']= $_REQUEST['LastFour'];
  $cc['cc_type']= $_REQUEST['CardLogo'];

  try {
    $payment= $txn->addPayment($method, $amount, $cc);
  } catch (Exception $e) {
    die_jsonp($e->getMessage());
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
