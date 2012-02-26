<?
require 'scat.php';
require 'lib/person.php';

head("person");

$id= (int)$_REQUEST['id'];
$search= $_REQUEST['search'];

?>
<form method="get" action="person.php">
<input id="focus" type="text" name="search" value="<?=ashtml($search)?>">
<input type="submit" value="Find People">
</form>
<br>
<?

if (!empty($search)) {
  $search= $db->real_escape_string($search);

  $q= "SELECT IF(deleted, 'deleted', '') AS meta,
              CONCAT(id, '|', IFNULL(company,''),
                     '|', IFNULL(name,''))
                AS Person\$person
         FROM person
        WHERE name like '%$search%' OR company LIKE '%$search%'
        ORDER BY company, name";

  $r= $db->query($q)
    or die($db->error);

  if ($r->num_rows > 1) {
    dump_table($r);
  } else {
    $person= $r->fetch_assoc();
    $id= (int)$person['Person$person'];
  }
}

if (!$id) {
  foot();
  exit;
}

$person= person_load($db, $id);
?>
<style>
  #person th { text-align: right; vertical-align: top; color: #777; }
  #person td { white-space: pre-wrap; }
  .deleted { text-decoration: line-through; }
</style>
<script>
function loadPerson(person) {
  $('#person').data('person', person);
  var active= parseInt(person.active);
  if (active) {
    $('#person #active').attr('src', 'icons/accept.png');
  } else {
    $('#person #active').attr('src', 'icons/cross.png');
  }
  $('#person #name').text(person.name);
  $('#person #company').text(person.company);
  $('#person #email').text(person.email);
  $('#person #phone').text(person.phone);
  $('#person #address').text(person.address);
  $('#person #tax_id').text(person.tax_id);
}

$(function() {
  loadPerson(<?=json_encode($person)?>);
});
</script>
<table id="person">
  <tr class="<?=($person['deleted'] ? 'deleted' : '');?>">
   <th>Name:</th>
   <td><span id="name" class="editable"></span><img id="active" align="right" src="icons/accept.png" height="16" width="16"></td>
  </tr>
  <tr>
   <th>Company:</th>
   <td id="company" class="editable"></td>
  </tr>
  <tr>
   <th>Email:</th>
   <td id="email" class="editable"></td>
  </tr>
  <tr>
   <th>Phone:</th>
   <td id="phone" class="editable"></td>
  </tr>
  <tr>
   <th>Address:</th>
   <td id="address" class="editable"></td>
  </tr>
  <tr>
   <th>Tax ID:</th>
   <td id="tax_id" class="editable"></td>
  </tr>
</table>
<script>
$('#person .editable').editable(function(value, settings) {
  var person= $('#person').data('person');
  var data= { person: person.id };
  var key= this.id;
  data[key] = value;

  $.getJSON("api/person-update.php?callback=?",
            data,
            function (data) {
              if (data.error) {
                $.modal(data.error);
                return;
              }
              loadPerson(data.person);
            });
  return "...";
}, {
  event: 'dblclick',
  style: 'display: inline',
  placeholder: '',
});
</script>

<h2>Activity</h2>
<?
$q= "SELECT meta, Number\$txn, Created\$date,
            Ordered, Allocated,
            CAST(ROUND_TO_EVEN(taxed * (1 + tax_rate / 100), 2) + untaxed
                 AS DECIMAL(9,2))
            Total\$dollar,
            Paid\$dollar
      FROM (SELECT
            txn.type AS meta,
            CONCAT(txn.id, '|', type, '|', txn.number) AS Number\$txn,
            txn.created AS Created\$date,
            CONCAT(txn.person, '|', IFNULL(person.company,''),
                   '|', IFNULL(person.name,''))
              AS Person\$person,
            SUM(ordered) * IF(txn.type = 'customer', -1, 1) AS Ordered,
            SUM(allocated) * IF(txn.type = 'customer', -1, 1) AS Allocated,
            CAST(ROUND_TO_EVEN(
              SUM(IF(txn_line.taxfree, 1, 0) *
                IF(type = 'customer', -1, 1) * allocated *
                CASE discount_type
                  WHEN 'percentage' THEN retail_price * ((100 - discount) / 100)
                  WHEN 'relative' THEN (retail_price - discount) 
                  WHEN 'fixed' THEN (discount)
                  ELSE retail_price
                END),
              2) AS DECIMAL(9,2))
            untaxed,
            CAST(ROUND_TO_EVEN(
              SUM(IF(txn_line.taxfree, 0, 1) *
                IF(type = 'customer', -1, 1) * allocated *
                CASE discount_type
                  WHEN 'percentage' THEN retail_price * ((100 - discount) / 100)
                  WHEN 'relative' THEN (retail_price - discount) 
                  WHEN 'fixed' THEN (discount)
                  ELSE retail_price
                END),
              2) AS DECIMAL(9,2))
            taxed,
            tax_rate,
            CAST((SELECT SUM(amount) FROM payment WHERE txn.id = payment.txn)
                 AS DECIMAL(9,2)) AS Paid\$dollar
       FROM txn
       LEFT JOIN txn_line ON (txn.id = txn_line.txn)
       LEFT JOIN person ON (txn.person = person.id)
      WHERE person = $id
      GROUP BY txn.id
      ORDER BY created DESC
      LIMIT 50) t";

dump_table($db->query($q));
dump_query($q);

foot();
