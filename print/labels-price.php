<?php

include '../scat.php';
include '../lib/item.php';

include '../extern/fpdf/alphapdf.php';
include '../extern/php-barcode.php';

use Smalot\Cups\Builder\Builder;
use Smalot\Cups\Manager\JobManager;
use Smalot\Cups\Manager\PrinterManager;
use Smalot\Cups\Model\Job;
use Smalot\Cups\Transport\Client;
use Smalot\Cups\Transport\ResponseParser;

$qty= (int)$_REQUEST['quantity'];
if (!$qty) $qty= 1;

if ($q= @$_REQUEST['q']) {
  $items= item_find($db, $q, 0);

  if (!$items) die_json("No items found.");
} elseif ($item_list= $_REQUEST['items']) {
  $items= [];
  $item_ids= explode(',', $item_list);
  foreach ($item_ids as $id) {
    $item= item_load($db, $id);
    $items= array_merge($items, array_fill(0, $qty, $item));
  }
} else {
  $id= (int)$_REQUEST['id'];
  $items= array(item_load($db, $id));

  if (!$items[0]) die_json("No such item.");

  if ($qty > 1) {
    $items= array_pad($items, $qty, $items[0]);
  }
}

$trim= trim($_REQUEST['trim']);
$short= (int)$_REQUEST['short'];
$noprice= (int)$_REQUEST['noprice'];

$left_margin= 0.2;

$label_width= 2.0;
$label_height= 0.75;

$basefontsize= 9;
$vmargin= 0.1;

$dummy = new AlphaPDF('P', 'in', array($label_width, $label_height));
$dummy->AddPage();

$pdf= new AlphaPDF('P', 'in', array($label_width, $label_height));

foreach ($items as $item) {

  $pdf->AddPage();

  $pdf->Rotate(270);

  $pdf->SetFont('Helvetica', '');
  $pdf->SetFontSize($size= $basefontsize);
  $pdf->SetTextColor(0);

  // write the name
  $name= utf8_decode($short ? $item['short_name'] : $item['name']);

  if ($trim)
    $name= trim(preg_replace("!$trim!i", '', $name));

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

  if ($noprice) goto noprice;

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
  if ($item['sale_price'] != $item['retail_price']) {
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

noprice:

  // write the barcode
  $code= $item['fake_barcode'];
  if ($item['barcode']) {
    foreach ($item['barcode'] as $barcode => $quantity) {
      if ($quantity == 1) {
        $code= $barcode;
        break;
      }
    }
  }

  $types= array(8 => 'ean8', 12 => 'upc', 13 => 'ean13');
  $type= $types[strlen($code)];
  if (!$type) $type= 'code39';

  $info= Barcode::fpdf($dummy, '000000',
                       0, 0, 0, $type, $code,
                       (1/72), $basefontsize/2/72);

  Barcode::fpdf($pdf, '000000',
                $label_width - $left_margin - $info['width'] / 2 - 2/72,
                $label_height - $vmargin - 7/72,
                0 /* angle */, $type,
                $code, (1/72), $basefontsize/2/72);

  // write the code
  $pdf->SetFontSize($size= $basefontsize/2);
  $width= $pdf->GetStringWidth($item['code']);

  $pdf->Text($label_width - $width - $left_margin - 2/72,
             $label_height - $vmargin,
             $item['code']);

  // write the brand
  $max_width= $label_width - $width - $left_margin - 8/72;
  $brand= $item['brand']." ";
  do {
    $brand= substr($brand, 0, -1);
    $width= $pdf->GetStringWidth($brand);
  } while ($width > $max_width && $brand != "");

  $pdf->Text($left_margin + 2/72,
             $label_height - $vmargin,
             $brand);
}

$tmpfname= tempnam("/tmp", "lab");

if (@$_REQUEST['DEBUG']) {
  $pdf->Output("test.pdf", 'I');
  exit;
}

$pdf->Output($tmpfname, 'F');

if (!defined('CUPS_HOST')) {
  $printer= LABEL_PRINTER;
  shell_exec("lpr -P$printer $tmpfname");
} else {

  $client= new Client(CUPS_USER, CUPS_PASS,
		      [ 'remote_socket' => 'tcp://' . CUPS_HOST ]);
  $builder= new Builder(null, true);
  $responseParser= new ResponseParser();

  $printerManager= new PrinterManager($builder, $client, $responseParser);
  $printer= $printerManager->findByUri('ipp://' . CUPS_HOST .
				       '/printers/' . LABEL_PRINTER);

  $jobManager= new JobManager($builder, $client, $responseParser);

  $job= new Job();
  $job->setName('job create file');
  $job->setCopies(1);
  $job->setPageRanges('1-1000');
  $job->addFile($tmpfname);
  $result= $jobManager->send($printer, $job);
}

echo jsonp(array("result" => "Printed."));
