<?php
namespace Scat\Controller;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

/* TODO
 * Probably could use a more automatic or data-driven approach here where we
 * either figure out name & template from URL or just look them up in an array
 *
 * But for now we're mostly just wrapping the old report generation
 */

class Reports {
  private $report;
  private $view;

  public function __construct(\Scat\Service\Report $report, View $view) {
    $this->report= $report;
    $this->view= $view;
  }

  public function quick(Request $request, Response $response) {
    $data= $this->report->sales();
    return $this->view->render($response, 'dialog/report-quick.html', $data);
  }

  public function emptyProducts(Request $request, Response $response) {
    $data= $this->report->emptyProducts();
    return $this->view->render($response, 'report/empty-products.html', $data);
  }

  public function backorderedItems(Request $request, Response $response) {
    $data= $this->report->backorderedItems();
    return $this->view->render($response, 'report/backordered-items.html', $data);
  }

  public function oldReport(Request $request, Response $response, $name) {
    ob_start();
    include "../old-report/report-$name.php";
    $content= ob_get_clean();
    return $this->view->render($response, 'report/old.html', [
      'title' => $GLOBALS['title'],
      'content' => $content,
    ]);
  }
}
