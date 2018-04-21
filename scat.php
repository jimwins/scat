<?
error_reporting(E_ALL & ~E_NOTICE);

date_default_timezone_set('America/Los_Angeles');

if (get_magic_quotes_gpc())
  die("Sorry, you need to disable magic quotes for Scat to work.");

bcscale(2);

/* Start page timer */
$start_time= microtime();

/* $DEBUG can be set by config.php, not in request */
$DEBUG= false;
require dirname(__FILE__).'/config.php';

require dirname(__FILE__).'/vendor/autoload.php';

require dirname(__FILE__).'/lib/db.php';

define('APP_NAME', 'ScatPOS');
define('VERSION', '0.6.0');

/** Basic functions */

function head($title = "Scat", $allnew= false) {
header("content-type: text/html;charset=utf-8");?>
<!DOCTYPE html>
<html>
<head>
 <title><?=ashtml($title)?></title>
 <meta name="viewport" content="width=device-width, initial-scale=1">
 <link rel="stylesheet" type="text/css" href="extern/bootstrap/css/bootstrap.min.css">
 <link rel="stylesheet" type="text/css" href="components/font-awesome/css/font-awesome.min.css">
 <link rel="stylesheet" type="text/css" href="extern/bootstrap-datepicker-1.7.1/css/bootstrap-datepicker3.min.css">
 <link rel="stylesheet" type="text/css" href="css/scat.css">
<?if (!$allnew) {?>
  <link rel="stylesheet" type="text/css" href="css/jquery-ui.css">
<?}?>
<?if ($GLOBALS['DEBUG']) {?>
  <link rel="stylesheet" type="text/css" href="css/debug.css">
<?}?>
 <style type="text/css">
   body.page {
     padding-top: 70px;
   }
 </style>
 <script src="components/jquery/jquery.min.js"></script>
 <script src="extern/bootstrap/js/bootstrap.min.js"></script>
 <script src="extern/bootstrap-datepicker-1.7.1/js/bootstrap-datepicker.min.js"></script>
 <script src="extern/knockout/knockout-3.4.2.js"></script>
 <script src="extern/knockout/knockout.mapping-2.4.1.js"></script>
 <script src="js/jquery.tablesorter.min.js"></script>
 <script src="js/jquery.html5uploader.js"></script>
 <script src="js/jquery.jeditable.js"></script>
 <script src="js/jquery.dragbetter.js"></script>
 <script src="js/jquery.event.ue.js"></script>
 <script src="js/jquery.udraggable.js"></script>
 <script src="vendor/flesler/jquery.scrollto/jquery.scrollTo.min.js"></script>
 <script src="components/moment/min/moment.min.js"></script>
 <script src="extern/chartjs-2.7.0/Chart.min.js"></script>
 <script src="extern/marked-0.3.12/marked.min.js"></script>
<?if (!$allnew) {?>
 <script src="js/jquery-ui.min.js"></script>
 <script src="js/jquery.simplemodal.1.4.4.min.js"></script>
 <!-- next 1 used in index.php -->
 <script src="js/jquery.hotkeys.js"></script>
 <script>
$(document).ready(function() { 
  // Enable sorted tables
  $(".sortable").tablesorter(); 
  // SimpleModal defaults
  $.smodal.defaults.position= [ '25%', '25%' ];
  $.smodal.defaults.overlayClose= true;
  // Focus the #focus item
  $("#focus").focus();
}); 
 </script>
<?} else {?>
 <script>
$(document).ready(function() { 
  // Enable sorted tables
  $(".sortable").tablesorter(); 
  // Focus the #focus item
  $("#focus").focus();
  // dynamically set active navbar link based on script
  var page= '<?=basename($_SERVER['SCRIPT_NAME'])?>';
  $("#navbar a[href='./" + page + "']").parent().addClass('active');
  // Default Chart text color
  Chart.defaults.global.defaultFontColor= '#000';
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
      <span id="show-notes" class="navbar-brand">
        <i class="fa fa-barcode"></i>
        Scat
        <span class="badge"></span>
      </span>
    </div>
    <div class="collapse navbar-collapse">
      <ul id="navbar" class="nav navbar-nav">
        <li><a href="./">New Sale</a></li>
        <li><a href="./items.php">Items</a></li>
        <!-- Hide Gift Cards menu on tablet for size reasons -->
        <li class="hidden-sm"><a href="./gift-card.php">Gift Cards</a></li>
        <li><a href="./custom.php">Custom</a></li>
        <li><a href="./people.php">People</a></li>
        <li><a href="./txns.php">Transactions</a></li>
        <li><a href="./clock.php">Clock</a></li>
        <li class="dropdown">
          <a href="#" class="dropdown-toggle" data-toggle="dropdown">Catalog <b class="caret"></b></a>
          <ul class="dropdown-menu">
            <li><a href="catalog-brands.php">Brands</a></li>
            <li><a href="catalog-departments.php">Departments</a></li>
          </ul>
        </li>
        <li class="dropdown">
          <a href="#" class="dropdown-toggle" data-toggle="dropdown">Reports <b class="caret"></b></a>
          <ul class="dropdown-menu">
            <li><a id="reports" href="#">Quick</a></li>
            <li><a href="report-summary.php">Daily Summary</a></li>
            <li><a href="report.php">Sales by Date</a></li>
            <li><a href="report-items.php">Sales by Item</a></li>
            <li><a href="report-clock.php">Clock</a></li>
            <li role="presentation" class="divider"></li>
            <li role="presentation" class="dropdown-header">Inventory</li>
            <li><a href="reorder.php">Reorder</a></li>
            <li><a href="report-backorders.php">Backorders</a></li>
            <li><a href="report-price-change.php">Price Changes</a></li>
            <li><a href="price-overrides.php">Price Overrides</a></li>
            <li role="presentation" class="divider"></li>
            <li role="presentation" class="dropdown-header">Money</li>
            <li><a href="./till.php">Till</a></li>
            <li><a href="report-daily.php">Daily Flow</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</header>
<script>
$("header #reports").on('click', function(ev) {
  ev.preventDefault();
  Scat.api('report-sales', { days: 7 })
      .done(function(data) {
        var t= $("<table class='table table-condensed table-striped' style='width: auto'><tr><th>Day<th>Sales</tr>");
        $.each(data.sales, function(i, sales) {
          t.append($('<tr><td>' + sales.span +
                     '<td align="right">' + amount(sales.total.toFixed(2)) +
                     '</tr>'));
        });
        $('#quick-report .modal-body').empty().append(t);
        $('#quick-report').modal('show');
      });
});

$("#show-notes").on('click', function(ev) {
  Scat.showNotes({ todo: 1});
});

Scat.showNotes= function (options) {
  Scat.dialog('show-notes').done(function (html) {
    var panel= $(html);

    var data= { notes: [], people: [],
                content: '', todo: 0, public: 0, parent_id: 0,
                kind: 'general', attach_id: 0, person_id: 0 }
    $.extend(data, options);
    var dataModel= ko.mapping.fromJS(data);

    /* Load notes */
    Scat.api('note-find', options)
        .done(function (data) {
          dataModel.notes(data);
        });

    /* Load employees */
    Scat.api('person-list', { role: 'employee' })
        .done(function (data) {
          ko.mapping.fromJS({ people: data }, dataModel);
          dataModel.person_id.valueHasMutated();
        })
      .fail(function (jqxhr, textStatus, error) {
        var data= $.parseJSON(jqxhr.responseText);
        vendor_item.error(textStatus + ', ' + error + ': ' + data.text)
      });

    dataModel.toggleTodo= function(place, ev) {
      Scat.api('note-update', { id: place.id,
                                todo: place.todo == '0' ? 1 : 0 })
          .done(function (data) {
            for (var i= 0; i < dataModel.notes().length; i++) {
              if (place.id === dataModel.notes()[i].id) {
                var note= dataModel.notes.splice(i, 1); // remove it
                $.extend(note[0], data);
                dataModel.notes.splice(i, 0, note[0]); // add the new one
                return;
              }
            }
          });
    }

    dataModel.showChildren= function(place, ev) {
      if (dataModel.parent_id() == place.id) {
        dataModel.parent_id(0);
      } else {
        dataModel.parent_id(place.id);
      }

      Scat.api('note-find', { parent_id: place.id, limit: place.children })
          .done(function (data) {
            for (var i= 0; i < dataModel.notes().length; i++) {
              if (place.id === dataModel.notes()[i].id) {
                var note= dataModel.notes.splice(i, 1); // remove it
                note[0].kids= data;
                note[0].children= note[0].kids.length;
                note[0].showingKids= true;
                dataModel.notes.splice(i, 0, note[0]); // add it back
                return;
              }
            }
          });
    }

    dataModel.hideChildren= function(place, ev) {
      dataModel.parent_id(0);

      for (var i= 0; i < dataModel.notes().length; i++) {
        if (place.id === dataModel.notes()[i].id) {
          var note= dataModel.notes.splice(i, 1); // remove it
          note[0].showingKids= false;
          dataModel.notes.splice(i, 0, note[0]); // add it back
          return;
        }
      }
    }

    dataModel.addNote= function(place, ev) {
      var data= ko.mapping.toJS(dataModel); 
      data.todo= data.todo ? 1 : 0; // knockout 'checked' is annoying
      data.public= data.public ? 1 : 0;
      delete data.notes; delete data.people;

      Scat.api('note-add', data)
          .done(function (data) {
            if (data.parent_id != '0') {
              for (var i= 0; i < dataModel.notes().length; i++) {
                if (data.parent_id == dataModel.notes()[i].id) {
                  var note= dataModel.notes.splice(i, 1); // remove it
                  note[0].kids.push(data);
                  note[0].children= note[0].kids.length;
                  dataModel.notes.splice(i, 0, note[0]); // add it back
                  break;
                }
              }
            } else {
              dataModel.notes.unshift(data);
              // TODO scroll new note into view?
            }
            // flush the input
            dataModel.content(''); dataModel.public(0);
          });

      // $(place).closest('.modal').modal('hide');
    }

    dataModel.selectedPerson= ko.computed({
      read: function () {
        return this.person_id();
      },
      write: function (value) {
        if (typeof value != 'undefined' && value != '') {
          this.person_id(value);
        }
      },
      owner: dataModel
    }).extend({ notify: 'always' });

    ko.applyBindings(dataModel, panel[0]);

    panel.on('hidden.bs.modal', function() {
      $(this).remove();
    });
    panel.on('shown.bs.modal', function() {
      $('input[type="text"]', this).focus();
    });

    panel.appendTo($('body')).modal();
  });
}
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
<div id="scat-page" class="container">
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
<script>
$(function() {
  Scat.api('note-count', { todo: 1})
      .done(function(data) {
        $('#show-notes .badge').text(data.notes);
      });
});
</script>
FOOTER;
}

if (!defined('DB_SERVER') ||
    !defined('DB_USER') ||
    !defined('DB_PASSWORD') ||
    !defined('DB_SCHEMA')) {
  head("Scat Configuration", true);
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

/* Configuration for Paris */
ORM::configure('mysql:host=' . DB_SERVER . ';dbname=' . DB_SCHEMA . ';charset=utf8');
ORM::configure('username', DB_USER);
ORM::configure('password', DB_PASSWORD);
ORM::configure('logging', true);
ORM::configure('error_mode', PDO::ERRMODE_EXCEPTION);
Model::$short_table_names= true;

if ($DEBUG) {
  ORM::configure('logger', function ($log_string, $query_time) {
    error_log('ORM: "' . $log_string . '" in ' . $query_time);
  });
}

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
      echo '<tr class="', ashtml($row[0]), '" data-id="', ashtml($row[0]), '">';
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
      echo '<td', ($class ? ' class="' . ltrim($class, '$'). '"' : ''),
           ($name ? ' id="' . strtolower(strtok($name, '$')). '"' : ''),
           '>',
           expand_field($row[$i], $class, $meta ? $row[0] : null), '</td>';
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

function expand_field($data, $class, $meta = null) {
  switch ($class) {
  case '$txn':
    list($id, $type, $number)= preg_split('/\|/', $data);
    $desc= array('correction' => 'Correction',
                 'drawer' => 'Till Count',
                 'customer' => 'Invoice',
                 'vendor' => 'Purchase Order');
    if ($type == 'customer' || $type == 'vendor' || $type == 'correction') {
      return '<a href="./?id='.ashtml($id).'">'.$desc[$type].' '.ashtml($number).'</a>';
    } else {
      return '<a href="txn.php?id='.ashtml($id).'">'.$desc[$type].' '.ashtml($number).'</a>';
    }
  case '$person':
    list($id, $company, $name, $loyalty)= preg_split('/\|/', $data, 4);
    if (!$id) return '';
    if (!$company && !$name) $name= format_phone($loyalty);
    return '<a href="person.php?id='.ashtml($id).'">'.ashtml($company).($name&&$company?" (":"").ashtml($name).($name&&$company?")":"").'</a>';
  case '$item':
    if ($meta) {
      return '<a href="item.php?id='.ashtml($meta).'">'.ashtml($data).'</a>';
    } else {
      return '<a href="item.php?code='.ashtml($data).'">'.ashtml($data).'</a>';
    }
  case '$dollar':
    if ($data == null)
      return $data;
    return amount($data);
  case '$payment':
    return \Payment::$methods[$data];
  case '$bool':
    if ($data) {
      return '<i data-truth="1" class="fa fa-check-square-o"></i>';
    } else {
      return '<i data-truth="0" class="fa fa-square-o"></i>';
    }
  case '$trool':
    if ($data < 0 || $data === null) {
      return '<i data-truth="1" class="fa fa-check-square-o"></i>';
    } elseif ($data == 0) {
      return '<i data-truth="1" class="fa fa-minus-square-o"></i>';
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
    return sprintf('%s(%s);', $_GET['callback'],
                   json_encode($data, JSON_PRETTY_PRINT));
  }
  return json_encode($data, JSON_PRETTY_PRINT);
}

function die_jsonp($data) {
  if (ob_get_level()) {
    ob_end_clean();
  }
  if (!is_array($data)) {
    $data= array('error' => $data);
  }
  header('Content-type: application/javascript; charset=utf-8');
  if ($_GET['callback']) {
    die(sprintf('%s(%s);', $_GET['callback'],
                json_encode($data, JSON_PRETTY_PRINT)));
  }
  die(json_encode($data, JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK));
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

function format_phone($phone) {
  try {
    $phoneUtil= \libphonenumber\PhoneNumberUtil::getInstance();
    $num= $phoneUtil->parse($phone, 'US');
    return $phoneUtil->format($num,
                              \libphonenumber\PhoneNumberFormat::NATIONAL);
  } catch (Exception $e) {
    // Punt!
    return $phone;
  }
}

require 'lib/cryptor.php';

function include_encrypted($file) {
  $enc= file_get_contents($file);
  $dec= Cryptor::Decrypt($enc, SCAT_ENCRYPTION_KEY);
  eval('?>' . $dec);
}
