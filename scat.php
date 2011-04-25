<?

/** Basic functions */

function head($title = "Scat") {
header("content-type: text/html;charset=utf-8");?>
<!DOCTYPE html>
<html>
<head>
 <title><?=ashtml($title)?></title>
 <style type="text/css">
  body { background: #ba9d6c; padding-top: 28px; }

  header {
   width: 100%;
   position: fixed;
   top: 0;
   left: 0;
   padding: 4px 16px;
   background: #d2c09f;
   -webkit-box-shadow: 0px 4px 10px rgba(0,0,0,0.2);
  }

  /* prettier tables */
  table { border-collapse:collapse; }
  thead tr { background: rgba(0,0,0,0.2); color: rgba(0,0,0,0.5); }
  tbody tr:nth-child(odd) { background-color:rgba(255,255,255,0.3); }
  tbody tr:nth-child(even) { background-color:rgba(255,255,255,0.2); }
  tbody tr:hover { background:rgba(255,255,255,0.4); }
  th, td { padding:4px 6px; border:2px solid rgba(255,255,255,0.05); }
  td.num {
    vertical-align:middle;
    font:small-caps bold x-small sans-serif;
    text-align: center;
  }
  td.dollar {
    text-align: right;
  }
  td.dollar:before {
    content: '$';
  }
  td.right {
    text-align: right;
  }

  /* form styling */
  form.rock {
    border-radius: 10px;
    -webkit-box-shadow: 2px 2px 10px rgba(0,0,0,0.2);
    background-color: rgba(255,255,255,0.2);
    padding: 10px 10px;
  }
 
  input[type="text"] {
    border-radius: 10px;
    background: rgba(255,255,255,0.7);
    border: 1px solid #cacaca;
    color: #444;
    padding: 8px 10px;
    outline: none;
  }
  input[type="text"]:focus {
    background: rgba(255,255,255,0.9);
  }
 
  button, input[type="submit"] {
    border-radius: 10px;
    background-color: #dedede;
    background: -webkit-gradient(linear, left top, left bottom,
                                 color-stop(0.0, rgba(255,255,255,0.8)),
                                 color-stop(1.0, rgba(255,255,255,0.3)));
    border: 1px solid rgba(255,255,255,0.3);
    color: #484848;
    font-weight: bold;
    padding: 8px 10px;
  }
  button:hover, input[type="submit"]:hover {
    background-color: #dedede;
    background: -webkit-gradient(linear, left top, left bottom,
                                 color-stop(0.0, rgba(240,240,240,0.8)),
                                 color-stop(1.0, rgba(240,240,240,0.3)));
    border: 1px solid rgba(240,240,240,0.3);
  }
 </style>
 <style type="text/css" media="print">
  .debug { display: none; }
 </style>
 <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.4/jquery.min.js"></script>
 <script src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.2/jquery-ui.min.js"></script>
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
<a href="./" title="Home"><img src="./icons/house.png" width="16" height="16" alt="Home"></a>
&nbsp;
<a href="#" onclick="return false" title="Cart"><img src="./icons/cart.png" width="16" height="16" alt="Cart"></a>
&nbsp;
<a href="./items.php" title="Items"><img src="./icons/tag_blue.png" width="16" height="16" alt="Items"></a>
&nbsp;
<a href="./txns.php" title="Transactions"><img src="./icons/table.png" width="16" height="16" alt="Transactions"></a>
&nbsp;
<a href="#" onclick="return false" title="Reports"><img src="./icons/report.png" width="16" height="16" alt="Reports"></a>
</header>
<?
}

$db= mysqli_init();
if (!$db) die("mysqli_init failed");

if (!$db->real_connect("localhost","root","","scat"))
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

function dump_query($q) {
  static $num;
  $num+= 1;
?>
<button onclick="$('#query_<?=$num?>').toggle('drop')">Show Query</button>
<pre id="query_<?=$num?>" class="debug" style="display: none"><?=ashtml($q)?></pre>
<?
}
