<?php
require '../scat.php';
require '../lib/txn.php';

$id= (int)$_REQUEST['id'];

if (!$id)
  die("No id specified.");

$txn= txn_load_full($db, $id);
if (!$txn)
  die("Transaction not found.");

if ($txn['txn']['type'] == 'vendor') {
  if ($txn['person']['id'] == 3757) {
    $xls= new \PhpOffice\PhpSpreadsheet\Spreadsheet();

    $xls->setActiveSheetIndex(0);
    $row= 1;

    $xls->getActiveSheet()->setCellValueByColumnAndRow(1, $row, "code")
                          ->setCellValueByColumnAndRow(2, $row, "")
                          ->setCellValueByColumnAndRow(3, $row, "qty");
    $row+=1;

    foreach ($txn['items'] as $item) {
      $xls->getActiveSheet()->setCellValueByColumnAndRow(1, $row, $item['code'])
                            ->setCellValueByColumnAndRow(2, $row, "")
                            ->setCellValueByColumnAndRow(3, $row,
                                                         $item['quantity']);
      $row+=1;
    }

    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' .
           'PO' . $txn['txn']['formatted_number'] . '.xls"');
    header('Cache-Control: max-age=0');

    $objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($xls, 'Xls');
    $objWriter->save('php://output');
  } else {
    header("Content-type: text/tsv");
    header('Content-disposition: attachment; filename="' .
           'PO' . $txn['txn']['formatted_number'] . '.csv"');

    echo "code\tqty\r\n";

    foreach ($txn['items'] as $item) {
      echo $item['code'], "\t",
           $item['quantity'], "\r\n";
    }
  }
  exit;
}

header("Content-type: text/csv");
if ($_REQUEST['dl']) {
  header('Content-disposition: attachment; filename="' .
         'PO' . $txn['txn']['formatted_number'] . '.csv"');
}
echo ",Name,MSRP,Sale,Net,Qty,Ext,Barcode\r\n";

foreach ($txn['items'] as $item) {
  echo $item['code'], ",",
       $item['name'], ",",
       $item['msrp'], ",",
       $item['price'], ",",
       $item['price'], ",",
       $item['quantity'], ",",
       $item['ext_price'], "\r\n";
}
