<?php
require '../vendor/autoload.php';

use \Psr\Http\Message\RequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Respect\Validation\Validator as v;
use \DavidePastore\Slim\Validation\Validation as Validation;

/* Some defaults */
error_reporting(E_ALL & ~E_NOTICE);
date_default_timezone_set($_ENV['PHP_TIMEZONE'] ?: $_ENV['TZ']);
bcscale(2);

$DEBUG= $ORM_DEBUG= false;
$config= require $_ENV['SCAT_CONFIG'] ?: dirname(__FILE__).'/../config.php';

/* Configure Idiorm & Paris */
Model::$auto_prefix_models= '\\Scat\\Model\\';

ORM::configure('mysql:host=' . DB_SERVER . ';dbname=' . DB_SCHEMA . ';charset=utf8');
ORM::configure('username', DB_USER);
ORM::configure('password', DB_PASSWORD);
ORM::configure('logging', true);
ORM::configure('error_mode', PDO::ERRMODE_EXCEPTION);
Model::$short_table_names= true;

if ($DEBUG || $ORM_DEBUG) {
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
  $view= new \Slim\Views\Twig('../ui', [
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

/* Hook up our services */
$container['catalog']= function($c) {
  return new \Scat\Service\Catalog();
};
$container['search']= function($c) {
  return new \Scat\Service\Search($c['settings']['search']);
};
$container['report']= function($c) {
  return new \Scat\Service\Report($c['settings']['report']);
};
$container['phone']= function($c) {
  return new \Scat\Service\Phone($c['settings']['phone']);
};
$container['push']= function($c) {
  return new \Scat\Service\Push($c['settings']['push']);
};
$container['tax']= function($c) {
  return new \Scat\Service\Tax($c['settings']['tax']);
};
$container['giftcard']= function($c) {
  return new \Scat\Service\Giftcard($c['settings']['giftcard']);
};
$container['txn']= function($c) {
  return new \Scat\Service\Txn();
};
$container['printer']= function($c) {
  return new \Scat\Service\Printer($c->view);
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

/* ROUTES */

$app->get('/',
          function (Request $req, Response $res, array $args) {
            $q= ($req->getQueryParams() ?
                  '?' . http_build_query($req->getQueryPArams()) :
                  '');
            return $res->withRedirect("/sale/new" . $q);
          })->setName('home');

/* Sales */
$app->group('/sale', function (Slim\App $app) {
  $app->get('',
            function (Request $req, Response $res, array $args) {
              $page= (int)$req->getParam('page');
              $limit= 25;
              $txns= $this->txn->find('customer', $page, $limit);
              return $this->view->render($res, 'txn/index.html', [
                'type' => 'customer',
                'txns' => $txns,
                'page' => $page,
                'limit' => $limit,
              ]);
            });
  $app->get('/new',
            function (Request $req, Response $res, array $args) {
              ob_start();
              include "../old-index.php";
              $content= ob_get_clean();
              return $this->view->render($res, 'sale/old-new.html', [
                'title' => $GLOBALS['title'],
                'content' => $content,
              ]);
            });
  $app->get('/{id:[0-9]+}',
            function (Request $req, Response $res, array $args) {
              return $res->withRedirect("/?id={$args['id']}");
            })->setName('sale');
  $app->get('/email-invoice-form',
            function (Request $req, Response $res, array $args) {
              $txn= $this->txn->fetchById($req->getParam('id'));
              return $this->view->render($res, 'dialog/email-invoice.html',
                                         [
                                           'txn' => $txn
                                         ]);
            });
  $app->post('/email-invoice',
            function (Request $req, Response $res, array $args) {
              $txn= $this->txn->fetchById($req->getParam('id'));
              $name= $req->getParam('name');
              $email= $req->getParam('email');
              $subject= trim($req->getParam('subject'));
              error_log("Sending {$txn->id} to $email");

              $attachments= [];
              if ($req->getParam('include_details')) {
                $pdf= $txn->getInvoicePDF();
                $attachments[]= [
                  'name' => (($txn->type == 'vendor') ? 'PO' : 'I') . $txn->formatted_number() . '.pdf',
                  'type' => 'application/pdf',
                  'data' => base64_encode($pdf->Output('', 'S')),
                ];
              }

              // TODO push email sending into a service
              $httpClient= new \Http\Adapter\Guzzle6\Client(new \GuzzleHttp\Client());
              $sparky= new \SparkPost\SparkPost($httpClient,
                                                [ 'key' => SPARKPOST_KEY ]);
	      $promise= $sparky->transmissions->post([
		'content' => [
                  'html' => $this->view->fetch('email/invoice.html',
                                               [
                                                 'txn' => $txn,
                                                 'subject' => $subject,
                                                 'content' =>
                                                   $req->getParam('content'),
                                               ]),
                  'subject' => $subject,
                  'from' => array('name' => "Raw Materials Art Supplies",
                                  'email' => OUTGOING_EMAIL_ADDRESS),
                  'attachments' => $attachments,
                  'inline_images' => [
                    [
                      'name' => 'logo.png',
                      'type' => 'image/png',
                      'data' => base64_encode(
                                 file_get_contents('../ui/logo.png')),
                    ],
                  ],
                ],
		'recipients' => [
		  [
		    'address' => [
		      'name' => $name,
		      'email' => $email,
		    ],
		  ],
		  [
		    // BCC ourselves
		    'address' => [
		      'header_to' => $name,
		      'email' => OUTGOING_EMAIL_ADDRESS,
		    ],
		  ],
		],
		'options' => [
		  'inlineCss' => true,
		],
	      ]);

	      try {
		$response= $promise->wait();
                return $res->withJson([ "message" => "Email sent." ]);
	      } catch (\Exception $e) {
		error_log(sprintf("SparkPost failure: %s (%s)",
				  $e->getMessage(), $e->getCode()));
                return $res->withJson([
                  "error" =>
                    sprintf("SparkPost failure: %s (%s)",
                            $e->getMessage(), $e->getCode())
                ], 500);
	      }
            });
});

/* Sales */
$app->group('/purchase', function (Slim\App $app) {
  $app->get('',
            function (Request $req, Response $res, array $args) {
              $page= (int)$req->getParam('page');
              $limit= 25;
              $txns= $this->txn->find('vendor', $page, $limit);
              return $this->view->render($res, 'txn/index.html', [
                'type' => 'vendor',
                'txns' => $txns,
                'page' => $page,
                'limit' => $limit,
              ]);
            });
  $app->get('/reorder',
            function (Request $req, Response $res, array $args) {

/* XXX This needs to move into a service or something. */
$extra= $extra_field= $extra_field_name= '';

$all= (int)$req->getParam('all');

$vendor_code= "NULL";
$vendor= (int)$req->getParam('vendor');
if ($vendor > 0) {
  $vendor_code= "(SELECT code FROM vendor_item WHERE vendor_id = $vendor AND item_id = item.id AND vendor_item.active LIMIT 1)";
  $extra= "AND EXISTS (SELECT id
                         FROM vendor_item
                        WHERE vendor_id = $vendor
                          AND item_id = item.id
                          AND vendor_item.active)";
  $extra_field= "(SELECT MIN(IF(promo_quantity, promo_quantity,
                                purchase_quantity))
                    FROM vendor_item
                   WHERE item_id = item.id
                     AND vendor_id = $vendor
                     AND vendor_item.active)
                  AS minimum_order_quantity,
                 (SELECT MIN(IF(promo_price, promo_price, net_price))
                    FROM vendor_item
                    JOIN person ON vendor_item.vendor_id = person.id
                  WHERE item_id = item.id
                    AND vendor_id = $vendor
                    AND vendor_item.active)
                  AS cost,
                 (SELECT MIN(IF(promo_price, promo_price, net_price)
                             * ((100 - vendor_rebate) / 100))
                    FROM vendor_item
                    JOIN person ON vendor_item.vendor_id = person.id
                  WHERE item_id = item.id
                    AND vendor_id = $vendor
                    AND vendor_item.active) -
                 (SELECT MIN(IF(promo_price, promo_price, net_price)
                             * ((100 - vendor_rebate) / 100))
                    FROM vendor_item
                    JOIN person ON vendor_item.vendor_id = person.id
                   WHERE item_id = item.id
                     AND NOT special_order
                     AND vendor_id != $vendor
                     AND vendor_item.active)
                 cheapest, ";
  $extra_field_name= "minimum_order_quantity, cheapest, cost,";
} else if ($vendor < 0) {
  // No vendor
  $extra= "AND NOT EXISTS (SELECT id
                             FROM vendor_item
                            WHERE item_id = item.id
                              AND vendor_item.active)";
}

$code= trim($req->getParam('code'));
if ($code) {
  $extra.= " AND code LIKE " . ORM::get_db()->quote($code.'%');
}
$criteria= ($all ? '1=1'
                 : '(ordered IS NULL OR NOT ordered)
                    AND IFNULL(stock, 0) < minimum_quantity');
$q= "SELECT id, code, vendor_code, name, stock,
            minimum_quantity, last3months,
            $extra_field_name
            order_quantity
       FROM (SELECT item.id,
                    item.code,
                    $vendor_code AS vendor_code,
                    name,
                    SUM(allocated) stock,
                    minimum_quantity,
                    (SELECT -1 * SUM(allocated)
                       FROM txn_line JOIN txn ON (txn_id = txn.id)
                      WHERE type = 'customer'
                        AND txn_line.item_id = item.id
                        AND filled > NOW() - INTERVAL 3 MONTH)
                    AS last3months,
                    (SELECT SUM(ordered - allocated)
                       FROM txn_line JOIN txn ON (txn_id = txn.id)
                      WHERE type = 'vendor'
                        AND txn_line.item_id = item.id
                        AND created > NOW() - INTERVAL 12 MONTH)
                    AS ordered,
                    $extra_field
                    IF(minimum_quantity > minimum_quantity - SUM(allocated),
                       minimum_quantity,
                       minimum_quantity - IFNULL(SUM(allocated), 0))
                      AS order_quantity
               FROM item
               LEFT JOIN txn_line ON (item_id = item.id)
              WHERE purchase_quantity
                AND item.active AND NOT item.deleted
                $extra
              GROUP BY item.id
              ORDER BY code) t
       WHERE $criteria
       ORDER BY code
      ";

$items= ORM::for_table('item')->raw_query($q)->find_many();

              return $this->view->render($res, 'purchase/reorder.html', [
                'items' => $items,
                'all' => $all,
                'code' => $code,
                'vendor' => $vendor,
                'person' => \Model::factory('Person')->find_one($vendor)
              ]);
            })->setName('sale');
  $app->get('/create',
             function (Request $req, Response $res, array $args) {
               $vendor_id= $req->getParam('vendor');

               if (!$vendor_id) {
                 throw new \Exception("No vendor specified.");
               }

               $purchase= $this->txn->create([
                 'type' => 'vendor',
                 'person_id' => $vendor_id,
                 'tax_rate' => 0,
               ]);
               $path= $this->router->pathFor('purchase', [
                 'id' => $purchase->id
               ]);
               return $res->withRedirect($path);
             });
  $app->post('/reorder',
             function (Request $req, Response $res, array $args) {
               $vendor_id= $req->getParam('vendor');

               if (!$vendor_id) {
                 throw new \Exception("No vendor specified.");
               }

               \ORM::get_db()->beginTransaction();

               $txn_id= $req->getParam('txn_id');
               if ($txn_id) {
                 $purchase= $this->txn->fetchById($txn_id);
                 if (!$txn_id) {
                   throw new \Exception("Unable to find transaction.");
                 }
               } else {
                 $purchase= $this->txn->create([
                   'type' => 'vendor',
                   'person_id' => $vendor_id,
                   'tax_rate' => 0,
                 ]);
               }

               $items= $req->getParam('item');
               foreach ($items as $item_id => $quantity) {
                 if (!$quantity) {
                   continue;
                 }

                 $vendor_items=
                   \Scat\Model\VendorItem::findByItemIdForVendor($item_id,
                                                           $vendor_id);

                 // Get the lowest available price for our quantity
                 $price= 0;
                 foreach ($vendor_items as $item) {
                   $contender= ($item->promo_price > 0.00 &&
                                $quantity >= $item->promo_quantity) ?
                               $item->promo_price :
                               (($quantity >= $item->purchase_quantity) ?
                                $item->net_price :
                                0);
                   $price= ($price && $price < $contender) ?
                           $price :
                           $contender;
                 }

                 if (!$price) {
                   error_log("Failed to get price for $item_id");
                 }

                 $item= $purchase->items()->create();
                 $item->txn_id= $purchase->id;
                 $item->item_id= $item_id;
                 $item->ordered= $quantity;
                 $item->retail_price= $price;
                 $item->save();
               }

               \ORM::get_db()->commit();

               $path= $this->router->pathFor('purchase', [
                 'id' => $purchase->id
               ]);
               return $res->withRedirect($path);
             });
  $app->get('/{id}',
            function (Request $req, Response $res, array $args) {
              return $res->withRedirect("/?id={$args['id']}");
            })->setName('purchase');
});

