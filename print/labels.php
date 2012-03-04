<?php

include '../scat.php';
include '../lib/item.php';

include '../lib/fpdf/alphapdf.php';
include '../lib/fpdf/ean13.php';

$a= array();

$in= $_REQUEST['in'];

$c= preg_split('/\r?\n/', $in);
$a= array();
foreach ($c as $code) {
  if (trim($code))
    $a[]= "code LIKE '" . addslashes($code) . "%'";
}
$having= join(' OR ', $a);
$ex= $_REQUEST['ex'];
if (!empty($ex)) {
  $having.= ") AND (";
  $c= preg_split('/\r?\n/', $ex);
  $a= array();
  foreach ($c as $code) {
    if (trim($code))
      $a[]= "code NOT LIKE '" . addslashes($code) . "%'";
  }
  $having.= join(' AND ', $a);
}

$items= item_find($db, $in, 0);

$default_height= $height= 1;
$cols= 3;

$pdf= new AlphaPDF('P', 'in', 'Letter');

$pdf->SetCompression(true);

$pdf->SetMargins(0, 0);
$pdf->SetAutoPageBreak(false);

$x= $y= 0;

$light= 96;

$basefontsize= 6;

$label_width= 2.625;

foreach ($items as $item) {
  $bx= 0.25 + ($x * ($label_width + 0.125));
  $by= 0.5 + ($y * $height);
  $vmargin= 0.125;

  if ($x == 0 && $y % 10 == 0) {
    $pdf->AddPage(); 

    if ($_REQUEST['debug']) {
      $pdf->SetDrawColor($light);
      $pdf->SetLineWidth(1/72/2);
      for ($ly= 0; $ly < 11; $ly++) {
        $pdf->Line(0, 0.5 + $ly, 8.5, 0.5 + $ly);
      }
      $pdf->Line(0.1875, 0,
                 0.1875, 11);
      $pdf->Line(0.1875 + 2.625, 0,
                 0.1875 + 2.625, 11);
      $pdf->Line(0.1875 + 2.625 + 0.125, 0,
                 0.1875 + 2.625 + 0.125, 11);
      $pdf->Line(0.1875 + 2.625 + 0.125 + 2.625, 0,
                 0.1875 + 2.625 + 0.125 + 2.625, 11);
      $pdf->Line(0.1875 + 2.625 + 0.125 + 2.625 + 0.125, 0,
                 0.1875 + 2.625 + 0.125 + 2.625 + 0.125, 11);
      $pdf->Line(0.1875 + 2.625 + 0.125 + 2.625 + 0.125 + 2.625, 0,
                 0.1875 + 2.625 + 0.125 + 2.625 + 0.125 + 2.625, 11);
    }
  }

  # write the code
  $pdf->SetFont('Helvetica', '');
  $pdf->SetFontSize($basefontsize);
  $pdf->SetTextColor($light);
  $width= $pdf->GetStringWidth($item['code']);

  $pdf->Text($bx + $label_width - $width - $vmargin,
             $by + $height - $vmargin,
             $item['code']);

  # write the barcode
  if ($item['barcode']) {
    foreach ($item['barcode'] as $code => $quantity) {
      if ($quantity == 1) {
        Barcode($pdf,
                $bx + $vmargin,
                $by + $height - $vmargin - $basefontsize/72,
                $code, $basefontsize/72, 1/72, strlen($code));
        break;
      }
    }
  }

  # write the name
  $pdf->SetFontSize($size= $basefontsize * 2);
  $pdf->SetTextColor(0);
  $name= utf8_decode($item['name']);

  $width= $pdf->GetStringWidth($name);
  while ($width > ($label_width - $vmargin * 2) && $size) {
    $pdf->SetFontSize(--$size);
    $width= $pdf->GetStringWidth($name);
  }
  $pdf->Text($bx + $vmargin,
             $by + $vmargin + ($size/72),
             $name);

  # write the price
  $pdf->SetFont('Helvetica', 'B');
  $pdf->SetFontSize($basefontsize * 4);
  $price= '$' . $item['sale_price'];
  $pwidth= $pdf->GetStringWidth($price);
  $pdf->Text($bx + ($label_width - $vmargin) - $pwidth,
             $by + (36/72) + $vmargin,
             $price);

  # discount? write the info
  $pdf->SetFont('Helvetica', '');
  if ($item['discount_label']) {
    # Write the MSRP
    $pdf->SetFontSize($basefontsize * 3);
    $pdf->SetTextColor($light);
    $msrp= '$' . $item['retail_price'];
    $width= $pdf->GetStringWidth($msrp." ");
    $pdf->Text($bx + ($label_width - $vmargin) - $pwidth - $width,
               $by + (36/72) + $vmargin,
               $msrp);
    $pdf->SetDrawColor(0);
    $pdf->SetAlpha(0.4);
    $pdf->SetLineWidth(1/16);
    $pdf->Line($bx + ($label_width - $vmargin) - $pwidth - $width,
               $by + (36/72) + $vmargin - ($basefontsize * 1)/72,
               $bx + ($label_width - $vmargin) - $pwidth - (4/72),
               $by + (36/72) + $vmargin - ($basefontsize * 1)/72);
    $pdf->SetAlpha(1);

    // write the discount
    if (0) {
    $pdf->SetFont('Helvetica', 'B');
    $pdf->SetTextColor(0xcd, 0x6a, 0x21);
    $size= $basefontsize * 2;
    $pdf->SetFontSize($size);
    $pdf->Text($bx + $vmargin,
               $by + (34 / 72) + $vmargin,
               $item['discount_label']);
    $pdf->SetTextColor(0);
    }
  }

  if (++$x >= $cols) {
    $x= 0;
    ++$y;
  }
}

$pdf->Output('labels' . rand() . '.pdf', 'I');
