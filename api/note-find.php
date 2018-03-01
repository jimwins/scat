<?php
require '../scat.php';
require '../lib/catalog.php';

$limit= (int)$_REQUEST['limit'];
if (!$limit) $limit= 100;
$offset= (int)$_REQUEST['offset'];

$attach_id= (int)$_REQUEST['attach_id'];
if ($attach_id) {
  $extra_conditions= 'AND attach_id = ' . $attach_id;
}

$parent_id= (int)$_REQUEST['parent_id'];
$order= $parent_id ? 'ASC' : 'DESC';

$todo= (int)$_REQUEST['todo'];

$q= "SELECT note.id, note.kind, note.attach_id,
            note.content, note.added, note.modified,
            note.person_id, note.parent_id,
            note.public, note.todo,
            person.name as person_name,
            txn.type AS txn_type,
            (SELECT COUNT(*) FROM note children
              WHERE children.parent_id = note.id) AS children,
            IF(txn.type = 'vendor' && YEAR(txn.created) > 2013,
               CONCAT(SUBSTRING(YEAR(txn.created), 3, 2), txn.number),
               CONCAT(DATE_FORMAT(txn.created, '%Y-'), txn.number))
              AS txn_name,
            IF(about.name != '', about.name, about.company) AS about_name,
            item.name AS item_name
       FROM note
       LEFT JOIN person ON note.person_id = person.id
       LEFT JOIN txn ON note.kind = 'txn' AND note.attach_id = txn.id
       LEFT JOIN person about ON (note.kind = 'person'
                                  AND note.attach_id = about.id)
                              OR (note.kind = 'txn'
                                  AND txn.person = about.id)
       LEFT JOIN item ON note.kind = 'item' AND note.attach_id = item.id
      WHERE parent_id = $parent_id
        AND IF($todo, todo, 1)
        $extra_conditions
      ORDER BY note.id $order
      LIMIT $offset, $limit";

$r= $db->query($q)
  or die_query($db, $q);

$data= array();

while ($row= $r->fetch_assoc()) {
  $data[]= $row;
}

echo jsonp($data);
