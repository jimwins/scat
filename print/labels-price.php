<?php

include '../scat.php';
include '../lib/item.php';

include '../lib/fpdf/alphapdf.php';
include '../lib/fpdf/ean13.php';

if ($q= $_REQUEST['q']) {
  $items= item_find($db, $q, 0);

  if (!$items) die_json("No items found.");
} else {
  $id= (int)$_REQUEST['id'];
  $items= array(item_load($db, $id));

  if (!$items[0]) die_json("No such item.");
}

$left_margin= 0.2;

$label_width= 2.0;
$label_height= 0.75;

$basefontsize= 9;
$vmargin= 0.1;

$pdf= new AlphaPDF('P', 'in', array($label_width, $label_height));

foreach ($items as $item) {

  $pdf->AddPage();

  $pdf->Rotate(270);

  $pdf->SetFont('Helvetica', '');
  $pdf->SetFontSize($size= $basefontsize);
  $pdf->SetTextColor(0);

  // write the name
  $name= utf8_decode($item['name']);

  $width= $pdf->GetStringWidth($name);
  while ($width > ($label_width - $left_margin * 2) && $size) {
    $pdf->SetFontSize(--$size);
    $width= $pdf->GetStringWidth($name);
  }
  $pdf->Text(($label_width - $width) / 2,
             $vmargin + ($size/72),
             $name);

  // write the prices
  $pdf->SetFontSize($size= $basefontsize * 2);

  // figure out the font size
  $price= '$' . max($item['sale_price'], $item['retail_price']);
  $pwidth= $pdf->GetStringWidth($price);
  while ($pwidth > (($label_width - $left_margin * 2 - $vmargin * 2) / 2) && $size) {
    $pdf->SetFontSize(--$size);
    $pwidth= $pdf->GetStringWidth($price);
  }

  // sale price
  $price= '$' . ($item['sale_price'] ? $item['sale_price'] : $item['retail_price']);
  $pwidth= $pdf->GetStringWidth($price);
  $pdf->Text($label_width - $left_margin - $pwidth,
             ($label_height / 2) + ($vmargin),
             $price);

  // retail price, if different
  if ($item['retail_price'] != $item['sale_price']) {
    $price= '$' . $item['retail_price'];
    $pwidth= $pdf->GetStringWidth($price);
    $pdf->Text($left_margin + $vmargin,
               ($label_height / 2) + ($vmargin),
               $price);
    $pdf->SetDrawColor(0);
    $pdf->SetAlpha(0.4);
    $line_width= $size / 3;
    $pdf->SetLineWidth($line_width/72);
    $pdf->Line($left_margin,
               ($label_height / 2) + $vmargin - ($size/72/2 - $line_width/72/2),
               $left_margin + $pwidth + $vmargin * 2,
               ($label_height / 2) + $vmargin - ($size/72/2 - $line_width/72/2)
              );
    $pdf->SetAlpha(1);

  }

  // write the barcode
  if ($item['barcode']) {
    foreach ($item['barcode'] as $code => $quantity) {
      if ($quantity == 1) {
        Barcode($pdf,
                $label_width - $left_margin - (1/72 * 97),
                $label_height - $vmargin - $basefontsize/72,
                $code, $basefontsize/2/72, 1/72, strlen($code));
        break;
      }
    }
  }

  // write the code
  $pdf->SetFontSize($size= $basefontsize/2);
  $width= $pdf->GetStringWidth($item['code']);

  $pdf->Text($label_width - $width - $left_margin - 2/72,
             $label_height - $vmargin,
             $item['code']);

}

$tmpfname= tempnam("/tmp", "lab");

if ($_REQUEST['DEBUG']) {
  $pdf->Output("test.pdf", 'I');
  exit;
}

$pdf->Output($tmpfname, 'F');

$printer= LABEL_PRINTER;
shell_exec("lpr -P$printer $tmpfname");

echo jsonp(array("result" => "Printed."));
