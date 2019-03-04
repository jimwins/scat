<?
require 'scat.php';
require 'lib/person.php';

$search= $_REQUEST['search'];

$people= array();

if (empty($search)) {
  $search="role:vendor active:1";
}

$people= person_find($db, $search);

/* If only one person matches, redirect to that */
if (count($people) == 1) {
  header("Location: person.php?id=" . $people[0]['id'] . '&search=' . urlencode($search));
  exit;
}

head("People @ Scat", true);

include 'ui/person-search.html';
?>
<table class="table table-condensed table-striped table-hover"
       data-bind="if: people().length">
 <thead>
  <tr>
    <th>#</th>
    <th>Name</th>
    <th>Company</th>
    <th>Phone</th>
  </tr>
 </thead>
 <tbody data-bind="foreach: people">
  <tr data-bind="click: function(d, e) { window.location.href= 'person.php?id=' + $data.id() }" style="cursor: pointer">
   <td class="num" data-bind="text: $index() + 1"></td>
   <td data-bind="text: $data.name"></td>
   <td data-bind="text: $data.company"></td>
   <td data-bind="text: $data.pretty_phone"></td>
  </tr>
 </tbody>
</table>
<style>
tbody tr:hover { color: #02314d; text-decoration: underline; }
</style>
<?
foot();
?>
<script>
var model= {
  search: '<?=ashtml($search);?>',
  all: <?=(int)$all?>,
  people: <?=json_encode($people);?>,
};

var viewModel= ko.mapping.fromJS(model);

ko.applyBindings(viewModel);

</script>
