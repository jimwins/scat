<?
include '../scat.php';
include '../lib/item.php';

$id= (int)$_REQUEST['id'];

$code= $_REQUEST['code'];

if (!$id && $code) {
  $code= $db->escape($code);
  $q= "SELECT id FROM item WHERE code = '$code'";
  $id= $db->get_one($q);
};

$days= (int)$_REQUEST['days'];
if (!$days) $days= 7;

if (!$id)
  die_jsonp("No item specified.");

$q= "SELECT id,
            (SELECT -1 * SUM(allocated * sale_price(txn_line.retail_price,
                                                    txn_line.discount_type,
                                                    txn_line.discount))
               FROM txn_line JOIN txn ON (txn = txn.id)
              WHERE type = 'customer'
                AND item = item.id AND filled > NOW() - INTERVAL $days DAY)
            AS amount,
            (SELECT -1 * SUM(allocated)
               FROM txn_line JOIN txn ON (txn = txn.id)
              WHERE type = 'customer'
                AND item = item.id AND filled > NOW() - INTERVAL $days DAY)
            AS items
       FROM item
      WHERE id = $id";

$ret= $db->get_one_assoc($q);

echo jsonp([ 'sales' => $ret, 'q' => $q ]);
