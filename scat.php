<?
error_reporting(E_ALL & ~E_NOTICE);

date_default_timezone_set('America/Los_Angeles');

/* Start page timer */
$start_time= microtime();

/* $DEBUG can be set by config.php, not in request/ */
$DEBUG= false;
require dirname(__FILE__).'/config.php';

require dirname(__FILE__).'/lib/db.php';

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
 <link rel="stylesheet" type="text/css" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css">
 <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
 <script src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.16/jquery-ui.min.js"></script>
 <script src="js/jquery.tablesorter.min.js"></script>
 <script src="js/jquery.simplemodal.1.4.2.min.js"></script>
 <script src="js/jquery.data-selector.js"></script>
 <script src="js/jquery.hotkeys.js"></script>
 <script src="js/jquery.jeditable.mini.js"></script>
 <script src="js/date.js"></script>
 <script src="js/scat.js"></script>
 <script>
$(document).ready(function() { 
  // Enable sorted tables
  $(".sortable").tablesorter(); 
  // SimpleModal defaults
  $.modal.defaults.position= [ '25%', '25%' ];
  $.modal.defaults.overlayClose= true;
  // Focus the #focus item
  $("#focus").focus();
}); 
 </script>
</head>
<body>
<?if ($GLOBALS['DEBUG']) {?>
<header class="debug">
  <span style="float: right; color: #900; padding-right: 100px; font-weight: bold">DEBUG</span>
<?} else {?>
<header>
<?}?>
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
<a href="./till.php" title="Till"><img src="./icons/money.png" width="16" height="16" alt="Till"> Till</a>
&nbsp;
<a href="#" onclick="return false" id="reports" title="Reports"><img src="./icons/report.png" width="16" height="16" alt="Reports"> Reports</a>
</header>
<script>
$("header #reports").on('click', function() {
  $.getJSON("./api/report-sales.php?callback=?",
            { days: 7 },
            function(data) {
              if (data.error) {
                $.modal(data.error);
              } else {
                var t= $("<table><tr><th>Day<th>Sales</tr>");
                $.each(data.sales, function(i, sales) {
                  t.append($('<tr><td>' + sales.span +
                             '<td align="right">$' + sales.total.toFixed(2) +
                             '</tr>'));
                });
                t.modal();
              }
            });
});
</script>
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
 <div id="time">Page generated in $time seconds.</div>
 <div id="status"></div>
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

$db= new ScatDB();
if (!$db) die("mysqli_init failed");

if (!$db->real_connect(DB_SERVER,DB_USER,DB_PASSWORD,DB_SCHEMA))
  die('connect failed: ' . mysqli_connect_error());
$db->set_charset('utf8');

function dump_table($r, $calc = false) {
  $c= $meta= $line= 0;
  if (!$r) {
    echo 'Query failed: ', ashtml($GLOBALS['db']->error), '<br>';
    return;
  }
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
    $class= strchr($name, '$');
    if ($class == '$hide')
      continue;
    echo '<th>', strtok($name, '$'), '</th>';
  }
  if ($calc) {
    echo '<th>', strtok($calc, '$'), '</th>';
  }
  echo '</tr></thead>';
  echo '<tbody>';
  while ($row= $r->fetch_row()) {
    if ($r->fetch_field_direct(0)->name == 'code')
      $row[0]= '<a href="item.php?code='.$row[0].'">'.$row[0].'</a>';
    if ($meta) {
      echo '<tr class="', ashtml($row[0]), '">';
    } else {
      echo '<tr>';
    }
    if (!$line)
      echo '<td class="num">', ++$c, '</td>';
    for ($i= $meta; $i < $r->field_count; $i++) {
      $name= $r->fetch_field_direct($i)->name;
      $class= strchr($name, '$');
      if ($class == '$hide')
        continue;
      echo '<td', ($class ? ' class="' . ltrim($class, '$'). '"' : ''), '>',
           expand_field($row[$i], $class), '</td>';
    }
    if ($calc) {
      $func= strtok($calc, '$');
      $class= strchr($calc, '$');
      echo '<td', ($class ? ' class="' . ltrim($class, '$'). '"' : ''), '>',
           expand_field($func($row), $class), '</td>';
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
    $desc= array('correction' => 'Correction',
                 'drawer' => 'Till Count',
                 'customer' => 'Invoice',
                 'vendor' => 'Purchase Order');
    if ($type == 'customer') {
      return '<a href="./?number='.ashtml($number).'">'.$desc[$type].' '.ashtml($number).'</a>';
    } else {
      return '<a href="txn.php?id='.ashtml($id).'">'.$desc[$type].' '.ashtml($number).'</a>';
    }
  case '$person':
    list($id, $company, $name)= preg_split('/\|/', $data, 3);
    if (!$id) return '';
    return '<a href="person.php?id='.ashtml($id).'">'.ashtml($company).($name&&$company?" (":"").ashtml($name).($name&&$company?")":"").'</a>';
  case '$item':
    return '<a href="item.php?code='.ashtml($data).'">'.ashtml($data).'</a>';
  case '$dollar':
    if ($data == null)
      return $data;
    return amount($data);
  case '$payment':
    $desc= array('cash' => 'Cash',
                 'change' => 'Change',
                 'credit' => 'Credit Card',
                 'square' => 'Square',
                 'gift' => 'Gift Card',
                 'check' => 'Check',
                 'dwolla' => 'Dwolla',
                 'discount' => 'Discount',
                 'withdrawal' => 'Withdrawal',
                 'bad' => 'Bad Debt',
                 );
    return $desc[$data];
  case '$bool':
    if ($data) {
      return '<img data-truth="1" src="icons/accept.png" width="16" height="16">';
    } else {
      return '<img data-truth="0" src="icons/cross.png" width="16" height="16">';
    }
  case '$html':
    return $data;
  default:
    return ashtml($data);
  }
}

function ashtml($t) {
  return htmlspecialchars($t);
}

function amount($d) {
  return ($d < 0 ? '(' : '') . '$' . sprintf("%.2f", abs($d)) . ($d < 0 ? ')' : '');
}

function dump_query($q) {
  static $num;
  $num+= 1;
?>
<button onclick="$('#query_<?=$num?>').toggle('drop')">Show Query</button>
<pre id="query_<?=$num?>" class="debug" style="display: none"><?=ashtml($q)?></pre>
<?
}

function jsonp($data) {
  if (preg_match('/\W/', $_GET['callback'])) {
    // if $_GET['callback'] contains a non-word character,
    // this could be an XSS attack.
    header('HTTP/1.1 400 Bad Request');
    exit();
  }
  header('Content-type: application/json; charset=utf-8');
  if ($_GET['callback']) {
    return sprintf('%s(%s);', $_GET['callback'], json_encode($data));
  }
  return json_encode($data);
}

function die_jsonp($data) {
  if (ob_get_level()) {
    ob_end_clean();
  }
  if (!is_array($data)) {
    $data= array('error' => $data);
  }
  header('Content-type: application/javascript; charset=utf-8');
  die(sprintf('%s(%s);', $_GET['callback'], json_encode($data)));
}

function die_query($db, $query) {
  die_jsonp(array('error' => 'Query failed.',
                  'explain' =>  $db->error,
                  'query' => $query));
}
