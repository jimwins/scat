<?php
require '../vendor/autoload.php';

use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;
use \Slim\Routing\RouteCollectorProxy as RouteCollectorProxy;

/* Some defaults */
error_reporting(E_ALL ^ E_DEPRECATED);
$tz= @$_ENV['PHP_TIMEZONE'] ?: @$_ENV['TZ'];
if ($tz) date_default_timezone_set($tz);
bcscale(2);

$DEBUG= $ORM_DEBUG= false;
$config_file= @$_ENV['SCAT_CONFIG'] ?: dirname(__FILE__).'/../config.php';
if (file_exists($config_file)) {
  $config= require $config_file;
} else {
  $config= [
    'data' => [
      'dsn' => 'mysql:host=db;dbname=scat;charset=utf8mb4',
      'options' => [
        'username' => $_ENV['MYSQL_USER'],
        'password' => $_ENV['MYSQL_PASSWORD'],
      ],
    ],
  ];
}

/* Turn deprecation notices back on in debug mode */
if ($DEBUG) {
  error_reporting(E_ALL);
}

$builder= new \DI\ContainerBuilder();
/* Need to set up definitions for services that require manual setup */
$builder->addDefinitions([
  'Slim\Views\Twig' => \DI\get('view'),
  'Scat\Service\Data' => \DI\get('data'),
  'Scat\Service\Config' => \DI\get('config'),
]);
$container= $builder->build();

/* Hook up the data service, but not lazily because we rely on side-effects */
$container->set('data', new \Scat\Service\Data($config['data']));

try {
  $container->set('config', new \Scat\Service\Config($container->get('data')));
} catch (\PDOException $ex) {
  if ($ex->getCode() == '42S02') {
    header("Location: /setup.php");
    exit;
  }

  // Not what we expected? just re-throw so we barf all over the place
  throw $ex;
}

$app= \DI\Bridge\Slim\Bridge::create($container);

$app->addRoutingMiddleware();

/* Twig for templating */
$container->set('view', function($container) {
  /* No cache for now */
  $view= \Slim\Views\Twig::create(
    [ '../ui/pos', '../ui/shared' ],
    [ 'cache' => false ]
  );

  /* Set timezone for date functions */
  $tz= @$_ENV['PHP_TIMEZONE'] ?: @$_ENV['TZ'];
  if ($tz) {
    $view->getEnvironment()
      ->getExtension(\Twig\Extension\CoreExtension::class)
      ->setTimezone($tz);
  }

  // Add the Markdown extension
  $engine= new \Aptoma\Twig\Extension\MarkdownEngine\MichelfMarkdownEngine();
  $view->addExtension(new \Aptoma\Twig\Extension\MarkdownExtension($engine));

  // Add the HTML extension
  $view->addExtension(new \Twig\Extra\Html\HtmlExtension());

  // Add the Bootstrap Icons extension
  $view->addExtension(
    new \whatwedo\TwigBootstrapIcons\Twig\BootstrapIconsExtensions()
  );

  // Add our Twig extensions
  $view->addExtension(new \Scat\TwigExtension($container->get('config')));

  return $view;
});
$app->add(\Slim\Views\TwigMiddleware::createFromContainer($app));

$app->add(new \Middlewares\TrailingSlash());

$errorMiddleware= $app->addErrorMiddleware($DEBUG, true, true);

$errorHandler= $errorMiddleware->getDefaultErrorHandler();
$errorHandler->registerErrorRenderer('application/json',
                                     \Scat\JsonErrorRenderer::class);

/* 404 */
$errorMiddleware->setErrorHandler(
  \Slim\Exception\HttpNotFoundException::class,
  function (Request $request, Throwable $exception,
            bool $displayErrorDetails) use ($container)
  {
    $response= new \Slim\Psr7\Response();

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/json') !== false) {
      $response->getBody()->write(json_encode([ 'error' => 'Not found.' ]));
      return $response
              ->withStatus(404)
              ->withHeader('Content-Type', 'application/json');
    }
    // TODO special handling for application/vnd.scat.dialog?

    return $container->get('view')->render($response, '404.html')
      ->withStatus(404)
      ->withHeader('Content-Type', 'text/html');
  });

/* ROUTES */

/* Home */
$app->get('/', [ \Scat\Controller\Home::class, 'home' ])->setName('home');

