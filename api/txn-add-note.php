<?
include '../scat.php';
include '../lib/txn.php';

$id= (int)$_REQUEST['id'];

if (!$id)
  die_jsonp("No transaction specified.");

$note= $_REQUEST['note'];
$public= (int)$_REQUEST['public'];

if (!$note)
  die_jsonp("No note given.");

$note= $db->escape($note);

$q= "INSERT INTO txn_note
             SET entered = NOW(),
                 txn = $id,
                 content = '$note',
                 public = $public";
$db->query($q)
  or die_query($db, $q);

echo jsonp(array('txn' => txn_load($db, $id),
                 'notes' => txn_load_notes($db, $id)));
