<?php

include '../scat.php';
include '../lib/item.php';

include '../lib/fpdf/alphapdf.php';
include '../lib/fpdf/ean13.php';

$id= (int)$_REQUEST['id'];
$item= item_load($db, $id);

if (!$item) die_json("No such item.");

$left_margin= 0.0775;

$label_width= 2.0 - $left_margin;
$label_height= 0.75;

$basefontsize= 5;
$vmargin= 0.075;

$pdf= new AlphaPDF('P', 'in', array($label_width + $left_margin, $label_height));

$pdf->SetCompression(true);

$pdf->AddPage();
$pdf->Rotate(270);

$pdf->SetMargins(0, 0);
$pdf->SetAutoPageBreak(false);

$pdf->SetFont('Helvetica', '');
$pdf->SetFontSize($size= $basefontsize * 2);
$pdf->SetTextColor(0);

# write the name
$pdf->SetTextColor(0);
$name= utf8_decode($item['name']);

$width= $pdf->GetStringWidth($name);
while ($width > ($label_width - $vmargin * 2) && $size) {
  $pdf->SetFontSize(--$size);
  $width= $pdf->GetStringWidth($name);
}
$pdf->Text($left_margin + ($label_width - $width) / 2,
           $vmargin + ($size/72),
           $name);

$pdf->SetFontSize($size= $basefontsize * 2);

# write the prices
if ($item['retail_price'] != $item['sale_price']) {
  $price= 'List Price $' . $item['retail_price'];
  $pwidth= $pdf->GetStringWidth($price);
  $pdf->Text($left_margin + ($label_width - $pwidth) / 2,
             ($size / 72) * 2 + 2/72 + $vmargin,
             $price);
}

$price= 'Our Price $' . ($item['sale_price'] ? $item['sale_price'] : $item['retail_price']);
$pwidth= $pdf->GetStringWidth($price);
$pdf->Text($left_margin + ($label_width - $pwidth) / 2,
           ($size / 72) * 3 + 4/72 + $vmargin,
           $price);

# write the barcode
if ($item['barcode']) {
  foreach ($item['barcode'] as $code => $quantity) {
    if ($quantity == 1) {
      Barcode($pdf,
              $left_margin + 0.3,
              $label_height - $vmargin - $basefontsize/72,
              $code, $basefontsize/72, /*1/72*/ 0.01, strlen($code));
      break;
    }
  }
}

# write the code
$pdf->SetFontSize($basefontsize);
$width= $pdf->GetStringWidth($item['code']);

$pdf->Text($label_width - $width,
           $label_height - $vmargin,
           $item['code']);

$pdf->Rotate(0);

$tmpfname= tempnam("/tmp", "lab");

if ($_REQUEST['DEBUG']) {
  $pdf->Output("test.pdf", 'I');
  exit;
}

$pdf->Output($tmpfname, 'F');

$printer= LABEL_PRINTER;
shell_exec("lpr -P$printer $tmpfname");

echo jsonp(array("result" => "Printed."));
