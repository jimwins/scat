<?php
namespace Scat\Controller;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Reports {
  public function __construct(
    private \Scat\Service\Report $report,
    private View $view
  ) {
  }

  public static function registerRoutes(\Slim\Routing\RouteCollectorProxy $app) {
    $app->get('/brand', [ self::class, 'brandSales' ]);
    $app->get('/category', [ self::class, 'categorySales' ]);
    $app->get('/dogs', [ self::class, 'dogs' ]);
    $app->get('/empty-products', [ self::class, 'emptyProducts' ]);
    $app->get('/backordered-items', [ self::class, 'backorderedItems' ]);
    $app->get('/cashflow', [ self::class, 'cashflow' ]);
    $app->get('/drop-by-drop', [ self::class, 'dropByDrop' ]);
    $app->get('/items', [ self::class, 'itemSales' ]);
    $app->get('/inventory-list', [ self::class, 'inventoryList' ]);
    $app->get('/inventory-by-brand', [ self::class, 'inventoryByBrand' ]);
    $app->get('/inventory-value', [ self::class, 'inventoryValue' ]);
    $app->get('/kit-items', [ self::class, 'kitItems' ]);
    $app->get('/performance', [ self::class, 'performance' ]);
    $app->get('/price-change', [ self::class, 'priceChanges' ]);
    $app->get('/purchases-by-vendor', [ self::class, 'purchasesByVendor' ]);
    $app->get('/shipments', [ self::class, 'shipments' ]);
    $app->get('/shipping-costs', [ self::class, 'shippingCosts' ]);
    $app->get('/clock', [ self::class, 'clock' ]);
    $app->get('/sales', [ self::class, 'sales' ]);
    $app->get('/purchases', [ self::class, 'purchases' ]);
    $app->get('/summary', [ self::class, 'summary' ]);
  }

  /* A few simple helper methods */
  public function lastyear() {
    return (new \Datetime('-1 year'))->format('Y-m-d');
  }
  public function thirtydaysago() {
    return (new \Datetime('-30 days'))->format('Y-m-d');
  }
  public function today() {
    return (new \Datetime('now'))->format('Y-m-d');
  }

  public function brandSales(Request $request, Response $response) {
    $begin= $request->getParam('begin') ?? $this->thirtydaysago();
    $end= $request->getParam('end') ?? $this->today();
    $items= $request->getParam('items') ?? '';

    error_log("reporting sales by brand from '$begin' to '$end' for '$items'\n");

    $data= $this->report->brandSales($begin, $end, $items);

    return $this->view->render($response, 'report/brand-sales.html', $data);
  }

  public function categorySales(Request $request, Response $response) {
    $begin= $request->getParam('begin') ?? $this->thirtydaysago();
    $end= $request->getParam('end') ?? $this->today();
    $items= $request->getParam('items') ?? '';

    error_log("reporting sales by category from '$begin' to '$end' for '$items'\n");

    $data= $this->report->categorySales($begin, $end, $items);

    return $this->view->render($response, 'report/category-sales.html', $data);
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

  public function dogs(Request $request, Response $response) {
    $data= $this->report->dogs();
    return $this->view->render($response, 'report/dogs.html', $data);
  }

  public function emptyProducts(Request $request, Response $response) {
    $data= $this->report->emptyProducts();
    return $this->view->render($response, 'report/empty-products.html', $data);
  }

  public function backorderedItems(Request $request, Response $response) {
    $data= $this->report->backorderedItems();
    return $this->view->render($response, 'report/backordered-items.html', $data);
  }

  public function cashflow(Request $request, Response $response) {
    $begin= $request->getParam('begin');
    $end= $request->getParam('end');
    $data= $this->report->cashflow($begin, $end);
    return $this->view->render($response, 'report/cashflow.html', $data);
  }

  public function dropByDrop(Request $request, Response $response) {
    $begin= $request->getParam('begin');
    $end= $request->getParam('end');
    $data= $this->report->sales(null, $begin, $end);
    return $this->view->render($response, 'report/drop-by-drop.html', $data);
  }

  public function inventoryByBrand(Request $request, Response $response) {
    $items= $request->getParam('items') ?? '';

    $data= $this->report->inventoryByBrand($items);

    return $this->view->render($response, 'report/inventory-by-brand.html', $data);
  }

  public function inventoryList(
    Request $request, Response $response,
    \Scat\Service\Catalog $catalog
  ) {
    $items= $request->getParam('items') ?? 'is_kit:0 stock:1';

    $items= $catalog->searchItems($items)->find_many();

    $content_type= 'text/tsv';
    $ext= 'tsv';

    $name= 'inventory.' . $ext;

    $response= $response
      ->withHeader('Content-type', $content_type)
      ->withHeader('Content-disposition',
                    'attachment; filename="' . $name . '"')
      ->withHeader('Cache-control', 'max-age=0');

    $body= $response->getBody();

    $body->write("code\tname\tbarcode\tcost\tqty\r\n");

    foreach ($items as $item) {
      $body->write(
        $item->code . "\t" .
        $item->name . "\t" .
        $item->barcode() . "\t" .
        ($item->most_recent_cost() ?? 0.00) . "\t" .
        $item->stock() . "\r\n"
      );
    }

    return $response->withBody($body);
  }


  public function inventoryValue(Request $request, Response $response) {
    $items= $request->getParam('items') ?? '';

    $data= $this->report->inventoryValue($items);

    return $this->view->render($response, 'report/inventory-value.html', $data);
  }

  public function itemSales(Request $request, Response $response) {
    $begin= $request->getParam('begin') ?? $this->today();
    $end= $request->getParam('end') ?? $this->today();
    $items= $request->getParam('items') ?? '';

    $data= $this->report->itemSales($begin, $end, $items);

    return $this->view->render($response, 'report/item-sales.html', $data);
  }

  public function kitItems(Request $request, Response $response) {
    $data= $this->report->kitItems();
    return $this->view->render($response, 'report/kit-items.html', $data);
  }

  public function performance(Request $request, Response $response) {
    $begin= $request->getParam('begin') ?? $this->lastyear();
    $end= $request->getParam('end') ?? $this->today();
    $items= $request->getParam('items') ?? '';

    if (!$items && ($product= $request->getParam('product'))) {
      $items= "product:" . $product;
    }

    $data= $this->report->performance($begin, $end, $items);

    return $this->view->render($response, 'report/performance.html', $data);
  }

  public function priceChanges(
    Request $request, Response $response,
    \Scat\Service\Catalog $catalog
  ) {
    $vendor= $request->getParam('vendor');
    $items_query= $request->getParam('items');
    $data= $this->report->priceChanges($catalog, $vendor, $items_query);
    return $this->view->render($response, 'report/price-changes.html', $data);
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

  public function summary(Request $request, Response $response) {
    $date= $request->getParam('date') ?? $this->today();

    $data= $this->report->summary($date);

    return $this->view->render($response, 'report/summary.html', $data);
  }
}
