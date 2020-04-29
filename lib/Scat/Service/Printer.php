<?php
namespace Scat\Service;

use \Slim\Http\Response as Response;

class Printer
{
  private $view;
  private $config;

  public function __construct(\Slim\Views\Twig $view, Config $config) {
    $this->view= $view;
    $this->config= $config;
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

  public function printFromTemplate(Response $response,
                                    $pageType, $template, $data) {
    $pdf= $this->generateFromTemplate($template, $data);

    $cups_host= $this->config->get('cups.host');
    $cups_user= $this->config->get('cups.user');
    $cups_pass= $this->config->get('cups.pass');
    list ($pageType, $modifier)= explode(':', $pageType);
    $printer= $this->config->get('printer.' . $pageType);

    if (!$cups_host || !$printer) {
      $response->getBody()->write($pdf);
      return $response->withHeader('Content-type', 'application/pdf');
    }

    $client= new \Smalot\Cups\Transport\Client($cups_user, $cups_pass,
                                               [ 'remote_socket' => 'tcp://' .
                                                                    $cups_host
                                                                    ]);
    $builder= new \Smalot\Cups\Builder\Builder(null, true);
    $responseParser= new \Smalot\Cups\Transport\ResponseParser();

    $printerManager= new \Smalot\Cups\Manager\PrinterManager($builder,
                                                             $client,
                                                             $responseParser);
    $printer= $printerManager->findByUri('ipp://' . $cups_host .
                                         '/printers/' . REPORT_PRINTER);

    $jobManager= new \Smalot\Cups\Manager\JobManager($builder,
                                                     $client,
                                                     $responseParser);

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
}