/* Sales */
$app->group('/sale', function (RouteCollectorProxy $app) {
  $app->get('', [ \Scat\Controller\Transactions::class, 'sales' ]);
  $app->post('', [ \Scat\Controller\Transactions::class, 'createSale' ]);
  $app->get('/{id:[0-9]+}', [ \Scat\Controller\Transactions::class, 'sale' ])
      ->setName('sale');
  $app->get('/{uuid:[0-9a-f]+}',
            [ \Scat\Controller\Transactions::class, 'saleByUuid' ]);
  $app->patch('/{id:[0-9]+}',
              [ \Scat\Controller\Transactions::class, 'updateSale' ]);
  $app->delete('/{id:[0-9]+}',
              [ \Scat\Controller\Transactions::class, 'deleteSale' ]);
  $app->post('/{id:[0-9]+}/~return',
              [ \Scat\Controller\Transactions::class, 'createReturn' ]);
  $app->post('/{id:[0-9]+}/~print-invoice',
              [ \Scat\Controller\Transactions::class, 'printSaleInvoice' ]);
  $app->post('/{id:[0-9]+}/~print-receipt',
              [ \Scat\Controller\Transactions::class, 'printSaleReceipt' ]);

  /* Items (aka line) */
  $app->post('/{id:[0-9]+}/item',
              [ \Scat\Controller\Transactions::class, 'addItem' ]);
  $app->patch('/{id:[0-9]+}/item/{line_id:[0-9]+}',
              [ \Scat\Controller\Transactions::class, 'updateItem' ]);
  $app->delete('/{id:[0-9]+}/item/{line_id:[0-9]+}',
                [ \Scat\Controller\Transactions::class, 'removeItem' ]);

  /* Payments */
  $app->post('/{id:[0-9]+}/payment',
              [ \Scat\Controller\Transactions::class, 'addPayment' ]);
  $app->get('/{id:[0-9]+}/payment[/{payment_id:[0-9]+}]',
            [ \Scat\Controller\Transactions::class, 'payments' ]);
  $app->post('/{id:[0-9]+}/payment/{payment_id:[0-9]+}',
              [ \Scat\Controller\Transactions::class, 'modifyPayment' ]);
  $app->post('/{id:[0-9]+}/~clear-loyalty-reward',
              [ \Scat\Controller\Transactions::class, 'clearLoyaltyReward' ]);
  $app->post('/{id:[0-9]+}/~remove-discount',
              [ \Scat\Controller\Transactions::class, 'removeDiscount' ]);

  $app->get('/{id:[0-9]+}/email-invoice-form',
            [ \Scat\Controller\Transactions::class, 'emailForm' ]);
  $app->post('/{id:[0-9]+}/email-invoice',
              [ \Scat\Controller\Transactions::class, 'email' ]);

  /* Shipping address */
  $app->get('/{id:[0-9]+}/shipping-address',
            [ \Scat\Controller\Transactions::class, 'shippingAddress' ]);
  $app->post('/{id:[0-9]+}/shipping-address',
              [ \Scat\Controller\Transactions::class, 'updateShippingAddress' ]);

  $app->get('/{id:[0-9]+}/calculate-delivery-form',
            [ \Scat\Controller\Transactions::class, 'calculateDeliveryForm' ]);

  $app->post('/{id:[0-9]+}/~create-cart',
            [ \Scat\Controller\Transactions::class, 'createOnlineCart' ]);


  /* Dropships */
  $app->get('/{id:[0-9]+}/dropship[/{dropship_id:[0-9]+}]',
            [ \Scat\Controller\Transactions::class, 'saleDropships' ]);
  $app->post('/{id:[0-9]+}/dropship',
              [ \Scat\Controller\Transactions::class, 'createDropShip' ]);

  /* Shipments */
  $app->get('/{id:[0-9]+}/shipment[/{shipment_id:[0-9]+}]',
            [ \Scat\Controller\Transactions::class, 'saleShipments' ]);
  $app->post('/{id:[0-9]+}/shipment/{shipment_id:[0-9]+}/~print-label',
              [ \Scat\Controller\Transactions::class, 'printShipmentLabel' ]);
  $app->post('/{id:[0-9]+}/shipment',
              [ \Scat\Controller\Transactions::class, 'updateShipment' ]);
  $app->patch('/{id:[0-9]+}/shipment/{shipment_id:[0-9]+}',
              [ \Scat\Controller\Transactions::class, 'updateShipment' ]);

  /* Deliveries */
  $app->get('/{id:[0-9]+}/delivery[/{delivery_id:[0-9]+}]',
            [ \Scat\Controller\Transactions::class, 'saleDeliveries' ]);
  $app->post('/{id:[0-9]+}/delivery',
              [ \Scat\Controller\Transactions::class, 'updateDelivery' ]);
  $app->patch('/{id:[0-9]+}/delivery/{delivery_id:[0-9]+}',
              [ \Scat\Controller\Transactions::class, 'updateDelivery' ]);

  /* Tax */
  $app->post('/{id:[0-9]+}/~capture-tax',
              [ \Scat\Controller\Transactions::class, 'captureTax' ]);

  /* Report */
  $app->get('/{id:[0-9]+}/report',
            [ \Scat\Controller\Transactions::class, 'report' ]);
});

