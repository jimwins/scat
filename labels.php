<?
include 'scat.php';

head("labels");
?>
 <form method="post" action="print/labels.php">
  Include:<br>
  <textarea name="in" rows=5 cols=60></textarea>
  <br>
  Exclude:<br>
  <textarea name="ex" rows=5 cols=60></textarea>
  <br>
  <input type="submit" value="Generate">
 </form>
<?
foot();
