<?php
include '../scat.php';

$id= (int)$_REQUEST['id'];
$content= $_REQUEST['content'];

try {
  $note= Model::factory('Note')
           ->find_one($id);
  if ($content) $note->content= $content;
  if (isset($_REQUEST['public']))
    $note->public= (int)$_REQUEST['public'];
  if (isset($_REQUEST['todo']))
    $note->todo= (int)$_REQUEST['todo'];
  $note->save();
} catch (\PDOException $e) {
  die_jsonp(array('message' => $e->getMessage(),
                  'query' => ORM::get_last_query()));
}

/* Have to reload whole note to get timestamp */
echo jsonp(Model::factory('Note')
             ->select("note.*")
             ->select_expr('(SELECT COUNT(*) FROM note children
                              WHERE children.parent_id = note.id)',
                           'children')
             ->select_expr('(SELECT name FROM person
                              WHERE person_id = person.id)',
                           'person_name')
             ->find_one($note->id)->as_array());
