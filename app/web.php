<?php
require '../vendor/autoload.php';

use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;
use \Slim\Routing\RouteCollectorProxy as RouteCollectorProxy;

/* Some defaults */
error_reporting(E_ALL & ~E_NOTICE);
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
        'return_result_sets' => true,
        'username' => $_ENV['MYSQL_USER'],
        'password' => $_ENV['MYSQL_PASSWORD'],
      ],
    ],
  ];
}

$builder= new \DI\ContainerBuilder();
/* Need to set up definitions for services that require manual setup */
$builder->addDefinitions([
  'Slim\Views\Twig' => \DI\get('view'),
  'Scat\Service\Data' => \DI\get('data'),
  'Scat\Service\Config' => \DI\get('config'),
]);
$container= $builder->build();

$container->set('config', new \Scat\Service\Config());

$app= \DI\Bridge\Slim\Bridge::create($container);

$app->addRoutingMiddleware();

/* Twig for templating */
$container->set('view', function($container) {
  /* No cache for now */
  $view= \Slim\Views\Twig::create(
    [ '../ui/web', '../ui/shared' ],
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

  // Add StringLoader extension
  $view->addExtension(new \Twig\Extension\StringLoaderExtension());

  // Add our Twig extensions
  $view->addExtension(new \Scat\TwigExtension($container->get('config')));

  return $view;
});
$app->add(\Slim\Views\TwigMiddleware::createFromContainer($app));

/* Hook up the data service, but not lazily because we rely on side-effects */
$container->set('data', new \Scat\Service\Data($config['webdata']));

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

    return $container->get('view')->render($response, '404.html')
      ->withStatus(404)
      ->withHeader('Content-Type', 'text/html');
  });

/* ROUTES */

/* Catalog */
$app->group('/art-supplies', function (RouteCollectorProxy $app) {
  /* web has it's own search implementation */
  $app->get('/search', [ \Scat\Web\Catalog::class, 'search' ])
      ->setName('catalog-search');
  $app->get('/whats-new', [ \Scat\Controller\Catalog::class, 'whatsNew' ])
      ->setName('catalog-whats-new');
  $app->get('/brand[/{brand}]', [ \Scat\Controller\Catalog::class, 'brand' ])
      ->setName('catalog-brand');
  $app->get('[/{dept}[/{subdept}[/{product}[/{item}]]]]',
            [ \Scat\Controller\Catalog::class, 'catalogPage' ])
      ->setName('catalog')
      ->add(function ($request, $handler) {
          return $handler->handle($request->withAttribute('no_solo_item', true));
      });
});

/* Buy a Gift Card */
// TODO

/* Cart */
$app->group('/cart', function (RouteCollectorProxy $app) {
  $app->get('', [ \Scat\Web\Cart::class, 'cart' ])
      ->setName('cart');
  $app->post('', [ \Scat\Web\Cart::class, 'cartUpdate' ])
      ->setName('update-cart');
  $app->post('/add-item', [ \Scat\Web\Cart::class, 'addItem' ]);

  $app->get('/checkout', [ \Scat\Web\Cart::class, 'checkout' ])
      ->setName('checkout');

  /* Amazon */
  $app->get('/checkout/amzn', [ \Scat\Web\Cart::class, 'amznCheckout' ])
      ->setName('checkout-amzn');
  $app->get('/pay/amzn', [ \Scat\Web\Cart::class, 'amznPay' ])
      ->setName('pay-amzn');
  $app->get('/finalize/amzn', [ \Scat\Web\Cart::class, 'amznFinalize' ])
      ->setName('finalize-amzn');

  /* PayPal */
  $app->get('/checkout/paypal', [ \Scat\Web\Cart::class, 'paypalCheckout' ])
      ->setName('checkout-paypal');
  $app->get('/checkout/paypal-order',
            [ \Scat\Web\Cart::class, 'paypalOrder' ]);
  $app->post('/checkout/paypal-update',
            [ \Scat\Web\Cart::class, 'paypalUpdate' ]);
  $app->get('/checkout/paypal-pickup',
            [ \Scat\Web\Cart::class, 'paypalSetPickup' ]);
  $app->post('/checkout/paypal-shipped',
            [ \Scat\Web\Cart::class, 'paypalSetAddress' ]);
  $app->get('/pay/paypal', [ \Scat\Web\Cart::class, 'paypalPay' ])
      ->setName('pay-paypal');

  /* Stripe */
  $app->get('/checkout/stripe', [ \Scat\Web\Cart::class, 'stripeCheckout' ])
      ->setName('checkout-stripe');
  $app->get('/checkout/stripe-pickup',
            [ \Scat\Web\Cart::class, 'setCurbsidePickup' ]);
  $app->get('/checkout/stripe-shipped',
            [ \Scat\Web\Cart::class, 'setAddress' ]);
  $app->get('/finalize/stripe', [ \Scat\Web\Cart::class, 'stripeFinalize' ])
      ->setName('finalize-stripe');

})->add($container->get(\Scat\Middleware\Cart::class));

