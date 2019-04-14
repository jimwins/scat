<?php
require 'vendor/autoload.php';

use \Psr\Http\Message\RequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

/* Some defaults */
error_reporting(E_ALL & ~E_NOTICE);
date_default_timezone_set($_ENV['PHP_TIMEZONE'] ?: $_ENV['TZ']);
bcscale(2);

$DEBUG= false;
$config= require $_ENV['SCAT_CONFIG'] ?: dirname(__FILE__).'/config.php';

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

  // Add our Twig extensions
  $view->addExtension(new \Scat\TwigExtension());

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

/* Info (DEBUG only) */
if ($DEBUG) {
  $app->get('/info',
            function (Request $req, Response $res, array $args) {
              ob_start();
              phpinfo();
              return $res->getBody()->write(ob_get_clean());
            })->setName('info');
}

/* Sales */
$app->group('/sale', function (Slim\App $app) {
  $app->get('',
            function (Request $req, Response $res, array $args) {
              return $res->withRedirect('/txns.php');
            });
  $app->get('/{id}',
            function (Request $req, Response $res, array $args) {
              return $res->withRedirect("/?id={$args['id']}");
            })->setName('sale');
});

/* Catalog */
$container['catalog']= function($c) {
  return new \Scat\CatalogService();
};
$container['search']= function($c) {
  return new \Scat\SearchService($c['settings']['search']);
};
$container['report']= function($c) {
  return new \Scat\ReportService($c['settings']['report']);
};
$container['phone']= function($c) {
  return new \Scat\PhoneService($c['settings']['phone']);
};

$app->group('/catalog', function (Slim\App $app) {
  $app->get('/search',
            function (Request $req, Response $res, array $args) {
              $q= trim($req->getParam('q'));

              $data= $this->search->search($q);

              $data['depts']= $this->catalog->getDepartments();
              $data['q']= $q;

              return $this->view->render($res, 'catalog-searchresults.html',
                                         $data);
            })->setName('catalog-search');

  $app->get('/brand-form',
            function (Request $req, Response $res, array $args) {
              $brand= $this->catalog->getBrandById($req->getParam('id'));
              return $this->view->render($res, 'dialog/brand-edit.html',
                                         [
                                           'brand' => $brand
                                         ]);
            });

  $app->post('/brand-form',
             function (Request $req, Response $res, array $args) {
               $brand= $this->catalog->getBrandById($req->getParam('id'));
               if (!$brand)
                 $brand= $this->catalog->createBrand();
               $brand->name= $req->getParam('name');
               $brand->slug= $req->getParam('slug');
               $brand->save();
               return $res->withJson($brand);
             });

  $app->get('/department-form',
            function (Request $req, Response $res, array $args) {
              $depts= $this->catalog->getDepartments();
              $dept= $this->catalog->getDepartmentById($req->getParam('id'));
              return $this->view->render($res, 'dialog/department-edit.html',
                                         [
                                           'depts' => $depts,
                                           'dept' => $dept
                                         ]);
            });

  $app->post('/department-form',
             function (Request $req, Response $res, array $args) {
               $dept= $this->catalog->getDepartmentById($req->getParam('id'));
               if (!$dept)
                 $dept= $this->catalog->createDepartment();
               $dept->name= $req->getParam('name');
               $dept->slug= $req->getParam('slug');
               $dept->parent_id= $req->getParam('parent_id');
               $dept->save();
               return $res->withJson($dept);
             });

  $app->get('/product-form',
            function (Request $req, Response $res, array $args) {
              $depts= $this->catalog->getDepartments();
              $product= $this->catalog->getProductById($req->getParam('id'));
              $brands= $this->catalog->getBrands();
              return $this->view->render($res, 'dialog/product-edit.html',
                                         [
                                           'depts' => $depts,
                                           'brands' => $brands,
                                           'product' => $product,
                                           'department_id' =>
                                             $req->getParam('department_id'),
                                         ]);
            });

  $app->post('/product-form',
             function (Request $req, Response $res, array $args) {
               $product= $this->catalog->getProductById($req->getParam('id'));
               if (!$product)
                 $product= $this->catalog->createProduct();
               foreach ($product->fields() as $field) {
                 $value= $req->getParam($field);
                 if (isset($value)) {
                   $product->set($field, $value);
                 }
               }
               $product->save();
               return $res->withJson($product);
             });

  $app->post('/product/add-image',
             function (Request $req, Response $res, array $args) {
               $product= $this->catalog->getProductById($req->getParam('id'));

               $url= $req->getParam('url');
               if ($url) {
                 $image= \Scat\Image::createFromUrl($url);
                 $product->addImage($image);
               } else {
                 foreach ($req->getUploadedFiles() as $file) {
                   $image= \Scat\Image::createFromFile($file->getStream(),
                                                       $file->getClientFilename());
                   $product->addImage($image);
                 }
               }

               return $res->withJson($product);
             });

  $app->get('/brand[/{brand}]',
            function (Request $req, Response $res, array $args) {
              $depts= $this->catalog->getDepartments();

              $brand= $args['brand'] ?
                $this->catalog->getBrandBySlug($args['brand']) : null;
              if ($args['brand'] && !$brand)
                throw new \Slim\Exception\NotFoundException($req, $res);

              if ($brand)
                $products= $brand->products()
                                 ->order_by_asc('name')
                                 ->find_many();

              $brands= $brand ? null : $this->catalog->getBrands();

              return $this->view->render($res, 'catalog-brand.html',
                                         [ 'depts' => $depts,
                                           'brands' => $brands,
                                           'brand' => $brand,
                                           'products' => $products ]);
            })->setName('catalog-brand');

  $app->get('[/{dept}[/{subdept}[/{product}[/{item}]]]]',
            function (Request $req, Response $res, array $args) {
            try {
              $depts= $this->catalog->getDepartments();
              $dept= $args['dept'] ?
                $this->catalog->getDepartmentBySlug($args['dept']):null;

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
                $subdept->products()
                        ->select('product.*')
                        ->left_outer_join('brand',
                                          array('product.brand_id', '=',
                                                 'brand.id'))
                        ->order_by_asc('brand.name')
                        ->order_by_asc('product.name')
                        ->find_many():null;

              $product= $args['product'] ?
                $subdept->products()
                        ->where('slug', $args['product'])
                        ->find_one():null;
              if ($args['product'] && !$product)
                throw new \Slim\Exception\NotFoundException($req, $res);

              $items= $product ?
                $product->items()
                        # A crude implementation of a numsort
                        ->order_by_expr('IF(CONVERT(variation, SIGNED),
                                            CONCAT(LPAD(CONVERT(variation,
                                                                SIGNED),
                                                        10, "0"),
                                                   variation),
                                            variation) ASC')
                        ->order_by_expr('minimum_quantity > 0 DESC')
                        ->order_by_asc('code')
                        ->find_many():null;

              if ($items) {
                $variations= array_unique(
                  array_map(function ($i) {
                    return $i->variation;
                  }, $items));
              }

              $item= $args['item'] ?
                $product->items()
                        ->where('code', $args['item'])
                        ->find_one():null;
              if ($args['item'] && !$item)
                throw new \Slim\Exception\NotFoundException($req, $res);

              $brands= $dept ? null : $this->catalog->getBrands();

              return $this->view->render($res, 'catalog-layout.html',
                                         [ 'brands' => $brands,
                                           'dept' => $dept,
                                           'depts' => $depts,
                                           'subdept' => $subdept,
                                           'subdepts' => $subdepts,
                                           'product' => $product,
                                           'products' => $products,
                                           'variations' => $variations,
                                           'item' => $item,
                                           'items' => $items ]);
             }
             catch (\Slim\Exception\NotFoundException $ex) {
               /* TODO figure out a way to not have to add/remove /catalog/ */
               $path= preg_replace('!/catalog/!', '',
                                   $req->getUri()->getPath());
               $re= $this->catalog->getRedirectFrom($path);

               if ($re) {
                 return $res->withRedirect('/catalog/' . $re->dest, 301);
               }

               throw $ex;
             }
            })->setName('catalog');
});

