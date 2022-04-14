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

  public function sales(Request $request, Response $response) {
    $span= $request->getParam('span');
    $begin= $request->getParam('begin');
    $end= $request->getParam('end');

    error_log("reporting sales from '$begin' to '$end' by '$span'\n");

    $data= $this->report->sales($span, $begin, $end);

    $accept= $request->getHeaderLine('Accept');

    if (strpos($accept, 'application/json') !== false)
    {
      return $response->withJson($data['sales']);
    }

    if (strpos($accept, 'application/vnd.scat.dialog+html') !== false) {
      return $this->view->render($response, 'dialog/report-quick.html', $data);
    }

    return $this->view->render($response, 'report/sales.html', $data);
  }

  public function purchases(Request $request, Response $response) {
    $span= $request->getParam('span');
    $begin= $request->getParam('begin');
    $end= $request->getParam('end');

    error_log("reporting purchases from '$begin' to '$end' by '$span'\n");

    $data= $this->report->purchases($span, $begin, $end);

    $accept= $request->getHeaderLine('Accept');

    if (strpos($accept, 'application/json') !== false)
    {
      return $response->withJson($data['purchases']);
    }

    return $this->view->render($response, 'report/purchases.html', $data);
  }

  public function emptyProducts(Request $request, Response $response) {
    $data= $this->report->emptyProducts();
    return $this->view->render($response, 'report/empty-products.html', $data);
  }

  public function backorderedItems(Request $request, Response $response) {
    $data= $this->report->backorderedItems();
    return $this->view->render($response, 'report/backordered-items.html', $data);
  }

  public function kitItems(Request $request, Response $response) {
    $data= $this->report->kitItems();
    return $this->view->render($response, 'report/kit-items.html', $data);
  }

  public function purchasesByVendor(Request $request, Response $response) {
    $begin= $request->getParam('begin');
    $end= $request->getParam('end');

    $data= $this->report->purchasesByVendor($begin, $end);
    return $this->view->render(
      $response,
      'report/purchases-by-vendor.html',
      $data
    );
  }

  public function shipments(Request $request, Response $response) {
    $data= $this->report->shipments();
    return $this->view->render($response, 'report/shipments.html', $data);
  }

  public function shippingCosts(Request $request, Response $response) {
    $begin= $request->getParam('begin');
    $end= $request->getParam('end');

    $data= $this->report->shippingCosts($begin, $end);

    return $this->view->render($response, 'report/shipping-costs.html', $data);
  }

  public function clock(Request $request, Response $response) {
    $begin= $request->getParam('begin');
    if (!$begin) {
      $begin= date('Y-m-d', strtotime('Sunday -2 weeks'));
    }

    $end= $request->getParam('end');
    if (!$end) {
      $end= date('Y-m-d', strtotime('last Saturday'));
    }

    $data= $this->report->clock($begin, $end);
    $data['begin']= $begin;
    $data['end']= $end;

    return $this->view->render($response, 'report/clock.html', $data);
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