$app->post('/~webhook/stripe',
            [ \Scat\Web\Cart::class, 'handleStripeWebhook' ]);

/* Sale (= a completed cart) */
$app->group('/sale', function (RouteCollectorProxy $app) {
  $app->get('/{uuid}/thanks', [ \Scat\Web\Sale::class, 'thanks' ])
      ->setName('sale-thanks');
});

/* Contact */
$app->post('/contact', [ \Scat\Web\Contact::class, 'handleContact' ])
    ->add(new \RKA\Middleware\IpAddress(/* TODO check proxy? */))
    ->setName('handleContact');

/* Tracking */
$app->group('/track', function (RouteCollectorProxy $app) {
  $app->get('/{service}/{code}', function ($request, $response, $service, $code)
  {
    switch ($service) {
    case 'ups':
    case 'upsdap':
      $uri= 'http://wwwapps.ups.com/WebTracking/processInputRequest?AgreeToTermsAndConditions=yes&track.x=38&track.y=9&InquiryNumber1=' . $code;
      return $response->withRedirect($uri);
    case 'usps':
      $uri= 'https://tools.usps.com/go/TrackConfirmAction.action?tLabels=' . $code;
      return $response->withRedirect($uri);
    case 'ontrac':
      $uri= 'http://www.ontrac.com/trackingres.asp?tracking_number=' . $code;
      return $response->withRedirect($uri);
    case 'fedex':
      $uri= 'https://www.fedex.com/apps/fedextrack/?cntry_code=us&tracknumbers=' . $code;
      return $response->withRedirect($uri);
    case 'gso':
      $uri= 'https://www.gso.com/Tracking/PackageDetail?TrackingNumber=' . $code;
      return $response->withRedirect($uri);
    case 'yrc':
      $uri= 'http://my.yrc.com/dynamic/national/servlet?CONTROLLER=com.rdwy.ec.rextracking.http.controller.ProcessPublicTrackingController&PRONumber=' . $code;
      return $response->withRedirect($uri);
    default:
      throw new \Slim\Exception\HttpNotFoundException($request);
    }
  });
});

/* Auth & Rewards */
$app->group('', function (RouteCollectorProxy $app) {
  $app->get('/account', [ \Scat\Web\Auth::class, 'account' ])
      ->setName('account');
  $app->get('/login/key/{key:.*}', [ \Scat\Web\Auth::class, 'handleLoginKey' ])
      ->setName('handleLoginKey');
  $app->get('/login', [ \Scat\Web\Auth::class, 'loginForm' ])
      ->setName('login');
  $app->post('/login', [ \Scat\Web\Auth::class, 'handleLogin' ])
      ->setName('handleLogin');
  $app->get('/logout', [ \Scat\Web\Auth::class, 'logout' ])
      ->setName('logout');
});

/* Webhooks */
// TODO

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

/* Pages (everything else) */
$app->get('/{param:.*}', [ \Scat\Web\Page::class, 'page' ]);
if ($DEBUG) {
  $app->post('/~edit/{param:.*}', [ \Scat\Web\Page::class, 'savePage' ]);
}

$app->run();
