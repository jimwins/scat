<?php
namespace Scat\Controller;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Home {
  private $view, $txn;

  public function __construct(View $view, \Scat\Service\Txn $txn) {
    $this->view= $view;
    $this->txn= $txn;
  }

  function home(Request $request, Response $response) {
    if ($GLOBALS['DEBUG'] || $request->getParam('force')) {
      $open_invoices=
        $this->txn->find('customer')
          ->where_in('status', [ 'new', 'filled' ])
          ->find_many();
      $orders_to_process=
        $this->txn->find('customer')
          ->where_in('status', [ 'paid', 'processing' ])
          ->find_many();

      return $this->view->render($response, 'index.html', [
        'open_invoices' => $open_invoices,
        'orders_to_process' => $orders_to_process
      ]);
    } else {
      $q= ($request->getQueryParams() ?
            '?' . http_build_query($request->getQueryParams()) :
            '');
      return $response->withRedirect("/sale/new" . $q);
    }
  }
}
