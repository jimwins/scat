<?
include 'scat.php';

head("till");

$q= "SELECT CAST(SUM(amount) AS DECIMAL(9,2)) FROM payment
      WHERE method IN ('cash','change','withdrawal')";
$r= $db->query($q)
  or die($db->error);

$row= $r->fetch_row();
$current= $row[0];

$count= $_REQUEST['count'];
$withdrawal= $_REQUEST['withdrawal'];

if (!empty($count) && !empty($withdrawal)) {
  $q= "START TRANSACTION";
  $r= $db->query($q)
    or die($db->error);

  $q= "SELECT MAX(number) + 1 FROM txn WHERE type = 'drawer'";
  $r= $db->query($q)
    or die($db->error);
  $row= $r->fetch_row();
  $number= $row[0];

  $q= "INSERT INTO txn
          SET number= $number,
              created = NOW(), filled = NOW(), paid = NOW(),
              type= 'drawer', tax_rate= 0.0";
  $r= $db->query($q)
    or die($db->error);
  $txn= $db->insert_id;

  if ($count != $current) {
    $amount= $count - $current;
    $q= "INSERT INTO payment
            SET txn = $txn,
                processed = NOW(),
                method = 'cash',
                amount = $amount";
    $r= $db->query($q)
      or die($db->error);
  }

  if ($withdrawal) {
    $q= "INSERT INTO payment
            SET txn = $txn,
                processed = NOW(),
                method = 'withdrawal',
                amount = -$withdrawal";
    $r= $db->query($q)
      or die($db->error);
  }

  $db->commit()
    or die($db->error);

  $q= "SELECT CAST(SUM(amount) AS DECIMAL(9,2)) FROM payment
        WHERE method IN ('cash','change','withdrawal')";
  $r= $db->query($q)
    or die($db->error);

  $row= $r->fetch_row();
  $current= $row[0];

  ?>
<div class="error">Till update successful!</div>
<script>
  var lpr= $('<iframe id="receipt" src="print/deposit-slip.php?print=1&amp;id=<?=$txn?>"></iframe>').hide();
  $("#receipt").remove();
  $('body').append(lpr);
</script>
  <?
}
?>
<form method="post" action="./till.php">
 Count: <input type="text" name="count" value="<?=$current?>">
 <br>
 Withdrawal: <input type="text" name="withdrawal" value="0.00">
 <br>
 <input type="submit" value="Kaching">
</form>

<?
foot();
