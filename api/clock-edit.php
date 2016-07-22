<?
include '../scat.php';

$id= (int)$_REQUEST['id'];
$time= $_REQUEST['time'];
$type= $_REQUEST['type'];

if (!$id)
  die_jsonp("No punch specified.");
if (!$time)
  die_jsonp("No time specified.");
if ($type != 'start' && $type != 'end')
  die_jsonp("Invalid type specified.");

$time= $db->escape($time);

// $id, $time and $type are safe in queries

$db->start_transaction()
  or die_query($db, "START TRANSACTION");

$q= "SELECT id, start, end FROM timeclock WHERE id = $id";
$entry= $db->get_one_assoc($q)
  or die_query($db, $q);

// XXX can't change date of an entry for now
$q= "UPDATE timeclock
        SET $type = CONCAT(DATE(start), ' ', '$time')
      WHERE id = $id";
$db->query($q)
  or die_query($db, $q);

$q= "INSERT INTO timeclock_audit
        SET entry = $id,
            before_start = '{$entry['start']}',
            before_end = '{$entry['end']}',
            after_start = (SELECT start FROM timeclock WHERE id = $id),
            after_end = (SELECT end FROM timeclock WHERE id = $id)";
$db->query($q)
  or die_query($db, $q);

$db->commit()
  or die_query($db, "COMMIT");

echo jsonp(array("result" => "Success."));
