<form role="form" method="get" action="items.php">
  <div class="input-group">
    <span class="input-group-btn">
      <input type="submit" class="btn btn-primary" value="Search">
    </span>
    <input id="autofocus" type="text" class="form-control" size="60" name="search" data-bind="value: search" placeholder="Enter keywords or scan barcode" value="<?=ashtml($_REQUEST['search'])?>">
    <span class="input-group-addon">
      <label><input type="checkbox" value="1" name="all" data-bind="checked: all"> Include inactive?</label>
    </span>
  </div>
</form>
<br>
<br>
