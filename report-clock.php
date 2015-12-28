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
<?

$q= "SELECT name,
            DATE(start) day,
            LEAST(8, TIMESTAMPDIFF(second, start, end) / 3600) regular,
            GREATEST(0, TIMESTAMPDIFF(second, start, end) / 3600 - 8) overtime
       FROM timeclock
       JOIN person ON timeclock.person = person.id
      WHERE start BETWEEN '$begin' AND '$end' + INTERVAL 1 DAY
      ORDER BY name, day";

$r= $db->query($q);

dump_table($r);

foot();
