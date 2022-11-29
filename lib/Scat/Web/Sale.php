<?php
namespace Scat\Web;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Sale {
  public function __construct(
    private \Scat\Service\Cart $carts,
    private \Scat\Service\Catalog $catalog,
    private \Scat\Service\Auth $auth,
    private \Scat\Service\Scat $scat,
    private View $view
  ) {
  }

  public function listSales(Request $request, Response $response) {
    $person= $this->auth->get_person_details($request);
    $key= $request->getParam('key');

    if (!$this->auth->verify_access_key($key) &&
        (!$person || $person->role != 'employee'))
    {
      throw new \Slim\Exception\HttpForbiddenException($request, "Wrong key");
    }

    $status= $request->getParam('status') ?: [ 'paid', 'processing' ];

    $sales= $this->carts->findByStatus(
      status: $status,
      yesterday: $request->getParam('yesterday'),
      limit: $key ? null : 100,
    );

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/json') !== false) {
      return $response->withHeader('Cache-control', 'private')->withJson($sales);
    }

    return $this->view->render($response, 'sale/list.html', [
      'sales' => $sales,
    ]);
  }

  public function listItems(
    Request $request,
    Response $response,
    \Scat\Service\Data $data,
  ) {
    $person= $this->auth->get_person_details($request);
    $key= $request->getParam('key');

    if (!$this->auth->verify_access_key($key) &&
        (!$person || $person->role != 'employee'))
    {
      throw new \Slim\Exception\HttpForbiddenException($request, "Wrong key");
    }

    $days= (int)$request->getParam('days') ?: 2;

    $q= "SELECT item.code, item.name, item.stock,
                item.width, item.length, item.height, item.weight,
                (SELECT COUNT(*) FROM item_to_image WHERE item_id = item.id)
                  media,
                SUM(quantity) quantity
           FROM sale_item
           JOIN sale ON sale_item.sale_id = sale.id
           JOIN item ON sale_item.item_id = item.id
          WHERE sale.modified BETWEEN NOW() - INTERVAL ? DAY AND NOW()
            AND sale.status = 'cart'
          GROUP BY item.id
          ORDER BY code";

    $items= $data->for_table('item')->raw_query($q, [ $days ])->find_many();

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/json') !== false) {
      return $response->withJson($items);
    }

    return $this->view->render($response, 'sale/items.html', [
      'items' => $items,
      'days' => $days,
    ]);
  }

  public function sale(Request $request, Response $response, $uuid) {
    $sale= $this->carts->findByUuid($uuid);

    $person= $this->auth->get_person_details($request);

    if (!$sale || !in_array($sale->status, [ 'cart', 'paid' ])) {
      return $this->scat->get_sale_invoice($uuid, $person);
    }

    if (!$sale) {
      throw new \Slim\Exception\HttpNotFoundException($request);
    }

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/json') !== false) {
      return $response->withJson([
        'sale' => $sale,
        'shipping_address' => $sale->shipping_address(),
        'items' => $sale->items()->find_many(),
        'payments' => $sale->payments()->find_many(),
        'notes' => $sale->notes()->find_many(),
      ]);
    }

    // TODO redirect to correct URL depending on $sale->status

    $key= $request->getParam('key');

    if ($key && !$this->auth->verify_access_key($key)) {
      throw new \Slim\Exception\HttpForbiddenException($request, "Wrong key");
    }

    if (!$person ||
        ($person->id != $sale->person_id &&
          $person->role != 'employee'))
    {
      error_log("person_id = {$person->id}, sale->person_id = {$sale->person_id}");
      throw new \Slim\Exception\HttpForbiddenException($request);
    }

    return $this->view->render($response, 'sale/sale.html', [
      'sale' => $sale,
    ]);
  }

  public function thanks(Request $request, Response $response, $uuid) {
    $sale= $this->carts->findByUuid($uuid);
    if (!$sale) {
      throw new \Slim\Exception\HttpNotFoundException($request);
    }

    if ($sale->status != 'paid') {
      if ($sale->status == 'sale') {
        // redirect to sale
      }
      if ($sale->status == '') {
        // other stuff
      }
    }

    return $this->view->render($response, 'sale/thanks.html', [
      'sale' => $sale,
      // only use scat.ecommerce() to report if modified very recently
      'report_sale' => strtotime($sale->modified) > time() - 30,
    ]);
  }

  public function setStatus(Request $request, Response $response, $uuid) {
    $sale= $this->carts->findByUuid($uuid);
    if (!$sale) {
      throw new \Slim\Exception\HttpNotFoundException($request);
    }

    $status= $request->getParam('status');

    if (!in_array($status, array('new','cart','review','unpaid','paid',
                                 'processing','shipped','cancelled','onhold')))
    {
      // XXX better error handling
      throw new \Exception("Didn't understand requested status {$status}.");
    }

    $sale->status= $status;
    $sale->save();

    return $response->withJson($sale);
  }

  public function setAbandonedLevel(
    Request $request,
    Response $response,
    $uuid
  ) {
    $sale= $this->carts->findByUuid($uuid);
    if (!$sale) {
      throw new \Slim\Exception\HttpNotFoundException($request);
    }

    $abandoned_level= (int)$request->getParam('abandoned_level');

    $sale->abandoned_level= $abandoned_level;
    $sale->save();

    return $response->withJson($sale);
  }

  public function trackShipment(
    Request $request, Response $response,
    $uuid,
    $shipment_id
  ) {
    return $this->scat->track_shipment($uuid, $shipment_id);
  }
}
