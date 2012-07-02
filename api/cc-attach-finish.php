<?
require '../scat.php';
require '../lib/person.php';

if ($_REQUEST['HostedPaymentStatus'] == 'Cancelled') {
  echo '<script>parent.$.modal.close();</script>';
  exit;
}

if ($_REQUEST['HostedPaymentStatus'] == 'Complete') {
  $payment_account_id= $db->escape($_REQUEST['PaymentAccountID']);
  $setup_id= $db->escape($_REQUEST['TransactionSetupID']);
  $valid= $db->escape($_REQUEST['ValidationCode']);
  $id= $db->get_one("SELECT txn FROM hostedpayment_txn
                      WHERE hostedpayment = '$setup_id'
                        AND validationcode = '$valid'");

  if ($id) {
    $person= person_load($db, $id);

    $q= "UPDATE person
            SET payment_account_id = '" . addslashes($payment_account_id) . "'
          WHERE id = $id";

    $r= $db->query($q)
      or die_query($db, $q);
?>
<script>
var person= <?=json_encode(person_load($db, $id))?>;
parent.loadPerson(person);
parent.$.modal.close();
</script>
<?
    exit;
  } else {
    echo 'Completion information not valid.';
  }
}
?>
<button onclick="javascript:parent.$.modal.close()">Close</button>
<?

echo '<pre>';
var_dump($_REQUEST);
