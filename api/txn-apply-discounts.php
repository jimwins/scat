<?
include '../scat.php';
include '../lib/txn.php';

$id= (int)$_REQUEST['txn'];
if (!$id)
  die_jsonp("no transaction specified.");

$txn= txn_load($db, $id);

if ($txn['paid']) {
  die_jsonp("This order is already paid!");
}

$count= $db->get_one("SELECT ABS(SUM(ordered))
                        FROM txn_line
                        JOIN item ON txn_line.item = item.id
                       WHERE txn = $id
                         AND code LIKE 'MXG-%'");
if ($count >= 50) {
  $q= "UPDATE txn_line, item
          SET txn_line.discount = 40.0, txn_line.discount_type = 'percentage'
        WHERE txn = $id AND code LIKE 'MXG-%'";
  $db->query($q)
    or die_query($db, $q);
} elseif ($count >= 12) {
  $q= "UPDATE txn_line, item
          SET txn_line.discount = 30.0, txn_line.discount_type = 'percentage'
        WHERE txn = $id AND code LIKE 'MXG-%'";
  $db->query($q)
    or die_query($db, $q);
} else {
  $q= "UPDATE txn_line, item
          SET txn_line.discount = item.discount,
              txn_line.discount_type = item.discount_type
        WHERE txn = $id AND code LIKE 'MXG-%'";
  $db->query($q)
    or die_query($db, $q);
}

$txn= txn_load_full($db, $id);

echo jsonp($txn);