/* People */
$app->group('/person', function (Slim\App $app) {
  $app->get('',
            function (Request $req, Response $res, array $args) {
              return $res->withRedirect('/people.php');
            });
  $app->get('/{id}',
            function (Request $req, Response $res, array $args) {
              return $res->withRedirect("/person.php?id={$args['id']}");
            })->setName('person');
});

/* Clock */
$app->group('/clock', function (Slim\App $app) {
  $app->get('',
            function (Request $req, Response $res, array $args) {
              return $res->withRedirect('/clock.php');
            });
});

/* Reports */
$app->group('/report', function (Slim\App $app) {
  $app->get('/quick',
            function (Request $req, Response $res, array $args) {
              $data= $this->report->sales();
              return $this->view->render($res, 'dialog/report-quick.html',
                                         $data);
            });
  $app->get('/{name}',
            function (Request $req, Response $res, array $args) {
              return $res->withRedirect("/report-{$args['name']}.php");
            });
});

/* Notes */
$app->get('/notes',
          function (Request $req, Response $res, array $args) {
            $notes= \Model::factory('Note')
                      ->where('parent_id', 0)
                      ->where('todo', 1)
                      ->order_by_desc('id')
                      ->find_many();
            return $this->view->render($res, 'dialog/notes.html',
                                       [ 'notes' => $notes ]);
          });

/* Till */
$app->group('/till', function (Slim\App $app) {
  $app->get('',
            function (Request $req, Response $res, array $args) {
              return $res->withRedirect("/till.php");
            });
});

/* SMS TODO just testing right now */
$app->get('/sms',
            function (Request $req, Response $res, array $args) {
              $data= $this->phone->sendSMS($req->getParam('to'),
                                           $req->getParam('text'));
              return $res->withJson($data);
            });

$app->get('/dialog/{dialog}',
          function (Request $req, Response $res, array $args) {
            return $this->view->render($res, "dialog/{$args['dialog']}");
          });

$app->run();
