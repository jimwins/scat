<?php

include '../scat.php';
include '../lib/item.php';

include '../lib/fpdf/alphapdf.php';
include '../lib/php-barcode.php';

if ($q= $_REQUEST['q']) {
  $items= item_find($db, $q, 0);

  if (!$items) die_json("No items found.");
} else {
  $id= (int)$_REQUEST['id'];
  $items= array(item_load($db, $id));

  if (!$items[0]) die_json("No such item.");
}

$trim= $_REQUEST['trim'];
$noprice= (int)$_REQUEST['noprice'];

$left_margin= 0.2;

$label_width= 2.0 / 2;
$label_height= 0.75;

$basefontsize= 9;
$vmargin= 0.1;

$dummy = new AlphaPDF('P', 'in', array($label_width * 2, $label_height));

$pdf= new AlphaPDF('P', 'in', array($label_width * 2, $label_height));

$c= 1;

foreach ($items as $item) {

  if (($c++ % 2)) {
    $pdf->AddPage(); 
    $pdf->Rotate(270);
  }

  $pdf->SetFont('Helvetica', '');
  $pdf->SetFontSize($size= $basefontsize);
  $pdf->SetTextColor(0);

  // write the name
  $name= utf8_decode($item['name']);

  if ($trim)
    $name= preg_replace("/$trim/i", '', $name);

  $width= $pdf->GetStringWidth($name);
  while ($width > ($label_width - $left_margin * 2) && $size) {
    $pdf->SetFontSize(--$size);
    $width= $pdf->GetStringWidth($name);
  }
  $pdf->Text(($label_width * ($c % 2)) + ($label_width - $width) / 2,
             $vmargin + ($size/72),
             $name);

  // write the prices
  $pdf->SetFontSize($size= $basefontsize * 2);

  if ($noprice) goto noprice;

  // figure out the font size
  $price= '$' . max($item['sale_price'], $item['retail_price']);
  $pwidth= $pdf->GetStringWidth($price);
  while ($pwidth > (($label_width - $left_margin * 2 - $vmargin * 2)) && $size) {
    $pdf->SetFontSize(--$size);
    $pwidth= $pdf->GetStringWidth($price);
  }

  // sale price
  $price= '$' . ($item['sale_price'] ? $item['sale_price'] : $item['retail_price']);
  $pwidth= $pdf->GetStringWidth($price);
  $pdf->Text(($label_width * ($c % 2)) + ($label_width - $pwidth) / 2,
             ($label_height / 2) + ($vmargin),
             $price);

  // retail price, if different
  if ($item['sale_price']) {
    $price= '$' . $item['retail_price'];
    $pwidth= $pdf->GetStringWidth($price);
    $pdf->Text(($label_width * ($c % 2)) + ($label_width - $pwidth) / 2,
               ($label_height / 2) - $vmargin / 3,
               $price);
    $pdf->SetDrawColor(0);
    $pdf->SetAlpha(0.4);
    $line_width= $size / 3;
    $pdf->SetLineWidth($line_width/72);
    $pdf->Line(($label_width * ($c % 2)) + $left_margin,
               ($label_height / 2) - ($size/72/2 - $line_width/72/2) - $vmargin / 3,
               ($label_width * ($c % 2)) + $left_margin + $pwidth + $vmargin * 2,
               ($label_height / 2) - ($size/72/2 - $line_width/72/2) - $vmargin / 3
              );
    $pdf->SetAlpha(1);

  }

noprice:

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
