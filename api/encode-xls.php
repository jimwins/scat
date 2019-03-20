<?
require '../scat.php';

$fn= "order.xls";
if (preg_match('/^([-A-Za-z0-9_])+\\.([A-Za-z0-9]+)$/', $_REQUEST['fn'])) {
  $fn= $_REQUEST['fn'];
}

$xls= new \PhpOffice\PhpSpreadsheet\Spreadsheet();

$xls->setActiveSheetIndex(0);
$row= 1;

$lines= explode("\r\n", urldecode($_REQUEST['file']));

foreach ($lines as $line) {
  list($code, $qty)= explode("\t", $line);
  $xls->getActiveSheet()->setCellValueByColumnAndRow(1, $row, $code)
                        ->setCellValueByColumnAndRow(2, $row, "")
                        ->setCellValueByColumnAndRow(3, $row, $qty);
  $row+=1;
}

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $fn . '"'); 
header('Cache-Control: max-age=0');

$objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($xls, 'Xls');
$objWriter->save('php://output');
