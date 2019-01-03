<?
include '../scat.php';
include '../lib/item.php';

$vendor= (int)$_REQUEST['vendor'];
if (!$vendor)
  die_jsonp("You need to specify a vendor.");

$sql_criteria= "1=1";
if (($items= $_REQUEST['items'])) {
  list($sql_criteria, $x)= item_terms_to_sql($db, $items, FIND_OR);
}

$q= "UPDATE item
        SET retail_price = (SELECT retail_price
                              FROM vendor_item
                             WHERE vendor_item.item = item.id
                               AND vendor = $vendor
                             LIMIT 1)
     WHERE $sql_criteria";

$r= $db->query($q)
  or die_query($db, $q);

echo jsonp(array('message' => "Updated items"));