/* Purchases */
$app->group('/purchase', function (RouteCollectorProxy $app) {
  $app->get('', [ \Scat\Controller\Transactions::class, 'purchases' ]);
  $app->get('/reorder',
            [ \Scat\Controller\Transactions::class, 'reorderForm' ]);
  $app->post('', [ \Scat\Controller\Transactions::class, 'createPurchase' ]);
  $app->get('/{id}', [ \Scat\Controller\Transactions::class, 'purchase' ])
      ->setName('purchase');
  $app->get('/{id}/export',
            [ \Scat\Controller\Transactions::class, 'exportPurchase' ]);
  $app->delete('/{id:[0-9]+}',
              [ \Scat\Controller\Transactions::class, 'deleteSale' ]);
  $app->post('/{id}',
              [ \Scat\Controller\Transactions::class, 'addToPurchase' ]);
  $app->post('/{id}/~mark-all-received',
              [ \Scat\Controller\Transactions::class, 'markAllReceived' ]);
  $app->post('/{id}/~clear-all',
              [ \Scat\Controller\Transactions::class, 'clearAll' ]);
});

/* Corrections */
$app->group('/correction', function (RouteCollectorProxy $app) {
  $app->get('', [ \Scat\Controller\Transactions::class, 'corrections' ]);
  $app->get('/{id}', [ \Scat\Controller\Transactions::class, 'correction' ])
      ->setName('correction');
});

/* Shipping */
$app->group('/shipment', function (RouteCollectorProxy $app) {
  $app->get('', [ \Scat\Controller\Shipping::class, 'index' ]);
  $app->get('/analyze', [ \Scat\Controller\Shipping::class, 'analyze' ]);
  $app->post('/batch', [ \Scat\Controller\Shipping::class, 'createBatch' ]);
  $app->get('/~populate',
            [ \Scat\Controller\Shipping::class, 'populateShipmentData' ]);
  $app->get('/{id}', [ \Scat\Controller\Shipping::class, 'shipment' ])
      ->setName('shipment');
  $app->delete('/{id:[0-9]+}',
                [ \Scat\Controller\Shipping::class, 'deleteShipment' ]);
  $app->get('/{shipment_id:[0-9]+}/track',
            [ \Scat\Controller\Shipping::class, 'trackShipment' ]);
  $app->post('/{id:[0-9]+}/~create-return',
              [ \Scat\Controller\Shipping::class, 'createShipmentReturn' ]);
  $app->get('/{id}/returnLabel',
            [ \Scat\Controller\Shipping::class, 'getReturnLabelPDF' ])
      ->setName('shipment-return-label');
  $app->post('/{id:[0-9]+}/~print-label',
              [ \Scat\Controller\Shipping::class, 'printShipmentLabel' ]);
  $app->post('/{id:[0-9]+}/~refund',
              [ \Scat\Controller\Shipping::class, 'refundShipment' ]);
  $app->post('/~print-limited-quantity-label',
              [ \Scat\Controller\Shipping::class, 'printLimitedQuantityLabel' ]);
});

/* Address */
$app->group('/address', function (RouteCollectorProxy $app) {
  $app->get('/{id}', [ \Scat\Controller\Address::class, 'show' ])
      ->setName('address');
  $app->patch('/{id}', [ \Scat\Controller\Address::class, 'update' ]);
});

