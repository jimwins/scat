<?
/* Start page timer */
$start_time= microtime();

require dirname(__FILE__).'/config.php';

define('APP_NAME', 'ScatPOS');
define('VERSION', '0.0.1');
define('EPS_ApplicationID', '984');

/** Basic functions */

function head($title = "Scat") {
header("content-type: text/html;charset=utf-8");?>
<!DOCTYPE html>
<html>
<head>
 <title><?=ashtml($title)?></title>
 <link rel="stylesheet" type="text/css" href="static/screen.css">
 <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
 <script src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.16/jquery-ui.min.js"></script>
 <script src="js/jquery.tablesorter.min.js"></script>
 <script>
$(document).ready(function() 
    { 
        $(".sortable").tablesorter(); 
        $("#focus").focus();
    } 
); 
 </script>
</head>
<body>
<header>
<?/*<a href="./" title="New Sale"><img src="./icons/house.png" width="16" height="16" alt="New Sale"> New Sale</a>
&nbsp;*/?>
<a href="./" title="New Sale"><img src="./icons/cart.png" width="16" height="16" alt="New Sale"> New Sale</a>
&nbsp;
<a href="./items.php" title="Items"><img src="./icons/tag_blue.png" width="16" height="16" alt="Items"> Items</a>
&nbsp;
<a href="./person.php" title="People"><img src="./icons/group.png" width="16" height="16" alt="People"> People</a>
&nbsp;
<a href="./txns.php" title="Transactions"><img src="./icons/table.png" width="16" height="16" alt="Transactions"> Transactions</a>
&nbsp;
<a href="#" onclick="return false" title="Reports"><img src="./icons/report.png" width="16" height="16" alt="Reports"> Reports</a>
</header>
<?
}

function foot() {
  global $start_time;
  $finish_time= microtime();

  list($secs, $usecs)= explode(' ', $start_time);
  $start= $secs + $usecs;

  list($secs, $usecs)= explode(' ', $finish_time);
  $finish= $secs + $usecs;

  $time= sprintf("%0.3f", $finish - $start);

  echo <<<FOOTER
<footer>
 <p class="time">Page generated in $time seconds</p>
</footer>
FOOTER;
}

if (!defined('DB_SERVER') ||
    !defined('DB_USER') ||
    !defined('DB_PASSWORD') ||
    !defined('DB_SCHEMA')) {
  head("Scat Configuration");
  $msg= <<<CONFIG
<p>You must configure Scat to connect to your database. Create
<code>config.php</code> and add the following code, configured as appropriate
for your setup:
<pre>
&lt;?
/* Database configuration */
define('DB_SERVER', 'localhost');
define('DB_USER', 'scat');
define('DB_PASSWORD', 'scat');
define('DB_SCHEMA', 'scat');
</pre>
CONFIG;
  die($msg);
}

$db= mysqli_init();
if (!$db) die("mysqli_init failed");

if (!$db->real_connect(DB_SERVER,DB_USER,DB_PASSWORD,DB_SCHEMA))
  die('connect failed: ' . mysqli_connect_error());
$db->set_charset('utf8');

function dump_table($r, $calc = false) {
  $c= $meta= $line= 0;
  if (!$r->num_rows) {
    echo 'No results.';
    return;
  }
  if (!strcmp($r->fetch_field_direct(0)->name, "meta")) {
    $meta= 1;
  }
  if (!strncmp($r->fetch_field_direct($meta)->name, "#", 1)) {
    $line= 1;
  }
  echo '<table class="sortable">';
  echo '<thead><tr>';
  if (!$line)
    echo '<th>#</th>';
  for ($i= $meta; $i < $r->field_count; $i++) {
    $name= $r->fetch_field_direct($i)->name;
    echo '<th>', strtok($name, '$'), '</th>';
  }
  if ($calc) {
    echo '<th>', $calc, '<th>';
  }
  echo '</tr></thead>';
  echo '<tbody>';
  while ($row= $r->fetch_row()) {
    if ($r->fetch_field_direct(0)->name == 'code')
      $row[0]= '<a href="item.php?code='.$row[0].'">'.$row[0].'</a>';
    if ($calc) {
      $row[]= $calc($row);
    }
    if ($meta) {
      echo '<tr class="', ashtml($row[0]), '">';
    } else {
      echo '<tr>';
    }
    if (!$line)
      echo '<td class="num">', ++$c, '</td>';
    for ($i= $meta; $i < $r->field_count; $i++) {
      $name= $r->fetch_field_direct($i)->name;
      $class= strlen($row[$i]) ? strchr($name, '$') : '';
      echo '<td', ($class ? ' class="' . ltrim($class, '$'). '"' : ''), '>',
           expand_field($row[$i], $class), '</td>';
    }
    echo '</tr>';
  }
  echo '</tbody>';
  echo '</table>';
}

function expand_field($data, $class) {
  switch ($class) {
  case '$txn':
    list($id, $type, $number)= preg_split('/\|/', $data);
    $desc= array('internal' => 'Transfer',
                 'customer' => 'Invoice',
                 'vendor' => 'Purchase Order');
    return '<a href="txn.php?id='.ashtml($id).'">'.$desc[$type].' '.ashtml($number).'</a>';
  case '$person':
    list($id, $company, $name)= preg_split('/\|/', $data, 3);
    if (!$id) return '';
    return '<a href="person.php?id='.ashtml($id).'">'.ashtml($company).($name&&$company?" (":"").ashtml($name).($name&&$company?")":"").'</a>';
  case '$item':
    return '<a href="item.php?code='.ashtml($data).'">'.ashtml($data).'</a>';
  default:
    return ashtml($data);
  }
}

function ashtml($t) {
  return htmlspecialchars($t);
}

function amount($d) {
  return ($d < 0 ? '(' : '') . '$' . abs($d) . ($d < 0 ? '(' : '');
}

function dump_query($q) {
  static $num;
  $num+= 1;
?>
<button onclick="$('#query_<?=$num?>').toggle('drop')">Show Query</button>
<pre id="query_<?=$num?>" class="debug" style="display: none"><?=ashtml($q)?></pre>
<?
}
