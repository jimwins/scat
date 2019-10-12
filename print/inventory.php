<?
require '../scat.php';
require '../lib/item.php';

$q= $_REQUEST['q'];
if (!$q) die_json("Nothing to report.");

$items= item_find($db, $q, 0);
if (!$items) die_json("No items found.");

$product= $items[0]['product_id'];
$use_short_name= true;
foreach ($items as $item) {
  if ($item['product_id'] != $product) {
    $use_short_name= false;
    break;
  }
}

$loader= new \Twig\Loader\FilesystemLoader('../ui/');
$twig= new \Twig\Environment($loader, [ 'cache' => false ]);

$template= $twig->load('print/inventory.html');
$html= $template->render([
  'items' => $items,
  'use_short_name' => $use_short_name
]);

if (defined('PRINT_DIRECT')) {
  define('_MPDF_TTFONTDATAPATH', '/tmp/ttfontdata');
  @mkdir(_MPDF_TTFONTDATAPATH);

  $mpdf= new \Mpdf\Mpdf([ 'mode' => 'utf-8', 'format' => 'letter',
                          'tempDir' => '/tmp',
                          'margin_left' => 15, 'margin_right' => 15,
                          'margin_top' => 9, 'margin_bottom' => 10,
                          'default_font_size' => 28  ]);
  $mpdf->writeHTML($html);

  $tmpfname= tempnam("/tmp", "rec");

  if ($_REQUEST['DEBUG']) {
    $mpdf->Output();
    exit;
  }

  $mpdf->Output($tmpfname, 'f');

  if (!defined('CUPS_HOST')) {
    $printer= REPORT_PRINTER;
    $option= "";
    shell_exec("lpr -P$printer $option $tmpfname");
  } else {
    $client= new \Smalot\Cups\Transport\Client(CUPS_USER, CUPS_PASS,
                                               [ 'remote_socket' => 'tcp://' .
                                                                    CUPS_HOST
                                                                    ]);
    $builder= new \Smalot\Cups\Builder\Builder(null, true);
    $responseParser= new \Smalot\Cups\Transport\ResponseParser();

    $printerManager= new \Smalot\Cups\Manager\PrinterManager($builder,
                                                             $client,
                                                             $responseParser);
    $printer= $printerManager->findByUri('ipp://' . CUPS_HOST .
                                         '/printers/' . REPORT_PRINTER);

    $jobManager= new \Smalot\Cups\Manager\JobManager($builder,
                                                     $client,
                                                     $responseParser);

    $job= new \Smalot\Cups\Model\Job();
    $job->setName('job create file');
    $job->setCopies(1);
    $job->setPageRanges('1-1000');
    $job->addFile($tmpfname);
    $result= $jobManager->send($printer, $job);
  }

  echo jsonp(array("result" => "Printed."));
} else {
  echo $html;
}