/* Catalog */
$app->group('/catalog', function (RouteCollectorProxy $app) {
  $app->get('/search', [ \Scat\Controller\Catalog::class, 'search' ])
      ->setName('catalog-search');
  $app->get('/~reindex', [ \Scat\Controller\Catalog::class, 'reindex' ]);

  $app->post('/~mark-inventoried',
              [ \Scat\Controller\Catalog::class, 'markInventoried' ]);
  $app->post('/~print-count-sheet',
              [ \Scat\Controller\Catalog::class, 'printCountSheet' ]);

  $app->get('/custom', [ \Scat\Controller\Catalog::class, 'custom' ]);

  $app->get('/whats-new', [ \Scat\Controller\Catalog::class, 'whatsNew' ])
      ->setName('catalog-whats-new');

  $app->get('/brand[/{brand}]', [ \Scat\Controller\Catalog::class, 'brand' ])
      ->setName('catalog-brand');
  $app->post('/brand[/{brand}]',
              [ \Scat\Controller\Catalog::class, 'brandUpdate' ]);

  $app->get('/department[/{id:[0-9]+}]',
            [ \Scat\Controller\Catalog::class, 'dept' ])
      ->setName('catalog-department');
  $app->post('/department[/{id:[0-9]+}]',
            [ \Scat\Controller\Catalog::class, 'deptUpdate' ]);

  $app->get('/product[/{id:[0-9]+}]',
            [ \Scat\Controller\Catalog::class, 'product' ])
      ->setName('catalog-product');
  $app->post('/product[/{id:[0-9]+}]',
            [ \Scat\Controller\Catalog::class, 'productUpdate' ]);

  $app->get('/product/{id:[0-9]+}/media',
            [ \Scat\Controller\Catalog::class, 'productEditMedia' ]);
  $app->post('/product/{id:[0-9]+}/media',
            [ \Scat\Controller\Catalog::class, 'productAddMedia' ]);
  $app->delete('/product/{id:[0-9]+}/media/{image_id:[0-9]+}',
            [ \Scat\Controller\Catalog::class, 'productUnlinkMedia' ]);

  $app->post('/item',
              [ \Scat\Controller\Catalog::class, 'updateItem' ]);
  $app->post('/item/~bulk-add',
              [ \Scat\Controller\Catalog::class, 'bulkAddItems' ]);

  $app->post('/item/{code:.*}/~print-label',
            [ \Scat\Controller\Catalog::class, 'printItemLabel' ]);

  $app->post('/item/{code:.*}/~merge',
            [ \Scat\Controller\Catalog::class, 'mergeItem' ]);

  $app->post('/item/{code:.*}/barcode',
            [ \Scat\Controller\Catalog::class, 'addItemBarcode' ]);
  $app->patch('/item/{code:.*}/barcode/{barcode:.*}',
            [ \Scat\Controller\Catalog::class, 'updateItemBarcode' ]);
  $app->delete('/item/{code:.*}/barcode/{barcode:.*}',
            [ \Scat\Controller\Catalog::class, 'deleteItemBarcode' ]);

  $app->post('/item/{code:.*}/kit',
            [ \Scat\Controller\Catalog::class, 'addKitItem' ]);
  $app->patch('/item/{code:.*}/kit/{id:[0-9]+}',
            [ \Scat\Controller\Catalog::class, 'updateKitItem' ]);
  $app->delete('/item/{code:.*}/kit/{id:[0-9]+}',
            [ \Scat\Controller\Catalog::class, 'deleteKitItem' ]);

  $app->get('/item/{code:.+}/media',
            [ \Scat\Controller\Catalog::class, 'itemGetMedia' ]);
  $app->post('/item/{code:.+}/media',
            [ \Scat\Controller\Catalog::class, 'itemAddMedia' ]);
  $app->delete('/item/{code:.+}/media/{image_id:[0-9]+}',
            [ \Scat\Controller\Catalog::class, 'itemUnlinkMedia' ]);

  $app->post('/item/{code:.*}/vendor-item',
              [ \Scat\Controller\Catalog::class, 'findVendorItems' ]);
  $app->delete('/item/{code:.*}/vendor-item/{id:[0-9]+}',
            [ \Scat\Controller\Catalog::class, 'unlinkVendorItem' ]);

  $app->get('/item/{code:.+}/googleHistory',
            [ \Scat\Controller\Catalog::class, 'itemGetGoogleHistory' ]);

  $app->get('/item/{code:.+}/shippingEstimate',
            [ \Scat\Controller\Catalog::class, 'itemGetShippingEstimate' ]);

  $app->post('/item/~bulk-update',
            [ \Scat\Controller\Catalog::class, 'bulkItemUpdate' ]);

  /* Needs to be after other /item */
  $app->get('/item[/{code:.*}]', [ \Scat\Controller\Catalog::class, 'item' ])
      ->setName('catalog-item');
  $app->patch('/item/{code:.*}',
              [ \Scat\Controller\Catalog::class, 'updateItem' ]);

  $app->get('/vendor-item[/{id:[0-9]+}]',
            [ \Scat\Controller\Catalog::class, 'vendorItem' ]);

  $app->post('/vendor-item/search',
            [ \Scat\Controller\Catalog::class, 'vendorItemSearch' ]);

  $app->post('/vendor-item',
              [ \Scat\Controller\Catalog::class, 'updateVendorItem' ]);
  $app->patch('/vendor-item/{id:[0-9]+}',
              [ \Scat\Controller\Catalog::class, 'updateVendorItem' ]);

  $app->get('/vendor-item/{id:[0-9]+}/stock',
              [ \Scat\Controller\Catalog::class, 'vendorItemStock' ]);

  $app->get('/price-overrides',
             function (Request $request, Response $response, View $view) {
               $price_overrides= \Titi\Model::factory('PriceOverride')
                                  ->order_by_asc('pattern')
                                  ->order_by_asc('minimum_quantity')
                                  ->find_many();

               return $view->render($response, 'catalog/price-overrides.html',[
                'price_overrides' => $price_overrides,
               ]);
             })->setName('catalog-price-overrides');
  $app->post('/price-overrides/~delete',
             function (Request $request, Response $response) {
               $override= \Titi\Model::factory('PriceOverride')
                            ->find_one($request->getParam('id'));
               if (!$override) {
                 throw new \Slim\Exception\HttpNotFoundException($request);
               }
               $override->delete();
               return $response->withJson([ 'message' => 'Success!' ]);
             });
  $app->post('/price-overrides/~edit',
             function (Request $request, Response $response) {
               $id= $request->getParam('id');
               $override= $id ? \Titi\Model::factory('PriceOverride')->find_one($id) : null;
               if (!$override) {
                 $override= \Titi\Model::factory('PriceOverride')->create();
               }
               $override->pattern_type= $request->getParam('pattern_type');
               $override->pattern= $request->getParam('pattern');
               $override->minimum_quantity= $request->getParam('minimum_quantity');
               $override->setDiscount($request->getParam('discount'));
               $override->expires= $request->getParam('expires') ?: null;
               $override->in_stock= $request->getParam('in_stock');
               $override->save();
               return $response->withJson($override);
             });
  $app->get('/price-override-form',
            function (Request $request, Response $response, View $view) {
              $override= \Titi\Model::factory('PriceOverride')
                           ->find_one($request->getParam('id'));
              return $view->render($response,
                                         'dialog/price-override-edit.html',
                                         [ 'override' => $override ]);
            });

  $app->get('/feed',
            [ \Scat\Controller\Catalog::class, 'itemFeed' ]);
  $app->get('/localFeed',
            [ \Scat\Controller\Catalog::class, 'itemLocalFeed' ]);
  $app->get('/costFeed',
            [ \Scat\Controller\Catalog::class, 'itemCostFeed' ]);

  $app->get('[/{dept}[/{subdept}[/{product}]]]',
            [ \Scat\Controller\Catalog::class, 'catalogPage' ])
      ->setName('catalog');
});

