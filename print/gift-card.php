<?php

include '../scat.php';

include '../lib/fpdf/fpdf.php';
include '../lib/fpdi/fpdi.php';
include '../lib/php-barcode.php';

$card= $_REQUEST['card'];
$balance= $_REQUEST['balance'];
$issued= $_REQUEST['issued'];

// initiate FPDI
$pdf = new FPDI('P', 'in', array(8.5, 11));
// add a page
$pdf->AddPage();
// set the source file
$pdf->setSourceFile("blank-gift-card.pdf");
// import page 1
$tplIdx = $pdf->importPage(1);
// use the imported page
$pdf->useTemplate($tplIdx);

// now write some text above the imported page
$pdf->SetFont('Helvetica');
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFontSize(($basefontsize= 18));

if ($balance) {
  $width= $pdf->GetStringWidth('$' . $balance);
  $pdf->SetXY(4.25 - ($width / 2), 2.5);
  $pdf->Write(0, '$' . $balance);
}

$width= $pdf->GetStringWidth($issued);
$pdf->SetXY(4.25 - ($width / 2), 3.25);
$pdf->Write(0, $issued);

$code= "RAW-$card";
$type= "code39";

Barcode::fpdf($pdf, '000000',
              4.25, 5,
              0 /* angle */, $type,
              $code, (1/72), $basefontsize/2/72);

$pdf->SetFontSize(10);
$width= $pdf->GetStringWidth($card);
$pdf->SetXY(4.25 - ($width / 2), 5.2);
$pdf->Write(0, $card);

$pdf->Output();

