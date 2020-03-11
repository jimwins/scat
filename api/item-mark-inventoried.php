<?
include '../scat.php';
include '../lib/item.php';

if (($q= $_REQUEST['q'])) {

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
} elseif (($items= $_REQUEST['items'])) {
  $items= $db->escape($items);
  $query= "UPDATE item SET inventoried = NOW()
            WHERE id IN ($items)";

  $r= $db->query($query)
    or die_query($db, $query);
} else {
  die_json("Nothing to mark.");
}

echo jsonp(array('count' => $db->affected_rows));

