<?php
require '../vendor/autoload.php';

use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;
use \Slim\Routing\RouteCollectorProxy as RouteCollectorProxy;

/* Some defaults */
error_reporting(E_ALL & ~E_NOTICE);
date_default_timezone_set($_ENV['PHP_TIMEZONE'] ?: $_ENV['TZ']);
bcscale(2);

$DEBUG= $ORM_DEBUG= false;
$config= require $_ENV['SCAT_CONFIG'] ?: dirname(__FILE__).'/../config.php';

$builder= new \DI\ContainerBuilder();
/* Need to set up definitions for services that require manual setup */
$builder->addDefinitions([
  'Slim\Views\Twig' => \DI\get('view'),
  'Scat\Service\Data' => \DI\get('data'),
]);
$container= $builder->build();

$app= \DI\Bridge\Slim\Bridge::create($container);

$app->addRoutingMiddleware();

/* Twig for templating */
$container->set('view', function() {
  /* No cache for now */
  $view= \Slim\Views\Twig::create('../ui', [ 'cache' => false ]);

  /* Set timezone for date functions */
  $tz= @$_ENV['PHP_TIMEZONE'] ?: $_ENV['TZ'];
  $view->getEnvironment()
    ->getExtension('Twig_Extension_Core')
    ->setTimezone($tz);

  // Add the Markdown extension
  $engine= new \Aptoma\Twig\Extension\MarkdownEngine\MichelfMarkdownEngine();
  $view->addExtension(new \Aptoma\Twig\Extension\MarkdownExtension($engine));

  // Add our Twig extensions
  $view->addExtension(new \Scat\TwigExtension());

  return $view;
});
$app->add(\Slim\Views\TwigMiddleware::createFromContainer($app));

/* Hook up the data service, but not lazily because we rely on side-effects */
$container->set('data', new \Scat\Service\Data($config['data']));

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

$app->get('/',
          function (Request $request, Response $response) {
            $q= ($request->getQueryParams() ?
                  '?' . http_build_query($request->getQueryParams()) :
                  '');
            return $response->withRedirect("/sale/new" . $q);
          })->setName('home');

/* Sales */
$app->group('/sale', function (RouteCollectorProxy $app) {
  $app->get('', [ \Scat\Controller\Transactions::class, 'sales' ]);
  $app->post('', [ \Scat\Controller\Transactions::class, 'createSale' ]);
  $app->get('/new', [ \Scat\Controller\Transactions::class, 'newSale' ]);
  $app->get('/{id:[0-9]+}', [ \Scat\Controller\Transactions::class, 'sale' ])
      ->setName('sale');
  $app->patch('/{id:[0-9]+}',
              [ \Scat\Controller\Transactions::class, 'updateSale' ]);

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

  $app->get('/{id:[0-9]+}/email-invoice-form',
            [ \Scat\Controller\Transactions::class, 'emailForm' ]);
  $app->post('/{id:[0-9]+}/email-invoice',
              [ \Scat\Controller\Transactions::class, 'email' ]);

  $app->get('/{id:[0-9]+}/shipping-address',
            [ \Scat\Controller\Transactions::class, 'shippingAddress' ]);
  $app->post('/{id:[0-9]+}/shipping-address',
              [ \Scat\Controller\Transactions::class, 'updateShippingAddress' ]);

  /* Dropships */
  $app->get('/{id:[0-9]+}/dropship[/{dropship_id:[0-9]+}]',
            [ \Scat\Controller\Transactions::class, 'saleDropships' ]);
  $app->post('/{id:[0-9]+}/dropship',
              [ \Scat\Controller\Transactions::class, 'createDropShip' ]);

  /* Shipments */
  $app->get('/{id:[0-9]+}/shipment[/{shipment_id:[0-9]+}]',
            [ \Scat\Controller\Transactions::class, 'saleShipments' ]);
  $app->get('/{id:[0-9]+}/shipment/{shipment_id:[0-9]+}/track',
            [ \Scat\Controller\Transactions::class, 'trackShipment' ]);
  $app->post('/{id:[0-9]+}/shipment/{shipment_id:[0-9]+}/~print-label',
              [ \Scat\Controller\Transactions::class, 'printShipmentLabel' ]);
  $app->post('/{id:[0-9]+}/shipment',
              [ \Scat\Controller\Transactions::class, 'updateShipment' ]);
  $app->patch('/{id:[0-9]+}/shipment/{shipment_id:[0-9]+}',
              [ \Scat\Controller\Transactions::class, 'updateShipment' ]);

  /* Tax */
  $app->post('/{id:[0-9]+}/~capture-tax',
              [ \Scat\Controller\Transactions::class, 'captureTax' ]);
});

