<?
require 'scat.php';

head("Scat");
?>
<form method="get" action="items.php">
<input id="focus" type="text" name="q" size="100" value="<?=htmlspecialchars($q)?>">
<input type="submit" value="Find Items">
</form>
