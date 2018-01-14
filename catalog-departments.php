<?
require 'scat.php';
require 'lib/catalog.php';

head("Departments @ Scat", true);

$departments= Model::factory('Department')
                ->where('parent_id', 0)
                ->find_many();

?>
<script>
$(function() {

var model= {
  departments: [
<?
  /* Convert each row to an object and add a dummy departments array */
  echo join(", \n",
            array_map(function ($department) {
                      return json_encode(array_merge(
                                          $department->as_array(),
                                          array('departments' =>
                                                array())),
                                         JSON_PRETTY_PRINT);
                      },
                      $departments));
?>
  ]
};

var viewModel= ko.mapping.fromJS(model);

viewModel.showDepartment= function (department) {
  Scat.api('department-find', { parent: this.id() })
      .done(function (data) {
        department.departments(data);
      });
  return true;
}

ko.applyBindings(viewModel);

});
</script>
<div class="panel-group" id="accordion" role="tablist"
     aria-multiselectable="true"
     data-bind="foreach: departments">
  <div class="panel panel-default">
    <div class="panel-heading" role="tab"
         data-bind="attr: { id: 'heading' + $index() }">
      <h4 class="panel-title">
        <a class="collapsed" role="button" data-toggle="collapse"
           data-parent="#accordion"
           data-bind="attr: { href: '#collapse' + $index(),
                              'aria-controls': 'collapse' + $index() },
                      text: $data.name"
           aria-expanded="true"></a>
      </h4>
    </div>
    <div class="panel-collapse collapse" role="tabpanel"
         data-bind="attr: { id: 'collapse' + $index(),
                            'aria-labelledby': 'heading' + $index() },
                    event: { 'show.bs.collapse': $parent.showDepartment }">
      <ul class="list-group"
          data-bind="foreach: $data.departments">
        <a class="list-group-item"
           data-bind="text: $data.name,
                      attr: { href: 'catalog-department.php?id=' +
                                    $data.id }"></a>
      </ul>
    </div>
  </div>
</div>
<?
foot();
