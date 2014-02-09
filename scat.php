<?
error_reporting(E_ALL & ~E_NOTICE);

date_default_timezone_set('America/Los_Angeles');

if (get_magic_quotes_gpc())
  die("Sorry, you need to disable magic quotes for Scat to work.");

/* Start page timer */
$start_time= microtime();

/* $DEBUG can be set by config.php, not in request/ */
$DEBUG= false;
require dirname(__FILE__).'/config.php';

require dirname(__FILE__).'/lib/db.php';

define('APP_NAME', 'ScatPOS');
define('VERSION', '0.6.0');
define('EPS_ApplicationID', '984');

/** Basic functions */

function head($title = "Scat", $allnew= false) {
header("content-type: text/html;charset=utf-8");?>
<!DOCTYPE html>
<html>
<head>
 <title><?=ashtml($title)?></title>
 <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css">
 <link rel="stylesheet" type="text/css" href="css/font-awesome.min.css">
 <link rel="stylesheet" type="text/css" href="css/datepicker3.css">
 <link rel="stylesheet" type="text/css" href="static/screen.css">
<?if (!$allnew) {?>
  <link rel="stylesheet" type="text/css" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.23/themes/base/jquery-ui.css">
<?}?>
<?if ($GLOBALS['DEBUG']) {?>
  <link rel="stylesheet" type="text/css" href="css/debug.css">
<?}?>
 <style type="text/css">
   body.page {
     padding-top: 70px;
   }
 </style>
 <script src="js/jquery.min.js"></script>
 <script src="js/bootstrap.min.js"></script>
 <script src="js/bootstrap-datepicker.js"></script>
 <script src="lib/knockout/knockout-3.0.0.js"></script>
 <script src="lib/knockout/knockout.mapping-2.4.1.js"></script>
 <script src="js/jquery.tablesorter.min.js"></script>
 <script src="js/jquery.html5uploader.js"></script>
<?if (!$allnew) {?>
 <script src="js/jquery-ui.min.js"></script>
 <script src="js/jquery.simplemodal.1.4.4.min.js"></script>
 <script src="js/jquery.data-selector.js"></script>
 <script src="js/jquery.hotkeys.js"></script>
 <script src="js/jquery.jeditable.mini.js"></script>
 <script src="js/date.js"></script>
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
<?} else {?>
 <script>
$(document).ready(function() { 
  // Focus the #focus item
  $("#focus").focus();
  // dynamically set active navbar link based on script
  var page= '<?=basename($_SERVER['SCRIPT_NAME'])?>';
  $("#navbar a[href='./" + page + "']").parent().addClass('active');
}); 
 </script>
<?}?>
 <script src="js/scat.js"></script>
</head>
<body class="page">
<header class="navbar navbar-default navbar-fixed-top" role="navigation">
  <div class="container">
    <div class="navbar-header">
      <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
        <span class="sr-only">Toggle navigation</span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
      </button>
      <span class="navbar-brand"><i class="fa fa-barcode"></i> Scat</span>
    </div>
    <div class="collapse navbar-collapse">
      <ul id="navbar" class="nav navbar-nav">
        <li><a href="./">New Sale</a></li>
        <li><a href="./items.php">Items</a></li>
        <li><a href="./person.php">People</a></li>
        <li><a href="./txns.php">Transactions</a></li>
        <li><a href="./till.php">Till</a></li>
        <li class="dropdown">
          <a href="#" class="dropdown-toggle" data-toggle="dropdown">Reports <b class="caret"></b></a>
          <ul class="dropdown-menu">
            <li><a id="reports" href="#">Quick</a></li>
            <li><a href="report.php">Sales by Date</a></li>
            <li><a href="report-items.php">Sales by Item</a></li>
            <li><a href="report-daily.php">Daily Flow</a></li>
            <li role="presentation" class="divider"></li>
            <li role="presentation" class="dropdown-header">Inventory</li>
            <li><a href="reorder.php">Reorder</a></li>
            <li><a href="vendor-upload.php">Upload Vendor Data</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</header>
<script>
$("header #reports").on('click', function(ev) {
  ev.preventDefault();
  $.getJSON("./api/report-sales.php?callback=?",
            { days: 7 },
            function(data) {
              if (data.error) {
                alert(data.error);
              } else {
                var t= $("<table class='table table-condensed table-striped' style='width: auto'><tr><th>Day<th>Sales</tr>");
                $.each(data.sales, function(i, sales) {
                  t.append($('<tr><td>' + sales.span +
                             '<td align="right">$' + sales.total.toFixed(2) +
                             '</tr>'));
                });
<?if ($allnew) {?>
                $('#quick-report .modal-body').append(t);
                $('#quick-report').modal('show');
<?} else {?>
                t.modal();
<?}?>
              }
            });
});
</script>
<div id="quick-report" class="modal fade">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h4 class="modal-title">Quick Report</h4>
      </div>
      <div class="modal-body">
      </div>
    </div>
  </div>
</div>
<?if ($GLOBALS['DEBUG']) {?>
  <div id="corner-banner">DEBUG</div>
<?}?>
<div class="container">
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
</div><!-- .container -->
<footer>
 <div id="time">Page generated in $time seconds.</div>
 <div id="status">&nbsp;</div>
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
  echo '<table class="table table-striped table-condensed sortable">';
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
      return '<i data-truth="1" class="fa fa-check-square-o"></i>';
    } else {
      return '<i data-truth="0" class="fa fa-square-o"></i>';
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
  if (!$GLOBALS['DEBUG']) return;
?>
<button onclick="$('#query_<?=$num?>').toggle('drop')" class="btn btn-default">Show Query</button>
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

function generate_upc($code) {
  assert(strlen($code) == 11);
  $check= 0;
  foreach (range(0,10) as $digit) {
    $check+= $code[$digit] * (($digit % 2) ? 1 : 3);
  }

  $cd= 10 - ($check % 10);
  if ($cd == 10) $cd= 0;

  return $code.$cd;
}

