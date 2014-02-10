<?
include 'scat.php';

head("Till @ Scat", true);

$q= "SELECT CAST(SUM(amount) AS DECIMAL(9,2)) FROM payment
      WHERE method IN ('cash','change','withdrawal')";
$current= $db->get_one($q)
  or die($db->error);

$q= "SELECT created FROM txn
      WHERE type = 'drawer'
      ORDER BY id DESC
      LIMIT 1";
$last_txn= $db->get_one($q)
  or die($db->error);

$q= "SELECT COUNT(*)
       FROM payment
      WHERE method = 'check' AND processed > '$last_txn'";
$checks= $db->get_one($q);

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
  $current= $db->get_one($q)
    or die($db->error);

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
<form class="form-horizontal" method="post" action="./till.php">
  <div class="form-group">
    <label for="expected" class="col-sm-2 control-label">Expected</label>
    <div class="col-sm-10">
      <input type="text" class="form-control" disabled
             id="expected" data-bind="value: expected">
    </div>
  </div>
  <div class="form-group">
    <label for="counted" class="col-sm-2 control-label">Counted</label>
    <div class="col-sm-10">
      <input type="text" class="form-control"
             id="counted" name="count" data-bind="value: current">
    </div>
  </div>
  <div class="form-group">
    <label for="overshort" class="col-sm-2 control-label">Over/Short</label>
    <div class="col-sm-10">
      <input type="text" class="form-control" disabled
             id="overshort" data-bind="value: overshort">
    </div>
  </div>
  <div class="form-group">
    <label for="withdraw" class="col-sm-2 control-label">Withdrawal</label>
    <div class="col-sm-10">
      <input type="text" class="form-control"
             id="withdraw" name="withdrawal" data-bind="value: withdraw">
    </div>
  </div>
  <div class="form-group">
    <label for="remaining" class="col-sm-2 control-label">Remaining</label>
    <div class="col-sm-10">
      <input type="text" class="form-control" disabled
             id="remaining" data-bind="value: remaining">
    </div>
  </div>
  <div data-bind="visible: checks, text: checks_pending"></div>
  <div class="form-group">
    <div class="col-sm-offset-2 col-sm-10">
      <button type="submit" class="btn btn-primary">Kaching</button>
    </div>
  </div>
</form>

<script>
var tillModel= function(expected, checks) {
  this.expected= ko.observable(expected);
  this.current= ko.observable(expected);
  this.withdraw= ko.observable(0.00);
  this.checks= ko.observable(checks);

  this.overshort= ko.computed(function() {
    return (this.current() - this.expected()).toFixed(2);
  }, this);

  this.remaining= ko.computed(function() {
    return (this.current() - this.withdraw()).toFixed(2);
  }, this);

  this.checks_pending= ko.computed(function() {
    return "(" + this.checks() + " check" + ((this.checks() > 1) ? 's' : '')
           + " pending)";
  }, this);
};

ko.applyBindings(new tillModel(<?=$current.','.(int)$checks?>));
</script>
<?
foot();
