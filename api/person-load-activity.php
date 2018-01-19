<?
include '../scat.php';
include '../lib/person.php';

$person_id= (int)$_REQUEST['person'];
$page= (int)$_REQUEST['page'];
$limit= isset($_REQUEST['limit']) ? (int)$_REQUEST['limit'] : 50;

$activity= person_load_activity($db, $person_id, $page, $limit);

$rows= $db->get_one('SELECT FOUND_ROWS()');

if (!is_array($activity))
  die_jsonp('No such person.');

echo jsonp(array('activity' => $activity, 'activity_page' => $page,
                 'total' => $rows));
