<?
header('Content-Type: application/tsv'); 
header('Content-Disposition: attachment; filename="order.txt"'); 
$file = urldecode($_REQUEST['file']); 
echo $file; 
