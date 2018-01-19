<?
include '../scat.php';
include '../lib/catalog.php';

$content= $_REQUEST['content'];
$kind= $_REQUEST['kind'];
$attach_id= (int)$_REQUEST['attach_id'];
$person_id= (int)$_REQUEST['person_id'];
$parent_id= (int)$_REQUEST['parent_id'];
$public= (int)$_REQUEST['public'];
$todo= (int)$_REQUEST['todo'];

if (!$kind) $kind= 'general';

if (!$content)
  die_jsonp('Need some content.');
if (!in_array($kind, array('general','txn','person','item')))
  die_jsonp('Not aware of how to handle that kind');

if ($kind == 'txn' && !$attach_id)
  die_jsonp("Notes on transactions require a transaction ID.");

try {
  $note= Model::factory('Note')->create();
  $note->kind= $kind;
  $note->attach_id= $attach_id;
  $note->content= $content;
  $note->person_id= $person_id;
  $note->parent_id= $parent_id;
  $note->public= $public;
  $note->todo= $todo;
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
             ->find_one($note->id)->as_array());
