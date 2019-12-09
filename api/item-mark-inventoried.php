<?
include '../scat.php';
include '../lib/item.php';

$q= $_REQUEST['q'];
if (!$q) die_json("Nothing to mark.");

if (!preg_match('/stocked:/i', $q)) {
  $q= $q . " stocked:1";
}

$items= item_find($db, $q, 0);
if (!$items) die_json("No items found.");

$query= "update item set inventoried = now()
          where id in (" .
          join(',', array_map(function($a) { return $a['id']; }, $items))
          . ")";

$r= $db->query($query)
  or die_query($db, $query);

echo jsonp(array('count' => $db->affected_rows));

