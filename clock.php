<?
include 'scat.php';

head("Clock @ Scat", true);

$q= "SELECT id, name,
            (SELECT start
               FROM timeclock
              WHERE person = person.id
                AND end IS NULL
              ORDER BY id DESC
              LIMIT 1) AS punched
       FROM person
      WHERE active AND role = 'employee'
      ORDER BY name";

$r= $db->query($q);

$people= array();
while ($person= $r->fetch_assoc()) {
  $people[]= $person;
}
?>
<div class="row">
  <div class="col-sm-offset-3 col-sm-6">
    <div class="alert alert-info">
      <strong>Click</strong> on a name to clock in or out.
    </div>
    <div class="list-group" data-bind="foreach: { data: people, as: 'person' }">
      <a href="#" class="list-group-item"
         data-bind="css: { 'list-group-item-success': punched() },
                    click: punch">
        <span data-bind="text: name"></span>
        <span class="badge" data-bind="text: punched"></span>
      </a>
    </div>
    <div id="clock-alert" class="alert alert-success" style="display: none">
    </div>
  </div>
</div>
<?
foot();
?>
<script>
var model= {
  people: <?=json_encode($people);?>,
};

var viewModel= ko.mapping.fromJS(model);

function punch(place, ev) {
  $.getJSON("api/clock-punch.php?callback=?",
            { id: place.id(), punched: place.punched() },
            function (data) {
              if (data.error) {
                displayError(data);
                return;
              }
              $.each(viewModel.people(), function (i, person) {
                if (person.id() == data.person.id) {
                  person.punched(data.person.punched);
                }
              });

              $('#clock-alert').hide().clearQueue();
              $('#clock-alert')
                .text("Clocked " + data.person.name + " " + data.action);
              $('#clock-alert')
                .fadeToggle()
                .delay(3000)
                .fadeToggle();
            });
}

ko.applyBindings(viewModel);

</script>
