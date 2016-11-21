<?
require '../scat.php';

$fn= "order.xls";
if (preg_match('/^([-A-Za-z0-9_])+\\.([A-Za-z0-9]+)$/', $_REQUEST['fn'])) {
  $fn= $_REQUEST['fn'];
}

require '../extern/PHPExcel-1.8.1/Classes/PHPExcel.php';

$xls= new PHPExcel();

$xls->setActiveSheetIndex(0);
$row= 1;

$lines= explode("\r\n", urldecode($_REQUEST['file']));

foreach ($lines as $line) {
  list($code, $qty)= explode("\t", $line);
  $xls->getActiveSheet()->setCellValueByColumnAndRow(0, $row, $code)
                        ->setCellValueByColumnAndRow(1, $row, "")
                        ->setCellValueByColumnAndRow(2, $row, $qty);
  $row+=1;
}

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $fn . '"'); 
header('Cache-Control: max-age=0');

$objWriter = PHPExcel_IOFactory::createWriter($xls, 'Excel5');
$objWriter->save('php://output');
