<?
require 'scat.php';
require 'lib/item.php';

ob_start();

head("Custom @ Scat", true);
?>
<form id="dims" class="form-horizontal" role="form">
  <div class="form-group">
    <label for="w" class="col-sm-2 control-label">Width</label>
    <div class="col-sm-4">
      <input type="text" class="form-control" id="w" data-bind="value: w">
    </div>
  </div> 
  <div class="form-group">
    <label for="h" class="col-sm-2 control-label">Height</label>
    <div class="col-sm-4">
      <input type="text" class="form-control" id="h" data-bind="value: h">
    </div>
  </div> 
  <div class="row">
    <div class="col-sm-4">
      <div class="panel panel-default">
        <div class="panel-heading">Stretch Canvas</div>
        <div class="panel-body">
          <div class="form-group">
            <label for="slim" class="col-sm-6 control-label">
              &frac34;&quot; Stretch
            </label>
            <div class="col-sm-6">
              <p class="form-control-static"
                 data-bind="text: amount(ui() * 1.2)">$0.00</p>
            </div>
          </div> 
          <div class="form-group">
            <label for="thick" class="col-sm-6 control-label">
              &frac34;&quot; Flip Stretch
            </label>
            <div class="col-sm-6">
              <p class="form-control-static"
                 data-bind="text: (Math.max(w(), h()) > 40)
                                   ? 'Too big!'
                                   : amount(ui() * 1.5)">$0.00</p>
            </div>
          </div> 
          <div class="form-group">
            <label for="chunky" class="col-sm-6 control-label">
              1&frac12;&quot; Stretch
            </label>
            <div class="col-sm-6">
              <p class="form-control-static"
                 data-bind="text: amount(ui() * 1.8)">$0.00</p>
            </div>
          </div> 
        </div>
      </div><!-- /.panel -->
    </div><!-- /.col-sm-4 -->
    <div class="col-sm-4">
      <div class="panel panel-default">
        <div class="panel-heading">Print</div>
        <div class="panel-body">
          <div class="form-group">
            <label for="photo" class="col-sm-6 control-label">
              Photo
            </label>
            <div class="col-sm-6">
              <p class="form-control-static"
                 data-bind="text: amount(Math.max(80, area())/144 * 9.95)">$0.00</p>
            </div>
          </div> 
          <div class="form-group">
            <label for="fine" class="col-sm-6 control-label">
              Fine Art
            </label>
            <div class="col-sm-6">
              <p class="form-control-static"
                 data-bind="text: amount(Math.max(80, area())/144 * 14.95)">$0.00</p>
            </div>
          </div> 
          <div class="form-group">
            <label for="premium" class="col-sm-6 control-label">
              Premium
            </label>
            <div class="col-sm-6">
              <p class="form-control-static"
                 data-bind="text: amount(Math.max(80, area())/144 * 19.95)">$0.00</p>
            </div>
          </div> 
        </div>
      </div><!-- /.panel -->
    </div><!-- /.col-sm-4 -->
    <div class="col-sm-4">
      <div class="panel panel-default">
        <div class="panel-heading">Panel</div>
        <div class="panel-body">
          <div class="form-group">
            <label for="uncradled" class="col-sm-6 control-label">
              Uncradled
            </label>
            <div class="col-sm-6">
              <p class="form-control-static"
                 data-bind="text: amount(ui() * 0.49)">$0.00</p>
            </div>
          </div> 
          <div class="form-group">
            <label for="thick" class="col-sm-6 control-label">
              Thick
            </label>
            <div class="col-sm-6">
              <p class="form-control-static"
                 data-bind="text: amount(ui() * 0.79)">$0.00</p>
            </div>
          </div> 
          <div class="form-group">
            <label for="epic" class="col-sm-6 control-label">
              Epic
            </label>
            <div class="col-sm-6">
              <p class="form-control-static"
                 data-bind="text: amount(ui() * 0.99)">$0.00</p>
            </div>
          </div> 
        </div>
      </div><!-- /.panel -->
    </div><!-- /.col-sm-4 -->
    <div class="col-sm-4">
      <div class="panel panel-default">
        <div class="panel-heading">Canvas</div>
        <div class="panel-body">
          <div class="form-group">
            <label for="slim" class="col-sm-6 control-label">
              Slim
            </label>
            <div class="col-sm-6">
              <p class="form-control-static"
                 data-bind="text: amount(ui() * 0.49)">$0.00</p>
            </div>
          </div> 
          <div class="form-group">
            <label for="thick" class="col-sm-6 control-label">
              Thick
            </label>
            <div class="col-sm-6">
              <p class="form-control-static"
                 data-bind="text: amount(ui() * 0.75)">$0.00</p>
            </div>
          </div> 
          <div class="form-group">
            <label for="chunky" class="col-sm-6 control-label">
              Chunky
            </label>
            <div class="col-sm-6">
              <p class="form-control-static"
                 data-bind="text: amount(ui() * 1.49)">$0.00</p>
            </div>
          </div> 
          <div class="form-group">
            <label for="epic" class="col-sm-6 control-label">
              Epic
            </label>
            <div class="col-sm-6">
              <p class="form-control-static"
                 data-bind="text: amount(ui() * 1.99)">$0.00</p>
            </div>
          </div> 
        </div>
      </div><!-- /.panel -->
    </div><!-- /.col-sm-4 -->
    <div class="col-sm-4">
      <div class="panel panel-default">
        <div class="panel-heading">Floater Frame</div>
        <div class="panel-body">
          <div class="form-group">
            <label for="slim" class="col-sm-6 control-label">
              Natural Slim
            </label>
            <div class="col-sm-6">
              <p class="form-control-static"
                 data-bind="text: amount(ui() * 0.69)">$0.00</p>
            </div>
          </div> 
          <div class="form-group">
            <label for="slim-finished" class="col-sm-6 control-label">
              Finished Slim
            </label>
            <div class="col-sm-6">
              <p class="form-control-static"
                 data-bind="text: amount(ui() * 0.89)">$0.00</p>
            </div>
          </div> 
          <div class="form-group">
            <label for="thick" class="col-sm-6 control-label">
              Natural Thick
            </label>
            <div class="col-sm-6">
              <p class="form-control-static"
                 data-bind="text: amount(ui() * 1.19)">$0.00</p>
            </div>
          </div> 
          <div class="form-group">
            <label for="thick-finished" class="col-sm-6 control-label">
              Finished Thick
            </label>
            <div class="col-sm-6">
              <p class="form-control-static"
                 data-bind="text: amount(ui() * 1.49)">$0.00</p>
            </div>
          </div> 
        </div>
      </div><!-- /.panel -->
    </div><!-- /.col-sm-4 -->
  </div><!-- /.row -->
</form>
<script>
function CalcModel() {
  var self= this;

  self.w= ko.observable(8);
  self.h= ko.observable(10);

  self.ui= ko.computed(function() {
    return parseFloat(self.w()) + parseFloat(self.h());
  });

  self.area= ko.computed(function() {
    return parseFloat(self.w()) * parseFloat(self.h());
  });
};

ko.applyBindings(new CalcModel());
</script>
<?
foot();