/* Purchases */
$app->group('/purchase', function (RouteCollectorProxy $app) {
  $app->get('', [ \Scat\Controller\Transactions::class, 'purchases' ]);
  $app->get('/reorder',
            [ \Scat\Controller\Transactions::class, 'reorderForm' ]);
  $app->post('', [ \Scat\Controller\Transactions::class, 'createPurchase' ]);
  $app->get('/{id}', [ \Scat\Controller\Transactions::class, 'purchase' ])
      ->setName('purchase');
  $app->post('/{id}',
              [ \Scat\Controller\Transactions::class, 'addToPurchase' ]);
});

/* Shipping */
$app->group('/shipment', function (RouteCollectorProxy $app) {
  $app->get('', [ \Scat\Controller\Shipping::class, 'index' ]);
});

/* Catalog */
$app->group('/catalog', function (RouteCollectorProxy $app) {
  $app->get('/search', [ \Scat\Controller\Catalog::class, 'search' ])
      ->setName('catalog-search');
  $app->get('/~reindex', [ \Scat\Controller\Catalog::class, 'reindex' ]);

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

  $app->post('/product/{id:[0-9]+}/media',
            [ \Scat\Controller\Catalog::class, 'productAddMedia' ]);

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

  $app->post('/item/{code:.*}/vendor-item',
              [ \Scat\Controller\Catalog::class, 'findVendorItems' ]);
  $app->delete('/item/{code:.*}/vendor-item/{id:[0-9]+}',
            [ \Scat\Controller\Catalog::class, 'unlinkVendorItem' ]);

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
               $override= \Titi\Model::factory('PriceOverride')
                            ->find_one($request->getParam('id'));
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
  $app->get('/{id:[0-9]+}/loyalty',
            [ \Scat\Controller\People::class, 'loyalty' ]);
  $app->post('/{id:[0-9]+}/~merge',
            [ \Scat\Controller\People::class, 'mergePerson' ]);

  $app->get('/remarketing-list',
            [ \Scat\Controller\People::class, 'remarketingList' ]);

});

/* Clock */
$app->group('/clock', function (RouteCollectorProxy $app) {
  $app->get('', [ \Scat\Controller\Timeclock::class, 'home' ]);
  $app->post('/~punch', [ \Scat\Controller\Timeclock::class, 'punch' ]);
});

/* Gift Cards */
$app->group('/gift-card', function (RouteCollectorProxy $app) {
  $app->get('', [ \Scat\Controller\Giftcards::class, 'home' ]);
  $app->get('/lookup', [ \Scat\Controller\Giftcards::class, 'lookup' ]);

  $app->post('', [ \Scat\Controller\Giftcards::class, 'create' ]);
  $app->get('/{card:[0-9]+}', [ \Scat\Controller\Giftcards::class, 'card' ]);
  $app->get('/{card:[0-9]+}/print',
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
  $app->get('/quick', [ \Scat\Controller\Reports::class, 'quick' ]);
  $app->get('/empty-products',
            [ \Scat\Controller\Reports::class, 'emptyProducts' ]);
  $app->get('/{name}', [ \Scat\Controller\Reports::class, 'oldReport' ]);
});

/* Media */
$app->group('/media', function (RouteCollectorProxy $app) {
  $app->get('', [ \Scat\Controller\Media::class, 'home' ]);
  $app->post('', [ \Scat\Controller\Media::class, 'create' ]);
  $app->get('/{id:[0-9]+}', [ \Scat\Controller\Media::class, 'show' ]);
  $app->post('/{id:[0-9]+}', [ \Scat\Controller\Media::class, 'repair' ]);
  $app->patch('/{id:[0-9]+}', [ \Scat\Controller\Media::class, 'update' ]);
  $app->delete('/{id:[0-9]+}', [ \Scat\Controller\Media::class, 'delete' ]);
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
  $app->get('/test', [ \Scat\Controller\Tax::class, 'test' ]);
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

/* Settings */
$app->group('/settings', function (RouteCollectorProxy $app) {
  $app->get('', [ \Scat\Controller\Settings::class, 'home' ])
      ->setName('settings');
  $app->post('', [ \Scat\Controller\Settings::class, 'create' ]);
  $app->patch('/{id:[0-9]+}',
            [ \Scat\Controller\Settings::class, 'update' ]);
  $app->get('/printers',
            [ \Scat\Controller\Settings::class, 'listPrinters' ]);

  $app->get('/message[/{message_id}]',
            [ \Scat\Controller\Settings::class, 'message' ]);
  $app->post('/message[/{message_id}]',
              [ \Scat\Controller\Settings::class, 'messageUpdate' ]);
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

$app->run();
