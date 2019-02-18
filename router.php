<?php
require 'vendor/autoload.php';

use \Psr\Http\Message\RequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

/* Some defaults */
error_reporting(E_ALL & ~E_NOTICE);
date_default_timezone_set($_ENV['PHP_TIMEZONE'] ?: $_ENV['TZ']);
bcscale(2);

$DEBUG= false;
require $_ENV['SCAT_CONFIG'] ?: dirname(__FILE__).'/config.php';

$config= [
  'displayErrorDetails' => $DEBUG,
  'addContentLengthHeader' => false,
  'Twig' => [
    'timezone' => $_ENV['PHP_TIMEZONE'] ?: $_ENV['TZ']
  ],
  'db' => [
    'host' => DB_SERVER,
    'user' => DB_USER,
    'password' => DB_PASSWORD,
    'dbname' => DB_SCHEMA
  ]
];

/* Configure Idiorm & Paris */
Model::$auto_prefix_models= '\\Scat\\';

ORM::configure('mysql:host=' . DB_SERVER . ';dbname=' . DB_SCHEMA . ';charset=utf8');
ORM::configure('username', DB_USER);
ORM::configure('password', DB_PASSWORD);
ORM::configure('logging', true);
ORM::configure('error_mode', PDO::ERRMODE_EXCEPTION);
Model::$short_table_names= true;

if ($DEBUG) {
  ORM::configure('logger', function ($log_string, $query_time) {
    error_log('ORM: "' . $log_string . '" in ' . $query_time);
  });
}

$app= new \Slim\App([ 'settings' => $config ]);

$container= $app->getContainer();

/* We use monolog for logging (but still just through PHP's log for now) */
$container['logger']= function($c) {
  $logger= new \Monolog\Logger('scat');
  $handler= new \Monolog\Handler\ErrorLogHandler();
  $logger->pushHandler($handler);
  return $logger;
};

/* HTTP cache */
$container['cache']= function () {
  return new \Slim\HttpCache\CacheProvider();
};

/* PDO */
$container['db']= function ($c) {
  $db= $c['settings']['db'];
  $pdo= new PDO('mysql:host=' . $db['host'] . ';dbname=' . $db['dbname'],
                $db['user'], $db['pass']);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  return $pdo;
};

/* Twig for templating */
$container['view']= function ($container) {
  $view= new \Slim\Views\Twig('ui', [
    'cache' => false /* No cache for now */
  ]);

  if (($tz= $container['settings']['Twig']['timezone'])) {
    $view->getEnvironment()
      ->getExtension('Twig_Extension_Core')
      ->setTimezone($tz);
  }

  // Instantiate and add Slim specific extension
  $router= $container->get('router');
  $uri= \Slim\Http\Uri::createFromEnvironment(
          new \Slim\Http\Environment($_SERVER));
  $view->addExtension(new \Slim\Views\TwigExtension($router, $uri));

  // Add the Markdown extension
  $engine= new Aptoma\Twig\Extension\MarkdownEngine\MichelfMarkdownEngine();
  $view->addExtension(new Aptoma\Twig\Extension\MarkdownExtension($engine));

  return $view;
};

/* Trim trailing slashes */
$app->add(function (Request $request, Response $response, callable $next) {
    $uri = $request->getUri();
    $path = $uri->getPath();
    if ($path != '/' && substr($path, -1) == '/') {
        // permanently redirect paths with a trailing slash
        // to their non-trailing counterpart
        $uri = $uri->withPath(substr($path, 0, -1));
        
        if($request->getMethod() == 'GET') {
            return $response->withRedirect((string)$uri, 301);
        }
        else {
            return $next($request->withUri($uri), $response);
        }
    }

    return $next($request, $response);
});

/* 404 */
$container['notFoundHandler']= function ($c) {
  return function ($req, $res) use ($c) {
    return $c->get('view')->render($res, '404.html')
      ->withStatus(404)
      ->withHeader('Content-Type', 'text/html');
  };
};

$container['catalog_service']= function($c) {
  return new \Scat\CatalogService();
};

/* Info (DEBUG only) */
if ($DEBUG) {
  $app->get('/info',
            function (Request $req, Response $res, array $args) {
              ob_start();
              phpinfo();
              return $res->getBody()->write(ob_get_clean());
            })->setName('info');
}

/* Catalog */
$app->group('/catalog', function (Slim\App $app) {
  $app->get('/search',
            function (Request $req, Response $res, array $args) {
              $depts= $this->catalog_service->getDepartments();

              return $this->view->render($res, 'catalog-searchresults.html',
                                         [ 'depts' => $depts ]);
            })->setName('catalog-search');

  $app->get('/brand[/{brand}]',
            function (Request $req, Response $res, array $args) {
              $depts= $this->catalog_service->getDepartments();

              $brand= $args['brand'] ?
                $this->catalog_service->getBrandBySlug($args['brand']) : null;
              if ($args['brand'] && !$brand)
                throw new \Slim\Exception\NotFoundException($req, $res);

              if ($brand)
                $products= $brand->products()
                                 ->order_by_asc('name')
                                 ->find_many();

              $brands= $brand ? null : $this->catalog_service->getBrands();


              return $this->view->render($res, 'catalog-brand.html',
                                         [ 'depts' => $depts,
                                           'brands' => $brands,
                                           'brand' => $brand,
                                           'products' => $products ]);
            })->setName('catalog-brand');

  $app->get('[/{dept}[/{subdept}[/{product}[/{item}]]]]',
            function (Request $req, Response $res, array $args) {
              $depts= $this->catalog_service->getDepartments();
              $dept= $args['dept'] ?
                $this->catalog_service->getDepartmentBySlug($args['dept']):null;

              if ($args['dept'] && !$dept)
                throw new \Slim\Exception\NotFoundException($req, $res);

              $subdepts= $dept ?
                $dept->departments()->order_by_asc('name')->find_many():null;

              $subdept= $args['subdept'] ?
                $dept->departments()
                     ->where('slug', $args['subdept'])
                     ->find_one():null;
              if ($args['subdept'] && !$subdept)
                throw new \Slim\Exception\NotFoundException($req, $res);

              $products= $subdept ?
                $subdept->products()->order_by_asc('name')->find_many():null;

              $product= $args['product'] ?
                $subdept->products()
                        ->where('slug', $args['product'])
                        ->find_one():null;
              if ($args['product'] && !$product)
                throw new \Slim\Exception\NotFoundException($req, $res);

              $items= $product ?
                $product->items()
                        ->order_by_asc('name')
                        ->find_many():null;

              $item= $args['item'] ?
                $product->items()
                        ->where('code', $args['item'])
                        ->find_one():null;
              if ($args['item'] && !$item)
                throw new \Slim\Exception\NotFoundException($req, $res);

              return $this->view->render($res, 'catalog-layout.html',
                                         [ 'dept' => $dept,
                                           'depts' => $depts,
                                           'subdept' => $subdept,
                                           'subdepts' => $subdepts,
                                           'product' => $product,
                                           'products' => $products,
                                           'item' => $item,
                                           'items' => $items ]);
            })->setName('catalog');
});

$app->run();