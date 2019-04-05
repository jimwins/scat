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
      <input type="text" class="form-control" id="w" data-bind="textInput: w">
    </div>
    <div class="col-sm-2">
      <p class="form-control-static" data-bind="text: w_in() + ' in'"></p>
    </div>
  </div>
  <div class="form-group">
    <label for="h" class="col-sm-2 control-label">Height</label>
    <div class="col-sm-4">
      <input type="text" class="form-control" id="h" data-bind="textInput: h">
    </div>
    <div class="col-sm-2">
      <p class="form-control-static" data-bind="text: h_in() + ' in'"></p>
    </div>
  </div>
  <div class="row">
    <div class="col-sm-4">
      <div class="panel panel-default">
        <div class="panel-heading"><h3 class="panel-title">Canvas</h3></div>
        <div class="panel-body">
          <div class="form-group">
            <label for="slim" class="col-sm-6 control-label">
              Slim (&frac34;")
            </label>
            <div class="col-sm-6">
              <p class="form-control-static"
                 data-bind="text: amount(ui() * 0.70)"></p>
            </div>
          </div>
          <div class="form-group">
            <label for="thick" class="col-sm-6 control-label">
              Thick (1&frac12;")
            </label>
            <div class="col-sm-6">
              <p class="form-control-static"
                 data-bind="text: (Math.max(w(), h()) > 40)
                                   ? 'Too big!'
                                   : amount(ui() * 1.00)"></p>
            </div>
          </div>
          <div class="form-group">
            <label for="chunky" class="col-sm-6 control-label">
              Chunky (1&frac12;")
            </label>
            <div class="col-sm-6">
              <p class="form-control-static"
                 data-bind="text: amount(ui() * 2.00)"></p>
            </div>
          </div>
          <div class="form-group">
            <label for="epic" class="col-sm-6 control-label">
              Epic (2&frac12;")
            </label>
            <div class="col-sm-6">
              <p class="form-control-static"
                 data-bind="text: amount(ui() * 3.00)"></p>
            </div>
          </div>
        </div>
      </div><!-- /.panel -->
    </div><!-- /.col-sm-4 -->
    <div class="col-sm-4">
      <div class="panel panel-default">
        <div class="panel-heading">
          <h3 class="panel-title">Floater Frame</h3>
        </div>
        <div class="panel-body">
          <div class="form-group">
            <label for="slim" class="col-sm-7 control-label">
              Natural Slim (&frac34;")
            </label>
            <div class="col-sm-5">
              <p class="form-control-static"
                 data-bind="text: (Math.max(w(), h()) > 72)
                                   ? 'Too big!'
                                   : amount(ui() * 1.00)"></p>
            </div>
          </div>
          <div class="form-group">
            <label for="slim-finished" class="col-sm-7 control-label">
              Finished Slim (&frac34;")
            </label>
            <div class="col-sm-5">
              <p class="form-control-static"
                 data-bind="text: (Math.max(w(), h()) > 72)
                                   ? 'Too big!'
                                   : amount(ui() * 1.50)"></p>
            </div>
          </div>
          <div class="form-group">
            <label for="thick" class="col-sm-7 control-label">
              Natural Thick (1&frac12;")
            </label>
            <div class="col-sm-5">
              <p class="form-control-static"
                 data-bind="text: (Math.max(w(), h()) > 72)
                                   ? 'Too big!'
                                   : amount(ui() * 1.50)"></p>
            </div>
          </div>
          <div class="form-group">
            <label for="thick-finished" class="col-sm-7 control-label">
              Finished Thick (1&frac12;")
            </label>
            <div class="col-sm-5">
              <p class="form-control-static"
                 data-bind="text: (Math.max(w(), h()) > 72)
                                   ? 'Too big!'
                                   : amount(ui() * 2.50)"></p>
            </div>
          </div>
        </div>
      </div><!-- /.panel -->
    </div><!-- /.col-sm-4 -->
    <div class="col-sm-4">
      <div class="panel panel-default">
        <div class="panel-heading"><h3 class="panel-title">Birch Panel</h3></div>
        <div class="panel-body">
          <div class="form-group">
            <label for="uncradled" class="col-sm-6 control-label">
              Uncradled (&frac14;")
            </label>
            <div class="col-sm-6">
              <p class="form-control-static"
                 data-bind="text: (Math.min(w(), h()) > 60)
                                   ? 'Too big!'
                                   : amount(ui() * 0.59)"></p>
            </div>
          </div>
          <div class="form-group">
            <label for="thick" class="col-sm-6 control-label">
              Thick (1&frac34;")
            </label>
            <div class="col-sm-6">
              <p class="form-control-static"
                 data-bind="text: (Math.min(w(), h()) > 60)
                                   ? 'Too big!'
                                   : (Math.min(w(), h()) >= 48)
                                      ? amount(ui() * 2)
                                      : amount(ui() * 1.5)"></p>
            </div>
          </div>
          <div class="form-group">
            <label for="epic" class="col-sm-6 control-label">
              Epic (2&frac34;")
            </label>
            <div class="col-sm-6">
              <p class="form-control-static"
                 data-bind="text: (Math.min(w(), h()) > 60)
                                   ? 'Too big!'
                                   : (Math.min(w(), h()) >= 48)
                                      ? amount(ui() * 3)
                                      : amount(ui() * 2)"></p>
            </div>
          </div>
        </div>
      </div><!-- /.panel -->
    </div><!-- /.col-sm-4 -->
  </div><!-- /.row -->
  <div class="row">
    <div class="col-sm-4">
      <div class="panel panel-default">
        <div class="panel-heading">
          <h3 class="panel-title">Stretch Canvas</h3>
        </div>
        <div class="panel-body">
          <div class="form-group">
            <label for="slim" class="col-sm-6 control-label">
              &frac34;&quot; Stretch
            </label>
            <div class="col-sm-6">
              <p class="form-control-static"
                 data-bind="text: amount(ui() * 1.5)"></p>
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
                                   : amount(ui() * 1.7)"></p>
            </div>
          </div>
          <div class="form-group">
            <label for="chunky" class="col-sm-6 control-label">
              1&frac12;&quot; Stretch
            </label>
            <div class="col-sm-6">
              <p class="form-control-static"
                 data-bind="text: amount(ui() * 2)"></p>
            </div>
          </div>
        </div>
      </div><!-- /.panel -->
    </div><!-- /.col-sm-4 -->
    <div class="col-sm-4">
      <div class="panel panel-default">
        <div class="panel-heading"><h3 class="panel-title">Printing</h3></div>
        <div class="panel-body">
          <div class="form-group">
            <label for="photo" class="col-sm-7 control-label">
              Photo
            </label>
            <div class="col-sm-5">
              <p class="form-control-static"
                 data-bind="text: amount(Math.max(262, area())/144 * 9.95)"></p>
            </div>
          </div>
          <div class="form-group">
            <label for="metallic" class="col-sm-7 control-label">
              Metallic
            </label>
            <div class="col-sm-5">
              <p class="form-control-static"
                 data-bind="text: amount(Math.max(200, area())/144 * 13.95)"></p>
            </div>
          </div>
          <div class="form-group">
            <label for="fine" class="col-sm-7 control-label">
              Fine Art (WC, Smooth)
            </label>
            <div class="col-sm-5">
              <p class="form-control-static"
                 data-bind="text: amount(Math.max(144, area())/144 * 19.95)"></p>
            </div>
          </div>
          <div class="form-group">
            <label for="canvas-decor" class="col-sm-7 control-label">
              Canvas (Decorative)
            </label>
            <div class="col-sm-5">
              <p class="form-control-static"
                 data-bind="text: amount(Math.max(80, area())/144 * 19.95)"></p>
            </div>
          </div>
          <div class="form-group">
            <label for="canvas-archival" class="col-sm-7 control-label">
              Canvas (Archival)
            </label>
            <div class="col-sm-5">
              <p class="form-control-static"
                 data-bind="text: amount(Math.max(80, area())/144 * 24.95)"></p>
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

  self.w= ko.observable('8');
  self.h= ko.observable('10');

  self.w_in= ko.computed(function() {
    if (self.w().match(/cm/)) {
      return (parseFloat(self.w()) / 2.54).toFixed(3);
    }
    return parseFloat(self.w());
  });

  self.h_in= ko.computed(function() {
    if (self.h().match(/cm/)) {
      return (parseFloat(self.h()) / 2.54).toFixed(3);
    }
    return parseFloat(self.h());
  });


  self.ui= ko.computed(function() {
    return parseFloat(self.w_in()) + parseFloat(self.h_in());
  });

  self.area= ko.computed(function() {
    return parseFloat(self.w_in()) * parseFloat(self.h_in());
  });
};

ko.applyBindings(new CalcModel());
</script>
<?
foot();
