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

  function home(Request $request, Response $response,
                \Scat\Service\Txn $txn,
                \Scat\Service\Search $search)
  {
    $open_invoices=
      $this->txn->find('customer')
        ->where_in('status', [ 'new', 'filled' ])
        ->find_many();
    $orders_to_print=
      $this->txn->find('customer')
        ->where_in('status', [ 'paid' ])
        ->find_many();
    $orders_to_process=
      $this->txn->find('customer')
        ->where_in('status', [ 'processing' ])
        ->find_many();

    $q= trim($request->getParam('q'));
    if ($q) {
      if (preg_match('/^((%V|@)INV-)(\d+)/', $q, $m)) {
        $match= $txn->fetchById($m[3]);
        if ($match) {
          return $response->withRedirect(
            ($txn->type == 'customer' ? '/sale/' : '/purchase/') . $match->id
          );
        }
      }

      $limit= 10;

      $items= $search->searchItems($q, $limit);

      /*
        Fallback: if we found nothing and it looks like a barcode, try
        searching for an exact match on the barcode to catch items
        inadvertantly set inactive.
      */
      if (count($items) == 0 && preg_match('/^[-0-9x]+$/i', $q)) {
        $items= $search->searchItems("barcode:\"$q\" active:0");
      }

      $results= [ 'items' => $items ];
    }

    if (($block= $request->getParam('block'))) {
      $html= $this->view->fetchBlock('index.html', $block, [
        'q' => $q,
        'results' => $results,
        'open_invoices' => $open_invoices,
        'orders_to_print' => $orders_to_print,
        'orders_to_process' => $orders_to_process
      ]);

      $response->getBody()->write($html);
      return $response;
    }

    return $this->view->render($response, 'index.html', [
      'q' => $q,
      'results' => $results,
      'open_invoices' => $open_invoices,
      'orders_to_print' => $orders_to_print,
      'orders_to_process' => $orders_to_process
    ]);
  }
}