/* Catalog */
$app->group('/catalog', function (Slim\App $app) {
  $app->get('/search',
            function (Request $req, Response $res, array $args) {
              $q= trim($req->getParam('q'));

              $data= $this->search->search($q);

              $data['depts']= $this->catalog->getDepartments();
              $data['q']= $q;

              return $this->view->render($res, 'catalog/searchresults.html',
                                         $data);
            })->setName('catalog-search');

  $app->get('/~reindex', function (Request $req, Response $res, array $args) {
    $this->search->flush();

    $rows= 0;
    foreach ($this->catalog->getProducts() as $product) {
      $rows+= $this->search->indexProduct($product);
    }

    return $res->getBody()->write("Indexed $rows rows.");
  });

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
               $brand->description= $req->getParam('description');
               $brand->active= (int)$req->getParam('active');
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
               $dept->description= $req->getParam('description');
               $dept->active= (int)$req->getParam('active');
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
               if (!$product) {
                 if (!$req->getParam('slug')) {
                   throw \Exception("Must specify a slug.");
                 }
                 $product= $this->catalog->createProduct();
               }
               foreach ($product->fields() as $field) {
                 $value= $req->getParam($field);
                 if (isset($value)) {
                   $product->set($field, $value);
                 }
               }
               $product->save();
               $this->search->indexProduct($product);
               return $res->withJson($product);
             });

  $app->post('/product/add-image',
             function (Request $req, Response $res, array $args) {
               $product= $this->catalog->getProductById($req->getParam('id'));

               $url= $req->getParam('url');
               if ($url) {
                 $image= \Scat\Model\Image::createFromUrl($url);
                 $product->addImage($image);
               } else {
                 foreach ($req->getUploadedFiles() as $file) {
                   $image= \Scat\Model\Image::createFromStream($file->getStream(),
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
                                 ->where('product.active', 1)
                                 ->find_many();

              $brands= $brand ? null : $this->catalog->getBrands();

              return $this->view->render($res, 'catalog/brand.html',
                                         [ 'depts' => $depts,
                                           'brands' => $brands,
                                           'brand' => $brand,
                                           'products' => $products ]);
            })->setName('catalog-brand');

  $app->get('/item/{code:.*}',
            function (Request $req, Response $res, array $args) {
              $item= $this->catalog->getItemByCode($args['code']);
              if (!$item)
               throw new \Slim\Exception\NotFoundException($req, $res);
              return $this->view->render($res, 'catalog/item.html', [
                                          'item' => $item,
                                         ]);
            })->setName('catalog-item');

  $app->post('/item/{code:.*}/~print-label',
            function (Request $req, Response $res, array $args) {
              $item= $this->catalog->getItemByCode($args['code']);
              if (!$item)
               throw new \Slim\Exception\NotFoundException($req, $res);

              $body= $res->getBody();
              $body->write($item->getPDF($req->getParams()));
              return $res->withHeader("Content-type", "application/pdf");
            });

  $app->post('/item/{code:.*}/~add-barcode',
            function (Request $req, Response $res, array $args) {
              $item= $this->catalog->getItemByCode($args['code']);
              if (!$item)
               throw new \Slim\Exception\NotFoundException($req, $res);

              $barcode= $item->barcodes()->create();
              $barcode->item_id= $item->id;
              $barcode->code= trim($req->getParam('barcode'));
              $barcode->quantity= 1;
              $barcode->save();

              return $res->withJson($item);
            });

  $app->post('/item/{code:.*}/~edit-barcode',
            function (Request $req, Response $res, array $args) {
              $item= $this->catalog->getItemByCode($args['code']);
              if (!$item)
               throw new \Slim\Exception\NotFoundException($req, $res);

              $barcode= $item->barcodes()
                          ->where('code', $req->getParam('pk'))
                          ->find_one();

              if (!$barcode)
               throw new \Slim\Exception\NotFoundException($req, $res);

              if ($req->getParam('name') == 'quantity') {
                $barcode->quantity= trim($req->getParam('value'));
              }

              if ($req->getParam('name') == 'code') {
                $barcode->code= trim($req->getParam('value'));
              }

              $barcode->save();

              return $res->withJson($item);
            });

  $app->post('/item/{code:.*}/~remove-barcode',
            function (Request $req, Response $res, array $args) {
              $item= $this->catalog->getItemByCode($args['code']);
              if (!$item)
               throw new \Slim\Exception\NotFoundException($req, $res);

              $barcode= $item->barcodes()
                          ->where('code', $req->getParam('pk'))
                          ->find_one();

              if (!$barcode)
               throw new \Slim\Exception\NotFoundException($req, $res);

              $barcode->delete();

              return $res->withJson($item);
            });

  $app->post('/item/{code:.*}/~find-vendor-items',
            function (Request $req, Response $res, array $args) {
              $item= $this->catalog->getItemByCode($args['code']);
              if (!$item)
               throw new \Slim\Exception\NotFoundException($req, $res);

              $item->findVendorItems();

              return $res->withJson($item);
            });
  $app->post('/item/{code:.*}/~unlink-vendor-item',
            function (Request $req, Response $res, array $args) {
              $item= $this->catalog->getItemByCode($args['code']);
              if (!$item)
               throw new \Slim\Exception\NotFoundException($req, $res);
              $vi= $item->vendor_items()
                        ->where('id', $req->getParam('id'))
                        ->find_one();
              if (!$vi)
               throw new \Slim\Exception\NotFoundException($req, $res);

              $vi->item_id= 0;
              $vi->save();

              return $res->withJson($item);
            });
  $app->post('/item/{code:.*}/~check-vendor-stock',
            function (Request $req, Response $res, array $args) {
              $item= $this->catalog->getItemByCode($args['code']);
              if (!$item)
               throw new \Slim\Exception\NotFoundException($req, $res);
              $vi= $item->vendor_items()
                        ->where('id', $req->getParam('id'))
                        ->find_one();
              if (!$vi)
               throw new \Slim\Exception\NotFoundException($req, $res);

              return $res->withJson($vi->checkVendorStock());
            });

  $app->get('/item-add-form',
            function (Request $req, Response $res, array $args) {
              return $this->view->render($res, 'dialog/item-add.html',
                                         [
                                           'product_id' =>
                                             $req->getParam('product_id'),
                                         ]);
            });

  $app->get('/vendor-item-form',
            function (Request $req, Response $res, array $args) {
              $item= $this->catalog->getItemById($req->getParam('item'));
              $vendor_item= $this->catalog->getVendorItemById($req->getParam('id'));
              return $this->view->render($res, 'dialog/vendor-item-edit.html',
                                         [
                                           'item' => $item,
                                           'vendor_item' => $vendor_item,
                                         ]);
            });
  $app->post('/~update-vendor-item',
             function (Request $req, Response $res, array $args) {
               $vi= $this->catalog->getVendorItemById($req->getParam('id'));
               if (!$vi) {
                 $vi= $this->catalog->createVendorItem();
               }
               foreach ($vi->fields() as $field) {
                 $value= $req->getParam($field);
                 if (isset($value)) {
                   $vi->set($field, $value);
                 }
               }
               $vi->save();
               return $res->withJson($vi);
             });


  $app->post('/item-update',
             function (Request $req, Response $res, array $args) {
               $id= $req->getParam('pk');
               $name= $req->getParam('name');
               $value= $req->getParam('value');
               $item= $this->catalog->getItemById($id);
               if (!$item)
                 throw new \Slim\Exception\NotFoundException($req, $res);

               $item->setProperty($name, $value);
               $item->save();

               return $res->withJson([
                 'item' => $item,
                 'newValue' => $item->$name,
                 'replaceRow' => $this->view->fetch('catalog/item-row.twig', [
                                  'i' => $item,
                                  'variations' => $req->getParam('variations'),
                                  'product' => $req->getParam('product')
                                ])
               ]);
             });
  // XXX used by old-report/report-price-change
  $app->post('/item-reprice',
             function (Request $req, Response $res, array $args) {
               $id= $req->getParam('id');
               $retail_price= $req->getParam('retail_price');
               $discount= $req->getParam('discount');
               $item= $this->catalog->getItemById($id);
               if (!$item)
                 throw new \Slim\Exception\NotFoundException($req, $res);

               $item->setProperty('retail_price', $retail_price);
               $item->setProperty('discount', $discount);
               $item->save();

               return $res->withJson([ 'item' => $item ]);
             });


  $app->post('/bulk-update',
             function (Request $req, Response $res, array $args) {
               $items= $req->getParam('items');

               if (!preg_match('/^(?:\d+)(?:,\d+)*$/', $items)) {
                 throw new \Exception("Invalid items specified.");
               }

               foreach (explode(',', $items) as $id) {
                 $item= \Model::factory('Item')->find_one($id);
                 if (!$item)
                   throw new \Slim\Exception\NotFoundException($req, $res);

                 foreach ([ 'brand_id','product_id','name','short_name','variation','retail_price','discount','minimum_quantity','purchase_quantity','dimensions','weight','prop65','hazmat','oversized','active' ] as $key) {
                   $value= $req->getParam($key);
                   if (strlen($value)) {
                     $item->setProperty($key, $value);
                   }
                 }

                 $item->save();
               }

               return $res->withJson([ 'message' => 'Okay.' ]);
             });

  $app->get('/whats-new',
            function (Request $req, Response $res, array $args) {
              $limit= (int)$req->getParam('limit');
              if (!$limit) $limit= 12;
              $products= $this->catalog->getNewProducts($limit);
              $depts= $this->catalog->getDepartments();

              return $this->view->render($res, 'catalog/whatsnew.html',
                                         [
                                           'products' => $products,
                                           'depts' => $depts,
                                         ]);
            })->setName('catalog-whats-new');

  $app->get('/price-overrides',
             function (Request $req, Response $res, array $args) {
               $price_overrides= \Model::factory('PriceOverride')
                                  ->order_by_asc('pattern')
                                  ->order_by_asc('minimum_quantity')
                                  ->find_many();

               return $this->view->render($res, 'catalog/price-overrides.html',[
                'price_overrides' => $price_overrides,
               ]);
             })->setName('catalog-price-overrides');
  $app->post('/price-overrides/~delete',
             function (Request $req, Response $res, array $args) {
               $override= \Model::factory('PriceOverride')
                            ->find_one($req->getParam('id'));
               if (!$override) {
                 throw new \Slim\Exception\NotFoundException($req, $res);
               }
               $override->delete();
               return $res->withJson([ 'message' => 'Success!' ]);
             });
  $app->post('/price-overrides/~edit',
             function (Request $req, Response $res, array $args) {
               $override= \Model::factory('PriceOverride')
                            ->find_one($req->getParam('id'));
               if (!$override) {
                 $override= \Model::factory('PriceOverride')->create();
               }
               $override->pattern_type= $req->getParam('pattern_type');
               $override->pattern= $req->getParam('pattern');
               $override->minimum_quantity= $req->getParam('minimum_quantity');
               $override->setDiscount($req->getParam('discount'));
               $override->expires= $req->getParam('expires') ?: null;
               $override->in_stock= $req->getParam('in_stock');
               $override->save();
               return $res->withJson($override);
             });
  $app->get('/price-override-form',
            function (Request $req, Response $res, array $args) {
              $override= \Model::factory('PriceOverride')
                           ->find_one($req->getParam('id'));
              return $this->view->render($res,
                                         'dialog/price-override-edit.html',
                                         [ 'override' => $override ]);
            });

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
                $dept->departments(false)
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
                $subdept->products(false)
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

              return $this->view->render($res, 'catalog/layout.html',
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

  $app->post('/~add-item',
             function (Request $req, Response $res, array $args) {
               \ORM::get_db()->beginTransaction();

               $item= $this->catalog->createItem();

               $item->code= trim($req->getParam('code'));
               $item->name= trim($req->getParam('name'));
               $item->retail_price= $req->getParam('retail_price');

               if ($req->getParam('product_id')) {
                 $item->product_id= $req->getParam('product_id');
               }

               if (($id= $req->getParam('vendor_item'))) {
                 $vendor_item= $this->catalog->getVendorItemById($id);
                 if ($vendor_item) {
                   $item->purchase_quantity= $vendor_item->purchase_quantity;
                   $item->length= $vendor_item->length;
                   $item->width= $vendor_item->width;
                   $item->height= $vendor_item->height;
                   $item->weight= $vendor_item->weight;
                   $item->prop65= $vendor_item->prop65;
                   $item->hazmat= $vendor_item->hazmat;
                   $item->oversized= $vendor_item->oversized;
                 } else {
                   // Not a hard error, but log it.
                   error_log("Unable to find vendor_item $id");
                 }
               }

               $item->save();

               if ($vendor_item) {
                 if ($vendor_item->barcode) {
                   $barcode= $item->barcodes()->create();
                   $barcode->code= $vendor_item->barcode;
                   $barcode->item_id= $item->id();
                   $barcode->save();
                 }
                 if (!$vendor_item->item) {
                   $vendor_item->item= $item->id();
                   $vendor_item->save();
                 }
               }

               \ORM::get_db()->commit();

               return $res->withJson($item);
             });
  $app->post('/~vendor-lookup',
             function (Request $req, Response $res, array $args) {
               $item=
                 $this->catalog->getVendorItemByCode($req->getParam('code'));
               return $res->withJson($item);
             });
});

/* Custom */
$app->get('/custom',
          function (Request $req, Response $res, array $args) {
            return $this->view->render($res, 'custom/index.html');
          });

/* People */
$app->group('/person', function (Slim\App $app) {
  $app->get('',
            function (Request $req, Response $res, array $args) {
              // TODO most recent customers? vendors?
              return $this->view->render($res, 'person/index.html');
            });
  $app->get('/search',
            function (Request $req, Response $res, array $args) {
              $select2= $req->getParam('_type') == 'query';
              $q= trim($req->getParam('q'));

              $people= \Scat\Model\Person::find($q);

              if ($select2) {
                return $res->withJson($people);
              }

              return $this->view->render($res, 'person/index.html',
                                         [ 'people' => $people, 'q' => $q ]);
            })->setName('person-search');
  $app->get('/{id:[0-9]+}',
            function (Request $req, Response $res, array $args) {
              $person= \Model::factory('Person')->find_one($args['id']);
              $page= (int)$req->getParam('page');
              $limit= 25;
              return $this->view->render($res, 'person/index.html', [
                'person' => $person,
                'page' => $page,
                'limit' => $limit,
              ]);
            })->setName('person');
  $app->get('/{id:[0-9]+}/items',
            function (Request $req, Response $res, array $args) {
              $person= \Model::factory('Person')->find_one($args['id']);
              $page= (int)$req->getParam('page');
              if ($person->role != 'vendor') {
                throw new \Exception("That person is not a vendor.");
              }
              $limit= 25;
              $q= $req->getParam('q');
              $items= \Scat\Model\VendorItem::search($person->id, $q);
              $items= $items->select_expr('COUNT(*) OVER()', 'total')
                            ->limit($limit)->offset($page * $limit);
              $items= $items->find_many();
              return $this->view->render($res, 'person/items.html', [
                                           'person' => $person,
                                           'items' => $items,
                                           'q' => $q,
                                           'page' => $page,
                                           'limit' => $limit,
                                           'page_size' => $page_size,
                                          ]);
            })->setName('vendor-items');
  $app->post('/update',
             function (Request $req, Response $res, array $args) {
               $id= $req->getParam('pk');
               $name= $req->getParam('name');
               $value= $req->getParam('value');
               $person= \Model::factory('Person')->find_one($id);
               if (!$person)
                 throw new \Slim\Exception\NotFoundException($req, $res);

               $person->setProperty($name, $value);
               $person->save();

               return $res->withJson([
                 'person' => $person,
               ]);
             });
  $app->post('/{id:[0-9]+}/upload-items',
             function (Request $req, Response $res, array $args) {
               $person= \Model::factory('Person')->find_one($args['id']);
               if (!$person)
                 throw new \Slim\Exception\NotFoundException($req, $res);

               $details= [];
               foreach ($req->getUploadedFiles() as $file) {
                 $details[]= $person->loadVendorData($file);
               }

               return $res->withJson([
                 'details' => $details
               ]);
             });
  $app->get('/{id:[0-9]+}/set-role',
             function (Request $req, Response $res, array $args) {
               $person= \Model::factory('Person')->find_one($args['id']);
               if (!$person)
                 throw new \Slim\Exception\NotFoundException($req, $res);
               $role= $req->getParam('role');
               $person->role= $role;
               $person->save();
               $path= $this->router->pathFor('person', [ 'id' => $person->id ]);
               return $res->withRedirect($path);
             });
});

/* Clock */
$app->group('/clock', function (Slim\App $app) {
  $app->get('',
            function (Request $req, Response $res, array $args) {
              $people= \Model::factory('Person')
                ->select('*')
                ->where('role', 'employee')
                ->order_by_asc('name')
                ->find_many();

              if (($block= $req->getParam('block'))) {
                $out= $this->view->fetchBlock('clock/index.html', $block, [
                                               'people' => $people,
                                             ]);
                return $res->getBody()->write($out);
              } else {
                return $this->view->render($res, 'clock/index.html', [
                                             'people' => $people,
                                            ]);
              }
            });
  $app->post('/~punch',
            function (Request $req, Response $res, array $args) {
              $id= $req->getParam('id');
              $person= \Model::factory('Person')->find_one($id);
              return $res->withJson($person->punch());
            });
});

/* Gift Cards */
$app->group('/gift-card', function (Slim\App $app) {
  $app->get('',
            function (Request $req, Response $res, array $args) {
              $page_size= 25;

              $cards= \Model::factory('Giftcard')
                        ->select('*')
                        ->select_expr('COUNT(*) OVER()', 'total')
                        ->order_by_desc('id')
                        ->limit($page_size)->offset($page * $page_size)
                        ->where('active', 1)
                        ->find_many();

              return $this->view->render($res, 'gift-card/index.html', [
                                           'cards' => $cards,
                                           'error' => $req->getParam('error'),
                                          ]);
            });

  $app->get('/lookup',
            function (Request $req, Response $res, array $args) {
              $card= $req->getParam('card');
              $card= preg_replace('/^RAW-/', '', $card);
              $id= substr($card, 0, 7);
              $pin= substr($card, -4);
              $card= \Model::factory('Giftcard')
                      ->where('id', $id)
                      ->where('pin', $pin)
                      ->find_one();

              if ($card) {
                return $res->withRedirect("/gift-card/" . $card->card());
              } else {
                return $res->withRedirect("/gift-card?error=not-found");
              }
            });

  $app->post('/create',
            function (Request $req, Response $res, array $args) {
              $expires= $req->getParam('expires');
              $txn_id= $req->getParam('txn_id');
              $balance= $req->getParam('balance');

              \ORM::get_db()->beginTransaction();

              $card= Model::factory('Giftcard')->create();

              $card->set_expr('pin', 'SUBSTR(RAND(), 5, 4)');
              if ($expires) {
                $card->expires= $expires . ' 23:59:59';
              }
              $card->active= 1;

              $card->save();

              /* Reload the card to make sure we have calculated values */
              $card= \Model::factory('Giftcard')->find_one($card->id);

              if ($balance) {
                $txn= $card->txns()->create();
                $txn->amount= $balance;
                $txn->card_id= $card->id;
                if ($txn_id) $txn->txn_id= $txn_id;
                $txn->save();
              }

              \ORM::get_db()->commit();

              return $res->withJson($card);
            });

  $app->get('/{card:[0-9]+}',
            function (Request $req, Response $res, array $args) {
              $id= substr($args['card'], 0, 7);
              $pin= substr($args['card'], -4);
              $card= \Model::factory('Giftcard')
                      ->where('id', $id)
                      ->where('pin', $pin)
                      ->find_one();

              return $this->view->render($res, 'gift-card/card.html', [
                                           'card' => $card,
                                          ]);
            });

  $app->get('/{card:[0-9]+}/print',
            function (Request $req, Response $res, array $args) {
              $id= substr($args['card'], 0, 7);
              $pin= substr($args['card'], -4);
              $card= \Model::factory('Giftcard')
                      ->where('id', $id)
                      ->where('pin', $pin)
                      ->find_one();

              $body= $res->getBody();
              $body->write($card->getPDF());
              return $res->withHeader("Content-type", "application/pdf");
            });

  $app->get('/{card:[0-9]+}/email-form',
            function (Request $req, Response $res, array $args) {
              $txn= $this->txn->fetchById($req->getParam('id'));
              return $this->view->render($res, 'dialog/email-gift-card.html',
                                         $args);
            });
  $app->post('/{card:[0-9]+}/email',
            function (Request $req, Response $res, array $args) {
              $id= substr($args['card'], 0, 7);
              $pin= substr($args['card'], -4);
              $card= \Model::factory('Giftcard')
                      ->where('id', $id)
                      ->where('pin', $pin)
                      ->find_one();

              $email_body= $this->view->fetch('email/gift-card.html',
                                              $req->getParams());
              $subject= $this->view->fetchBlock('email/gift-card.html',
                                                'title',
                                                $req->getParams());

              $giftcard_pdf= $card->getPDF();

              // XXX fix hardcoded name
              $from= $from_name ? "$from_name via Raw Materials Art Supplies"
                                : "Raw Materials Art Supplies";
              $to_name= $req->getParam('to_name');
              $to_email= $req->getParam('to_email');

              $httpClient= new \Http\Adapter\Guzzle6\Client(new \GuzzleHttp\Client());
              $sparky= new \SparkPost\SparkPost($httpClient,
                                                [ 'key' => SPARKPOST_KEY ]);

              $promise= $sparky->transmissions->post([
                'content' => [
                  'html' => $email_body,
                  'subject' => $subject,
                  'from' => array('name' => $from,
                                  'email' => OUTGOING_EMAIL_ADDRESS),
                  'attachments' => [
                    [
                      'name' => 'Gift Card.pdf',
                      'type' => 'application/pdf',
                      'data' => base64_encode($giftcard_pdf),
                    ]
                  ],
                  'inline_images' => [
                    [
                      'name' => 'logo.png',
                      'type' => 'image/png',
                      'data' => base64_encode(
                                 file_get_contents('../ui/logo.png')),
                    ],
                  ],
                ],
                'substitution_data' => $data,
                'recipients' => [
                  [
                    'address' => [
                      'name' => $to_name,
                      'email' => $to_email,
                    ],
                  ],
                  [
                    // BCC ourselves
                    'address' => [
                      'name' => $to_name,
                      'header_to' => $to_email,
                      'email' => OUTGOING_EMAIL_ADDRESS,
                    ],
                  ],
                ],
                'options' => [
                  'inlineCss' => true,
                  'transactional' => true,
                ],
              ]);

              $response= $promise->wait();

              return $res->withJson([ 'message' => 'Success!' ]);
            });

  $app->post('/{card:[0-9]+}/add-txn',
            function (Request $req, Response $res, array $args) {
              $id= substr($args['card'], 0, 7);
              $pin= substr($args['card'], -4);
              $card= \Model::factory('Giftcard')
                      ->where('id', $id)
                      ->where('pin', $pin)
                      ->find_one();
              $card->add_txn($req->getParam('amount'),
                             $req->getParam('txn_id'));
              return $res->withJson($card);
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
  $app->get('/empty-products',
            function (Request $req, Response $res, array $args) {
              $data= $this->report->emptyProducts();
              return $this->view->render($res, 'report/empty-products.html',
                                         $data);
            });
  $app->get('/{name}',
            function (Request $req, Response $res, array $args) {
              ob_start();
              include "../old-report/report-{$args['name']}.php";
              $content= ob_get_clean();
              return $this->view->render($res, 'report/old.html', [
                'title' => $GLOBALS['title'],
                'content' => $content,
              ]);
            });
});

/* Media */
$app->get('/media',
          function (Request $req, Response $res, array $args) {
            $page= (int)$req->getParam('page');
            $page_size= 20;
            $media= \Model::factory('Image')
              ->order_by_desc('created_at')
              ->limit($page_size)->offset($page * $page_size)
              ->find_many();
            $total= \Model::factory('Image')->count();

            return $this->view->render($res, 'media/index.html', [
                                         'media' => $media,
                                         'page' => $page,
                                         'page_size' => $page_size,
                                         'total' => $total,
                                        ]);
          });
$app->post('/media/add',
           function (Request $req, Response $res, array $args) {
             $url= $req->getParam('url');
             if ($url) {
               $image= \Scat\Model\Image::createFromUrl($url);
             } else {
               foreach ($req->getUploadedFiles() as $file) {
                 $image= \Scat\Model\Image::createFromStream($file->getStream(),
                                                       $file->getClientFilename());
               }
             }

             return $res->withJson($image);
           });
$app->post('/media/{id}/update',
           function (Request $req, Response $res, array $args) {
             \ORM::get_db()->beginTransaction();

             $image= \Model::factory('Image')->find_one($args['id']);
             if (!$image) {
               throw new \Slim\Exception\NotFoundException($req, $res);
             }
             $image->alt_text= $req->getParam('caption');
             $image->save();

             \ORM::get_db()->commit();

             return $res->withJson($image);
           });

/* Notes */
$app->get('/notes',
          function (Request $req, Response $res, array $args) {
            $parent_id= (int)$req->getParam('parent_id');
            $staff= \Model::factory('Person')
                      ->where('role', 'employee')
                      ->where('person.active', 1)
                      ->order_by_asc('name')
                      ->find_many();
            if ($parent_id) {
              $notes= \Model::factory('Note')
                        ->select('*')
                        ->select_expr('0', 'children')
                        ->where_any_is([
                          [ 'id' => $parent_id ],
                          [ 'parent_id' => $parent_id ]
                        ])
                        ->order_by_asc('id')
                        ->find_many();
            } else {
              $notes= \Model::factory('Note')
                        ->select('*')
                        ->select_expr('(SELECT COUNT(*)
                                          FROM note children
                                         WHERE children.parent_id = note.id)',
                                      'children')
                        ->where('parent_id', $parent_id)
                        ->where('todo', 1)
                        ->order_by_desc('id')
                        ->find_many();
            }
            return $this->view->render($res, 'dialog/notes.html',
                                       [
                                         'body_only' =>
                                           (int)$req->getParam('body_only'),
                                         'parent_id' => $parent_id,
                                         'staff' => $staff,
                                         'notes' => $notes
                                       ]);
          });
$app->post('/notes/add',
           function (Request $req, Response $res, array $args) {
             $note= \Model::factory('Note')->create();
             $note->parent_id= (int)$req->getParam('parent_id');
             $note->person_id= (int)$req->getParam('person_id');
             $note->content= $req->getParam('content');
             $note->todo= (int)$req->getParam('todo');
             $note->public= (int)$req->getParam('public');
             $note->save();

             if ((int)$req->getParam('sms')) {
               try {
                 $txn= $note->parent()->find_one()->txn();
                 $person= $txn->owner();
                 error_log("Sending message to {$person->phone}");
                 $data= $this->phone->sendSMS($person->phone,
                                              $req->getParam('content'));
                 $note->public= true;
                 $note->save();
                } catch (\Exception $e) {
                  error_log("Got exception: " . $e->getMessage());
                }
             }

             return $res->withJson($note);
           });
$app->post('/notes/{id}/update',
           function (Request $req, Response $res, array $args) {
             \ORM::get_db()->beginTransaction();

             $note= \Model::factory('Note')->find_one($args['id']);
             if (!$note) {
               throw new \Slim\Exception\NotFoundException($req, $res);
             }

             $todo= $req->getParam('todo');
             if ($todo !== null && $todo != $note->todo) {
               $note->todo= (int)$req->getParam('todo');
               $update= \Model::factory('Note')->create();
               // TODO who did this?
               $update->parent_id= $note->parent_id ?: $note->id;
               $update->content= $todo ? "Marked todo." : "Marked done.";
               $update->save();
             }

             $public= $req->getParam('public');
             if ($public !== null && $public != $note->public) {
               $note->public= (int)$req->getParam('public');
               $update= \Model::factory('Note')->create();
               // TODO who did this?
               $update->parent_id= $note->parent_id ?: $note->id;
               $update->content= $public ? "Marked public." : "Marked private.";
               $update->save();
             }

             $note->save();

             \ORM::get_db()->commit();

             return $res->withJson($note);
           });

/* Till */
$app->group('/till', function (Slim\App $app) {
  $app->get('',
            function (Request $req, Response $res, array $args) {
              $q= "SELECT CAST(SUM(amount) AS DECIMAL(9,2)) AS expected
                     FROM payment
                    WHERE method IN ('cash','change','withdrawal')";
              $data= \ORM::for_table('payment')->raw_query($q)->find_one();
              return $this->view->render($res, "till/index.html", [
                'expected' => $data->expected,
              ]);
            });

  $app->post('/~print-change-order',
             function (Request $req, Response $res, array $args) {
               $out= $this->printer->printFromTemplate(
                 'print/change-order.html',
                 $req->getParams()
               );
               return $res->getBody()->write($out);
             });

  $app->post('/~count',
             function (Request $req, Response $res, array $args) {
               if ($req->getAttribute('has_errors')) {
                 return $res->withJson([
                   'error' => "Validation failed.",
                   'validation_errors' => $req->getAttribute('errors')
                 ]);
               }

               $counted= $req->getParam('counted');
               $withdraw= $req->getParam('withdraw');

               $q= "SELECT CAST(SUM(amount) AS DECIMAL(9,2)) AS expected
                      FROM payment
                     WHERE method IN ('cash','change','withdrawal')";
               $data= \ORM::for_table('payment')->raw_query($q)->find_one();
               $expected= $data->expected;

               \ORM::get_db()->beginTransaction();

               $txn= $this->txn->create([ 'type' => 'drawer' ]);

               if ($count != $expected) {
                 $amount= $counted - $expected;

                 $payment= Model::factory('Payment')->create();
                 $payment->txn_id= $txn->id;
                 $payment->method= 'cash';
                 $payment->amount= $amount;
                 $payment->set_expr('processed', 'NOW()');

                 $payment->save();
               }

               if ($withdraw) {
                 $payment= Model::factory('Payment')->create();
                 $payment->txn_id= $txn->id;
                 $payment->method= 'withdrawal';
                 $payment->amount= -$withdraw;
                 $payment->set_expr('processed', 'NOW()');

                 $payment->save();
               }

               \ORM::get_db()->commit();

               $data= \ORM::for_table('payment')->raw_query($q)->find_one();
               return $res->withJson(['expected' => $data->expected ]);
             })
      ->add(new Validation([
        'counted' => v::numeric()::positive(),
        'withdraw' => v::numeric(),
      ]));

  $app->post('/~withdraw-cash',
             function (Request $req, Response $res, array $args) {
               if ($req->getAttribute('has_errors')) {
                 return $res->withJson([
                   'error' => "Validation failed.",
                   'validation_errors' => $req->getAttribute('errors')
                 ]);
               }

               $reason= $req->getParam('reason');
               $amount= $req->getParam('amount');

               \ORM::get_db()->beginTransaction();

               $txn= $this->txn->create([ 'type' => 'drawer' ]);

               $payment= Model::factory('Payment')->create();
               $payment->txn_id= $txn->id;
               $payment->method= 'withdrawal';
               $payment->amount= -$amount;
               $payment->set_expr('processed', 'NOW()');

               $payment->save();

               $note= Model::factory('Note')->create();
               $note->kind= 'txn';
               $note->attach_id= $txn->id;
               $note->content= $reason;

               $note->save();

               \ORM::get_db()->commit();

               return $res->withJson($txn);
             })
      ->add(new Validation([
        'amount' => v::numeric()::positive(),
        'reason' => v::stringType()::notOptional(),
      ]));
});

/* Safari notifications */
$app->get('/push',
            function (Request $req, Response $res, array $args) {
              return $this->view->render($res, "push/index.html");
            });

$app->post('/push/v2/pushPackages/{id}',
           function (Request $req, Response $res, array $args) {
             $zip= $this->push->getPushPackage();
             return $res->withHeader("Content-type", "application/zip")
                         ->withBody($zip);
           });

$app->post('/push/v1/devices/{token}/registrations/{id}',
           function (Request $req, Response $res, array $args) {
             error_log("PUSH: Registered device: '{$args['token']}'");
             $device= \Scat\Model\Device::register($args['token']);
             return $res;
           });
$app->delete('/push/v1/devices/{token}/registrations/{id}',
           function (Request $req, Response $res, array $args) {
             error_log("PUSH: Forget device: '{$args['token']}'");
             $device= \Scat\Model\Device::forget($args['token']);
             return $res;
           });

$app->post('/push/v1/log',
           function (Request $req, Response $res, array $args) {
             $data= $req->getParsedBody($req);
             error_log("PUSH: " . json_encode($data));
             return $res;
           });

$app->post('/~push-notification',
           function (Request $req, Response $res, array $args) {
              $devices= \Model::factory('Device')->find_many();

              foreach ($devices as $device) {
                $this->push->sendNotification(
                  $device->token,
                  $req->getParam('title'),
                  $req->getParam('body'),
                  $req->getParam('action'),
                  'clicked' /* Not sure what to do about arguments yet. */
                );
              }

              return $res->withRedirect("/push");
           });

/* Tax stuff */
$app->get('/~tax/ping',
           function (Request $req, Response $res, array $args) {
              return $res->withJson($this->tax->ping());
           });

/* SMS TODO just testing right now */
$app->get('/~sms/send',
            function (Request $req, Response $res, array $args) {
              $data= $this->phone->sendSMS($req->getParam('to'),
                                           $req->getParam('text'));
              return $res->withJson($data);
            });

$app->get('/dialog/{dialog}',
          function (Request $req, Response $res, array $args) {
            return $this->view->render($res, "dialog/{$args['dialog']}");
          });

$app->get('/~ready-for-publish',
          function (Request $req, Response $res, array $args) {
            if (file_exists('/tmp/ready-for-publish')) {
              $res->getBody()->write('OK');
              unlink('/tmp/ready-for-publish');
            } else {
              $res->getBody()->write('NO');
            }
            return $res;
          });
$app->post('/~ready-for-publish',
           function (Request $req, Response $res, array $args) {
             touch('/tmp/ready-for-publish');
             return $res;
           });

$app->get('/~gift-card/check-balance',
          function (Request $req, Response $res, array $args) {
            $card= $req->getParam('card');
            return $res->withJson($this->giftcard->check_balance($card));
          });

$app->get('/~gift-card/add-txn',
          function (Request $req, Response $res, array $args) {
            $card= $req->getParam('card');
            $amount= $req->getParam('amount');
            return $res->withJson($this->giftcard->add_txn($card, $amount));
          });

$app->get('/~rewards/check-balance',
          function (Request $req, Response $res, array $args) {
            $loyalty= $req->getParam('loyalty');
            $loyalty_number= preg_replace('/[^\d]/', '', $loyalty);
            $person= \Model::factory('Person')
                      ->where_any_is([
                        [ 'loyalty_number' => $loyalty_number ?: 'no' ],
                        [ 'email' => $loyalty ]
                      ])
                      ->find_one();
            if (!$person)
              throw new \Slim\Exception\NotFoundException($req, $res);
            return $res->withJson([
              'loyalty_suppressed' => $person->loyalty_suppressed,
              'points_available' => $person->points_available(),
              'points_pending' => $person->points_pending(),
            ]);
          });

/* Ordure */
$app->group('/ordure', function (Slim\App $app) {
  $app->get('/~push-prices', \Scat\Controller\Ordure::class . ':pushPrices');
  $app->get('/~pull-orders', \Scat\Controller\Ordure::class . ':pullOrders');
  $app->get('/~pull-signups', \Scat\Controller\Ordure::class . ':pullSignups');
  $app->get('/~process-abandoned-carts',
            \Scat\Controller\Ordure::class . ':processAbandonedCarts');
});

/* QuickBooks */
$app->group('/quickbooks', function (Slim\App $app) {
  $app->get('',
            \Scat\Controller\Quickbooks::class . ':home');
  $app->get('/verify-accounts',
            \Scat\Controller\Quickbooks::class . ':verifyAccounts');
  $app->post('/~create-account',
            \Scat\Controller\Quickbooks::class . ':createAccount');
  $app->get('/~disconnect',
            \Scat\Controller\Quickbooks::class . ':disconnect');
  $app->post('/~sync', \Scat\Controller\Quickbooks::class . ':sync')
      ->add(new Validation([
          'from' => v::in(['sales', 'payments']),
          'date' => v::date(),
        ]));
});

/* Info (DEBUG only) */
if ($DEBUG) {
  $app->get('/info',
            function (Request $req, Response $res, array $args) {
              ob_start();
              phpinfo();
              return $res->getBody()->write(ob_get_clean());
            })->setName('info');

  $app->get('/test',
            function (Request $req, Response $res, array $args) {
              return $this->view->render($res, "test.html");
            });
  $app->get('/test/~one',
            function (Request $req, Response $res, array $args) {
              $res->getBody()->write('<div class="alert alert-warning">Reloaded one!</div>');
              return $res;
            });
  $app->get('/test/~two',
            function (Request $req, Response $res, array $args) {
              $res->getBody()->write('<div class="alert alert-warning">Reloaded two!</div>');
              return $res;
            });
  $app->post('/test',
            function (Request $req, Response $res, array $args) {
              $data= $req->getParams();
              $res= $res->withHeader('X-Scat-Title', 'New Page Title');
              $res= $res->withAddedHeader('X-Scat-Reload', 'one');
              $res= $res->withAddedHeader('X-Scat-Reload', 'two');
              return $res->withJson($data);
            });
}

$app->run();
