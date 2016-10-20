<?
$fn= "order.csv";
if (preg_match('/^([-A-Za-z0-9_])+\\.([A-Za-z0-9]+)$/', $_REQUEST['fn'])) {
  $fn= $_REQUEST['fn'];
}
header('Content-Type: application/tsv'); 
header('Content-Disposition: attachment; filename="' . $fn . '"'); 
$file = urldecode($_REQUEST['file']); 
echo $file; 
