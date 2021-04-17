<?php
namespace Scat\Service;

use \Slim\Http\Response as Response;

class Printer
{
  private $view;
  private $config;
  private $builder, $client, $responseParser;

  public function __construct(\Slim\Views\Twig $view, Config $config) {
    $this->view= $view;
    $this->config= $config;
  }

  public function getPrinterManager() {
    $cups_host= $this->config->get('cups.host');
    $cups_user= $this->config->get('cups.user');
    $cups_pass= $this->config->get('cups.pass');

    if (!$cups_host) return null;

    $this->client= new \Smalot\Cups\Transport\Client($cups_user, $cups_pass, [
      'remote_socket' => 'tcp://' .  $cups_host
    ]);

    $this->builder= new \Smalot\Cups\Builder\Builder(null, true);
    $this->responseParser= new \Smalot\Cups\Transport\ResponseParser();

    return new \Smalot\Cups\Manager\PrinterManager(
      $this->builder,
      $this->client,
      $this->responseParser
    );
  }

  public function generateFromTemplate($template, $data) {
    $html= $this->view->fetch($template, $data);

    define('_MPDF_TTFONTDATAPATH', '/tmp/ttfontdata');
    @mkdir(_MPDF_TTFONTDATAPATH);

    $mpdf= new \Mpdf\Mpdf([ 'mode' => 'utf-8', 'format' => 'letter',
                            'tempDir' => '/tmp',
                            // TODO receipt had default_font_size of 28,
                            // can we fix this in template?
                            'default_font_size' => 11  ]);
    $mpdf->setAutoTopMargin= 'stretch';
    $mpdf->setAutoBottomMargin= 'stretch';
    $mpdf->writeHTML($html);

    return $mpdf->Output("not-used", \Mpdf\Output\Destination::STRING_RETURN);
  }

  public function printPDF(Response $response, $pageType, $pdf) {
    $printerManager= $this->getPrinterManager();

    list ($pageType, $modifier)= explode(':', $pageType);
    $printer_name= $this->config->get('printer.' . $pageType);

    if ($GLOBALS['DEBUG'] || !$printerManager || !$printer_name) {
      $response->getBody()->write($pdf);
      return $response->withHeader('Content-type', 'application/pdf');
    }

    $printer= $printerManager->findByUri(
      'ipp://' . $cups_host .  '/printers/' . $printer_name
    );

    $jobManager= new \Smalot\Cups\Manager\JobManager(
      $this->builder,
      $this->client,
      $this->responseParser
    );

    $job= new \Smalot\Cups\Model\Job();
    $job->setName('job create file');
    $job->setCopies(1);
    $job->setPageRanges('1-1000');
    $job->addText($pdf, '', 'application/pdf');
    if ($modifier == 'open') {
      $job->addAttribute('CashDrawerSetting', '1OpenDrawer1');
    }
    $result= $jobManager->send($printer, $job);

    return $response->withJson([ 'result' => 'Printed.' ]);
  }

  public function printPNG(Response $response, $pageType, $png) {
    $printerManager= $this->getPrinterManager();

    list ($pageType, $modifier)= explode(':', $pageType);
    $printer_name= $this->config->get('printer.' . $pageType);

    if (!$printerManager || !$printer_name) {
      $response->getBody()->write($png);
      return $response->withHeader('Content-type', 'image/png');
    }

    $printer= $printerManager->findByUri(
      'ipp://' . $cups_host .  '/printers/' . $printer_name
    );

    $jobManager= new \Smalot\Cups\Manager\JobManager(
      $this->builder,
      $this->client,
      $this->responseParser
    );

    $job= new \Smalot\Cups\Model\Job();
    $job->setName('label.png');
    $job->setCopies(1);
    $job->setPageRanges('1-1000');

    /* Using addText doesn't work for some reason, so just make a tmpfile */
    $tmpfn= tempnam(sys_get_temp_dir(), 'Scat');
    file_put_contents($tmpfn, $png);

    $job->addFile($tmpfn, 'label.png', 'image/png');
    //$job->addAttribute('Rotate', '2');
    $job->addAttribute('PageSize', 'Custom.6x4in');

    if ($modifier == 'open') {
      $job->addAttribute('CashDrawerSetting', '1OpenDrawer1');
    }
    $result= $jobManager->send($printer, $job);

    unlink($tmpfn);

    return $response->withJson([ 'result' => 'Printed.' ]);
  }

  public function printZPL(Response $response, $pageType, $zpl) {
    $printerManager= $this->getPrinterManager();

    list ($pageType, $modifier)= explode(':', $pageType);
    $printer_name= $this->config->get('printer.' . $pageType);

    if (!$printerManager || !$printer_name) {
      $response->getBody()->write($zpl);
      return $response->withHeader('Content-type', 'text/plain');
    }

    $printer= $printerManager->findByUri(
      'ipp://' . $cups_host .  '/printers/' . $printer_name
    );

    $jobManager= new \Smalot\Cups\Manager\JobManager(
      $this->builder,
      $this->client,
      $this->responseParser
    );

    $job= new \Smalot\Cups\Model\Job();
    $job->setName('job create file');
    $job->setCopies(1);
    $job->setPageRanges('1-1000');
    $job->addText($zpl, '', 'application/vnd.cups-raw');
    if ($modifier == 'open') {
      $job->addAttribute('CashDrawerSetting', '1OpenDrawer1');
    }
    $result= $jobManager->send($printer, $job);

    return $response->withJson([ 'result' => 'Printed.' ]);
  }

  public function printFromTemplate(Response $response,
                                    $pageType, $template, $data)
  {
    $pdf= $this->generateFromTemplate($template, $data);

    return $this->printPDF($response, $pageType, $pdf);
  }

  public function getPrinters() {
    $printerManager= $this->getPrinterManager();

    return array_map(function ($printer) {
      return $printer->getName();
    }, $printerManager->getList());
  }
}