/* People */
$app->group('/person', function (RouteCollectorProxy $app) {
  $app->get('', [ \Scat\Controller\People::class, 'home' ])
      ->setName('people');

  $app->post('', [ \Scat\Controller\People::class, 'createPerson' ]);

  $app->map(['GET', 'POST'], '/search',
            [ \Scat\Controller\People::class, 'search' ])
      ->setName('people-search');

  $app->get('/{id:[0-9]+}',
            [ \Scat\Controller\People::class, 'person' ])
      ->setName('person');
  $app->patch('/{id:[0-9]+}',
            [ \Scat\Controller\People::class, 'updatePerson' ]);
  $app->get('/{id:[0-9]+}/items',
            [ \Scat\Controller\People::class, 'items' ]);
  $app->post('/{id:[0-9]+}/items',
             [ \Scat\Controller\People::class, 'uploadItems' ]);
  $app->post('/{id:[0-9]+}/items/~clear-promos',
             [ \Scat\Controller\People::class, 'clearPromos' ]);
  $app->get('/{id:[0-9]+}/loyalty',
            [ \Scat\Controller\People::class, 'loyalty' ]);
  $app->post('/{id:[0-9]+}/loyalty',
              [ \Scat\Controller\People::class, 'updateLoyalty' ]);
  $app->get('/{id:[0-9]+}/backorders',
            [ \Scat\Controller\People::class, 'backorderReport' ]);
  $app->get('/{id:[0-9]+}/~merge',
            [ \Scat\Controller\People::class, 'mergePerson' ]);
  $app->post('/{id:[0-9]+}/~merge',
              [ \Scat\Controller\People::class, 'mergePerson' ]);
  $app->get('/{id:[0-9]+}/sale',
            [ \Scat\Controller\People::class, 'sales' ]);

  $app->get('/{id:[0-9]+}/sms',
            [ \Scat\Controller\People::class, 'startSms' ]);
  $app->post('/{id:[0-9]+}/sms',
            [ \Scat\Controller\People::class, 'sendSms' ]);

  $app->get('/{id:[0-9]+}/tax-exemption',
            [ \Scat\Controller\People::class, 'getTaxExemption' ]);
  $app->post('/{id:[0-9]+}/tax-exemption',
              [ \Scat\Controller\People::class, 'setTaxExemption' ]);

  $app->get('/remarketing-list',
            [ \Scat\Controller\People::class, 'remarketingList' ]);

});

