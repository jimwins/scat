<?
require '../scat.php';
require '../lib/txn.php';

$id= (int)$_REQUEST['id'];
if (!$id) die("No transaction specified.");

date_default_timezone_set('America/Los_Angeles');

$txn= txn_load_full($db, $id);

$fn= (($txn['txn']['type'] == 'vendor') ? 'PO' : 'I') .
     $txn['txn']['formatted_number'];

$loader= new \Twig\Loader\FilesystemLoader('../ui/');
$twig= new \Twig\Environment($loader, [ 'cache' => false ]);

$template= $twig->load('print/invoice.html');
$html= $template->render($txn);

if (defined('PRINT_DIRECT')) {
  define('_MPDF_TTFONTDATAPATH', '/tmp/ttfontdata');
  @mkdir(_MPDF_TTFONTDATAPATH);

  $mpdf= new \Mpdf\Mpdf([ 'mode' => 'utf-8', 'format' => 'letter',
                          'tempDir' => '/tmp',
                          'default_font_size' => 11  ]);
  $mpdf->setAutoTopMargin= 'stretch';
  $mpdf->setAutoBottomMargin= 'stretch';
  $mpdf->writeHTML($html);

  $tmpfname= tempnam("/tmp", "rec");

  // XXX hack: don't print vendor invoices directly
  if ($DEBUG || $txn['txn']['type'] == 'vendor') {
    $mpdf->Output($fn . '.pdf', \Mpdf\Output\Destination::INLINE);
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
