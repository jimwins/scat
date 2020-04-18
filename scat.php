<?
require dirname(__FILE__).'/lib/db.php';

if (!$GLOBALS['app']) {
  require $_ENV['SCAT_CONFIG'] ?: dirname(__FILE__).'/config.php';

  require dirname(__FILE__).'/vendor/autoload.php';

  Model::$auto_prefix_models= '\\Scat\\Model\\';
  Model::$short_table_names= true;
}

function head($title, $x= null) {
  $GLOBALS['title']= $title;
}
function foot() {
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
    if ($meta) {
      return '<a href="/catalog/item/'.ashtml($meta).'">'.ashtml($data).'</a>';
    } else {
      return '<a href="/catalog/item/'.ashtml($data).'">'.ashtml($data).'</a>';
    }
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