/* Clock */
$app->group('/clock', function (RouteCollectorProxy $app) {
  $app->get('', [ \Scat\Controller\Timeclock::class, 'home' ]);
  $app->post('/~punch', [ \Scat\Controller\Timeclock::class, 'punch' ]);
  $app->get('/{id:[0-9]+}', [ \Scat\Controller\Timeclock::class, 'getPunch' ]);
  $app->patch('/{id:[0-9]+}', [ \Scat\Controller\Timeclock::class, 'updatePunch' ]);
});

/* Gift Cards */
$app->group('/gift-card', function (RouteCollectorProxy $app) {
  $app->get('', [ \Scat\Controller\Giftcards::class, 'home' ]);
  $app->get('/lookup', [ \Scat\Controller\Giftcards::class, 'lookup' ]);

  $app->post('', [ \Scat\Controller\Giftcards::class, 'create' ]);
  $app->get('/{card:[0-9]+}', [ \Scat\Controller\Giftcards::class, 'card' ]);
  $app->post('/{card:[0-9]+}/~print',
            [ \Scat\Controller\Giftcards::class, 'printCard' ]);
  $app->get('/{card:[0-9]+}/email-form',
            [ \Scat\Controller\Giftcards::class, 'getEmailForm' ]);
  $app->post('/{card:[0-9]+}/email',
              [ \Scat\Controller\Giftcards::class, 'emailCard' ]);

  $app->post('/{card:[0-9]+}',
              [ \Scat\Controller\Giftcards::class, 'addTransaction' ]);
});

/* Reports */
$app->group('/report', function (RouteCollectorProxy $app) {
  $app->get('/empty-products',
            [ \Scat\Controller\Reports::class, 'emptyProducts' ]);
  $app->get('/backordered-items',
            [ \Scat\Controller\Reports::class, 'backorderedItems' ]);
  $app->get('/cashflow',
            [ \Scat\Controller\Reports::class, 'cashflow' ]);
  $app->get('/drop-by-drop',
            [ \Scat\Controller\Reports::class, 'dropByDrop' ]);
  $app->get('/kit-items',
            [ \Scat\Controller\Reports::class, 'kitItems' ]);
  $app->get('/purchases-by-vendor',
            [ \Scat\Controller\Reports::class, 'purchasesByVendor' ]);
  $app->get('/shipments',
            [ \Scat\Controller\Reports::class, 'shipments' ]);
  $app->get('/shipping-costs',
            [ \Scat\Controller\Reports::class, 'shippingCosts' ]);
  $app->get('/clock',
            [ \Scat\Controller\Reports::class, 'clock' ]);
  $app->get('/sales',
            [ \Scat\Controller\Reports::class, 'sales' ]);
  $app->get('/purchases',
            [ \Scat\Controller\Reports::class, 'purchases' ]);
  $app->get('/{name}', [ \Scat\Controller\Reports::class, 'oldReport' ]);
});

/* Media */
$app->group('/media', function (RouteCollectorProxy $app) {
  $app->get('', [ \Scat\Controller\Media::class, 'home' ]);
  $app->post('', [ \Scat\Controller\Media::class, 'create' ]);
  $app->get('/{id:[0-9]+}', [ \Scat\Controller\Media::class, 'show' ])
      ->setName('media');
  $app->post('/{id:[0-9]+}', [ \Scat\Controller\Media::class, 'repair' ]);
  $app->patch('/{id:[0-9]+}', [ \Scat\Controller\Media::class, 'update' ]);
  $app->delete('/{id:[0-9]+}', [ \Scat\Controller\Media::class, 'delete' ]);
  $app->get('/~fix', [ \Scat\Controller\Media::class, 'fix' ]);
});

