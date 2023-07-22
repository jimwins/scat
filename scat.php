<?
require dirname(__FILE__).'/lib/db.php';

if (!isset($GLOBALS['app'])) {
  $config= require @$_ENV['SCAT_CONFIG'] ?: dirname(__FILE__).'/config.php';

  require dirname(__FILE__).'/vendor/autoload.php';

  \Titi\Model::$auto_prefix_models= '\\Scat\\Model\\';
  \Titi\Model::$short_table_names= true;

  \Titi\ORM::configure($config['data']['dsn']);
  foreach ($config['data']['options'] as $option => $value) {
    \Titi\ORM::configure($option, $value);
  }
  \Titi\ORM::configure('error_mode', PDO::ERRMODE_EXCEPTION);
}

function head($title, $x= null) {
  $GLOBALS['title']= $title;
}
function foot() {
}

$db= new ScatDB();
if (!$db) die("mysqli_init failed");

$db->options(MYSQLI_OPT_LOCAL_INFILE, true);

preg_match('/mysql:host=(.+?);dbname=(.+?);charset=utf8/',
           $GLOBALS['config']['data']['dsn'], $m);

if (!$db->real_connect($m[1], $GLOBALS['config']['data']['options']['username'],
                       $GLOBALS['config']['data']['options']['password'],
                       $m[2]))
  die('connect failed: ' . mysqli_connect_error());

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
  echo '<table class="table table-striped table-condensed table-sort">';
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
      $row[0]= '<a href="/catalog/item/'.$row[0].'">'.$row[0].'</a>';
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
    return '<a href="./?id='.ashtml($id).'">'.$desc[$type].' '.ashtml($number).'</a>';
  case '$person':
    list($id, $company, $name, $loyalty)= preg_split('/\|/', $data, 4);
    if (!$id) return '';
    if (!$company && !$name) $name= format_phone($loyalty);
    return '<a href="/person/'.ashtml($id).'">'.ashtml($company).($name&&$company?" (":"").ashtml($name).($name&&$company?")":"").'</a>';
  case '$item':
    return '<a href="/catalog/item/'.ashtml($data).'">'.ashtml($data).'</a>';
  case '$dollar':
    if ($data == null)
      return $data;
    return amount($data);
  case '$payment':
    return \Scat\Model\Payment::$methods[$data];
  case '$bool':
    if ($data) {
      return '<i data-truth="1" class="fa fa-check-square"></i>';
    } else {
      return '<i data-truth="0" class="fa fa-square"></i>';
    }
  case '$trool':
    if ($data < 0 || $data === null) {
      return '<i data-truth="1" class="fa fa-check-square"></i>';
    } elseif ($data == 0) {
      return '<i data-truth="1" class="fa fa-minus-square"></i>';
    } else {
      return '<i data-truth="0" class="fa fa-square"></i>';
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
  if (preg_match('/\W/', @$_GET['callback'])) {
    // if $_GET['callback'] contains a non-word character,
    // this could be an XSS attack.
    header('HTTP/1.1 400 Bad Request');
    exit();
  }
  header('Content-type: application/json; charset=utf-8');
  if (@$_GET['callback']) {
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

require 'extern/cryptor.php';

function include_encrypted($file) {
  $enc= file_get_contents($file);
  $dec= Cryptor::Decrypt($enc, SCAT_ENCRYPTION_KEY);
  eval('?>' . $dec);
}
