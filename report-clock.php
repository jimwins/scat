<?
include 'scat.php';

head("Clock Report @ Scat", true);

$begin= $_REQUEST['begin'];
$end= $_REQUEST['end'];

if (!$begin) {
  $begin= date('Y-m-d', strtotime('Sunday -2 weeks'));
} else {
  $begin= $db->escape($begin);
}

if (!$end) {
  $end= date('Y-m-d', strtotime('last Saturday'));
} else {
  $end= $db->escape($end);
}
?>
<form id="report-params" class="form-horizontal" role="form">
  <div class="form-group">
    <label for="datepicker" class="col-sm-2 control-label">
      Dates
    </label>
    <div class="col-sm-10">
      <div class="input-daterange input-group" id="datepicker">
        <input type="text" class="form-control" name="begin" value="<?=ashtml($begin)?>" />
        <span class="input-group-addon">to</span>
        <input type="text" class="form-control" name="end" value="<?=ashtml($end)?>" />
      </div>
    </div>
  </div>
  <div class="form-group">
    <div class="col-sm-offset-2 col-sm-10">
      <input type="submit" class="btn btn-primary" value="Show">
    </div>
  </div>
</form>

<table class="table table-striped table-condensed" style="width: 60%">
  <tr>
    <th>Date</th>
    <th>In</th>
    <th>Out</th>
    <th>Regular</th>
    <th>OT</th>
  </tr>
<?

$q= "SELECT name,
            DATE(start) day,
            TIME(start) start, TIME(end) end,
            LEAST(8, TIMESTAMPDIFF(second, start, end) / 3600) regular,
            GREATEST(0, TIMESTAMPDIFF(second, start, end) / 3600 - 8) overtime
       FROM timeclock
       JOIN person ON timeclock.person = person.id
      WHERE start BETWEEN '$begin' AND '$end' + INTERVAL 1 DAY
      ORDER BY name, day";

$r= $db->query($q);

$latest= "";
$regular= $ot= 0;

while ($row= $r->fetch_assoc()) {

  if ($row['name'] != $latest) {
    if ($latest) {
      echo '<tr><td colspan="3"></td><td>', $regular, '</td><td>', $ot, '</td></tr>';
    }
    echo '<tr><th colspan="5">', ashtml($row['name']), '</th></tr>';

    $latest= $name;
    $regular= $ot= 0;

    $latest= $row['name'];
  }

  $regular+= $row['regular'];
  $ot+= $row['overtime'];

  echo '<tr><td>',
       '&nbsp; &nbsp;', date('l, F j', strtotime($row['day'])),
       '</td><td>',
       $row['start'],
       '</td><td>',
       $row['end'],
       '</td><td>',
       $row['regular'],
       '</td><td>',
       $row['overtime'],
       '</td></tr>';
}

echo '<tr><td colspan="3"></td><td>', $regular, '</td><td>', $ot, '</td></tr>';

?>
</table>
<?
foot();
?>
<script>
$(function() {
  $('#report-params .input-daterange').datepicker({
      format: "yyyy-mm-dd",
      todayHighlight: true
  });
});
</script>