/* Ads */
$app->group('/ad', function (RouteCollectorProxy $app) {
  $app->get('', [ \Scat\Controller\InternalAd::class, 'home' ]);
  $app->post('', [ \Scat\Controller\InternalAd::class, 'update' ]);
  $app->get('/{id:[0-9]+}', [ \Scat\Controller\InternalAd::class, 'show' ]);
  $app->post('/{id:[0-9]+}', [ \Scat\Controller\InternalAd::class, 'update' ]);
});

/* Notes */
$app->group('/note', function (RouteCollectorProxy $app) {
  $app->get('[/{id:[0-9]+}]', [ \Scat\Controller\Notes::class, 'view' ]);
  $app->post('', [ \Scat\Controller\Notes::class, 'create' ]);
  $app->patch('/{id:[0-9]+}', [ \Scat\Controller\Notes::class, 'update' ]);
});

/* Till */
$app->group('/till', function (RouteCollectorProxy $app) {
  $app->get('', [ \Scat\Controller\Till::class, 'home' ]);
  $app->post('/~print-change-order',
              [ \Scat\Controller\Till::class, 'printChangeOrder' ]);
  $app->post('/~count',
              [ \Scat\Controller\Till::class, 'count' ]);
  $app->post('/~withdraw-cash',
              [ \Scat\Controller\Till::class, 'withdrawCash' ]);
});

/* Safari notifications */
$app->group('/push', function (RouteCollectorProxy $app) {
  $app->get('', [ \Scat\Controller\Push::class, 'home' ]);
  $app->post('/v2/pushPackages/{id}',
              [ \Scat\Controller\Push::class, 'pushPackages' ]);
  $app->post('/v1/devices/{token}/registrations/{id}',
              [ \Scat\Controller\Push::class, 'registerDevice' ]);
  $app->delete('/v1/devices/{token}/registrations/{id}',
                [ \Scat\Controller\Push::class, 'forgetDevice' ]);
  $app->post('/v1/log', [ \Scat\Controller\Push::class, 'log' ]);
  $app->post('/~notify', [ \Scat\Controller\Push::class, 'pushNotification' ]);
});

/* Tax stuff */
$app->group('/tax', function (RouteCollectorProxy $app) {
  $app->get('/~ping', [ \Scat\Controller\Tax::class, 'ping' ]);
  $app->get('/tic', [ \Scat\Controller\Tax::class, 'getTICs' ]);
  $app->get('/~capture', [ \Scat\Controller\Tax::class, 'captureMissing' ]);
});
$app->get('/~webhook/tax',
             function (Request $request, Response $response,
                       \Scat\Service\Tax $tax) {
                return $response->withJson($tax->ping());
             });

/* SMS */
$app->group('/sms', function (RouteCollectorProxy $app) {
  $app->get('', [ \Scat\Controller\SMS::class, 'home' ]);
  $app->map(['GET','POST'], '/~send',
            [ \Scat\Controller\SMS::class, 'send' ]);
  $app->post('/~send-rewardsplus',
            [ \Scat\Controller\SMS::class, 'sendRewardsPlus' ]);
  $app->post('/~receive',
             [ \Scat\Controller\SMS::class, 'receive' ]);
  $app->get('/~register', [ \Scat\Controller\SMS::class, 'register' ]);

});

/* Shipping */
$app->group('/shipping', function (RouteCollectorProxy $app) {
  $app->get('/~register', [ \Scat\Controller\Shipping::class, 'register' ]);
  $app->get('/~check-stalled',
            [ \Scat\Controller\Shipping::class, 'checkStalledTrackers' ]);
});
$app->post('/~webhook/shipping',
            [ \Scat\Controller\Shipping::class, 'handleWebhook' ]);
$app->post('/~webhook/shippo',
            [ \Scat\Controller\Shipping::class, 'handleShippoWebhook' ]);

$app->get('/dialog/{dialog}',
          function (Request $request, Response $response, $dialog, View $view) {
            return $view->render($response, "dialog/$dialog");
          });

$app->get('/~ready-for-publish',
          function (Request $request, Response $response) {
            if (file_exists('/tmp/ready-for-publish')) {
              $response->getBody()->write('OK');
              unlink('/tmp/ready-for-publish');
            } else {
              $response->getBody()->write('NO');
            }
            return $response;
          });
$app->post('/~ready-for-publish',
           function (Request $request, Response $response) {
             touch('/tmp/ready-for-publish');
             return $response;
           });

/* Ordure */
$app->group('/ordure', function (RouteCollectorProxy $app) {
  $app->get('/~fix-loyalty', [ \Scat\Controller\Ordure::class, 'fixLoyalty' ]);
  $app->get('/~push-prices', [ \Scat\Controller\Ordure::class, 'pushPrices' ]);
  $app->get('/~pull-orders', [ \Scat\Controller\Ordure::class, 'pullOrders' ]);
  $app->get('/~pull-signups',
            [ \Scat\Controller\Ordure::class, 'pullSignups' ]);
  $app->get('/~process-abandoned-carts',
            [ \Scat\Controller\Ordure::class, 'processAbandonedCarts' ]);
});

/* Newsletter */
$app->get('/newsletter/~register-webhooks',
          function (Request $request, Response $response,
                    \Scat\Service\Newsletter $newsletter) {
  return $response->withJson($newsletter->registerWebhooks());
});
$app->post('/~webhook/newsletter',
            [ \Scat\Controller\People::class, 'handleNewsletterWebhook' ]);

/* QuickBooks */
$app->group('/quickbooks', function (RouteCollectorProxy $app) {
  $app->get('', [ \Scat\Controller\Quickbooks::class, 'home' ]);
  $app->get('/verify-accounts',
            [ \Scat\Controller\Quickbooks::class, 'verifyAccounts' ]);
  $app->post('/~create-account',
            [ \Scat\Controller\Quickbooks::class, 'createAccount' ]);
  $app->get('/~disconnect',
            [ \Scat\Controller\Quickbooks::class, 'disconnect' ]);
  $app->post('/~sync', [ \Scat\Controller\Quickbooks::class, 'sync' ]);
});

/* Scale */
$app->get('/scale', [ \Scat\Controller\Scale::class, 'home' ]);

/* Settings */
$app->group('/settings', function (RouteCollectorProxy $app) {
  $app->get('', [ \Scat\Controller\Settings::class, 'home' ])
      ->setName('settings');
  $app->post('', [ \Scat\Controller\Settings::class, 'create' ]);
  $app->patch('/{id:[0-9]+}',
            [ \Scat\Controller\Settings::class, 'update' ]);
  $app->get('/printers',
            [ \Scat\Controller\Settings::class, 'printers' ]);

  $app->get('/message[/{message_id}]',
            [ \Scat\Controller\Settings::class, 'message' ]);
  $app->post('/message[/{message_id}]',
              [ \Scat\Controller\Settings::class, 'messageUpdate' ]);

  $app->get('/address',
            [ \Scat\Controller\Settings::class, 'address' ]);

  $app->get('/wordform[/{wordform_id}]',
            [ \Scat\Controller\Settings::class, 'wordform' ]);
  $app->post('/wordform[/{wordform_id}]',
              [ \Scat\Controller\Settings::class, 'wordformUpdate' ]);

  $app->get('/advanced',
            [ \Scat\Controller\Settings::class, 'advanced' ]);

});

/* Webhooks */
$app->map(['GET', 'POST'], '/~webhook/ping', function (Response $response) {
  return $response->withJson([ 'message' => 'Received' ]);
});
$app->post('/~webhook/sms', [ \Scat\Controller\SMS::class, 'receive' ]);
$app->post('/~webhook/instagram',
            [ \Scat\Controller\Media::class, 'addFromInstagram' ]);

$app->map(['GET', 'POST'], '/~webhook[/{hook:[a-z]*}]',
            function (Request $request, Response $response, $hook) {
              error_log("Received webhook $hook\n");
              file_put_contents("/tmp/$hook.json", $request->getBody());
              return $response->withJson([ 'message' => 'Received.' ]);
            });

/* Info (DEBUG only) */
if ($DEBUG) {
  $app->get('/info',
            function (Request $request, Response $response) {
              ob_start();
              phpinfo();
              $response->getBody()->write(ob_get_clean());
              return $response;
            })->setName('info');
}

/*
 * When running with our demo docker-compose setup, nginx can't access the
 * static files so we serve them from here.
 */
$app->get('/{path:.*}', function (Request $request, Response $response, $path) {
  if (preg_match('/\.(js|css|ttf|svg|eot|woff|woff2)$/', $path, $m) && file_exists("../" . $path)) {
    $fp= fopen("../" . $path, 'r');
    $types= [
      'js' => 'application/javascript',
      'css' => 'text/css',
      'ttf' => 'font/ttf',
      'svg' => 'image/svg+xml',
      'eot' => 'application/vnd.ms-fontobject',
      'woff' => 'font/woff',
      'woff2' => 'font/woff2',
    ];
    return
      $response
        ->withHeader('Content-type', $types[$m[1]])
        ->withBody(new \GuzzleHttp\Psr7\Stream($fp));
  }
  throw new \Slim\Exception\HttpNotFoundException($request);
});

$app->run();
