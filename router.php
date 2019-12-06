<?php
require 'vendor/autoload.php';

use \Psr\Http\Message\RequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

/* Some defaults */
error_reporting(E_ALL & ~E_NOTICE);
date_default_timezone_set($_ENV['PHP_TIMEZONE'] ?: $_ENV['TZ']);
bcscale(2);

$DEBUG= $ORM_DEBUG= false;
$config= require $_ENV['SCAT_CONFIG'] ?: dirname(__FILE__).'/config.php';

/* Configure Idiorm & Paris */
Model::$auto_prefix_models= '\\Scat\\';

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

/* Hook up our services */
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
$container['txn']= function($c) {
  return new \Scat\TxnService();
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
              return $res->withRedirect('/txns.php?type=customer');
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
                  'inline_images' => [
                    [
                      'name' => 'logo.png',
                      'type' => 'image/png',
                      'data' => base64_encode(
                                 file_get_contents('ui/logo.png')),
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
              return $res->withRedirect('/txns.php?type=vendor');
            });
  $app->get('/reorder',
            function (Request $req, Response $res, array $args) {

/* XXX This needs to move into a service or something. */
$extra= $extra_field= $extra_field_name= '';

$all= (int)$req->getParam('all');

$vendor_code= "NULL";
$vendor= (int)$req->getParam('vendor');
if ($vendor > 0) {
  $vendor_code= "(SELECT code FROM vendor_item WHERE vendor = $vendor AND item = item.id AND vendor_item.active LIMIT 1)";
  $extra= "AND EXISTS (SELECT id
                         FROM vendor_item
                        WHERE vendor = $vendor
                          AND item = item.id
                          AND vendor_item.active)";
  $extra_field= "(SELECT MIN(IF(promo_quantity, promo_quantity,
                                purchase_quantity))
                    FROM vendor_item
                   WHERE item = item.id
                     AND vendor = $vendor
                     AND vendor_item.active)
                  AS minimum_order_quantity,
                 (SELECT MIN(IF(promo_price, promo_price, net_price))
                    FROM vendor_item
                    JOIN person ON vendor_item.vendor = person.id
                  WHERE item = item.id
                    AND vendor = $vendor
                    AND vendor_item.active)
                  AS cost,
                 (SELECT MIN(IF(promo_price, promo_price, net_price)
                             * ((100 - vendor_rebate) / 100))
                    FROM vendor_item
                    JOIN person ON vendor_item.vendor = person.id
                  WHERE item = item.id
                    AND NOT special_order
                    AND vendor = $vendor
                    AND vendor_item.active) -
                 (SELECT MIN(IF(promo_price, promo_price, net_price)
                             * ((100 - vendor_rebate) / 100))
                    FROM vendor_item
                    JOIN person ON vendor_item.vendor = person.id
                   WHERE item = item.id
                     AND NOT special_order
                     AND vendor != $vendor
                     AND vendor_item.active)
                 cheapest, ";
  $extra_field_name= "minimum_order_quantity, cheapest, cost,";
} else if ($vendor < 0) {
  // No vendor
  $extra= "AND NOT EXISTS (SELECT id
                             FROM vendor_item
                            WHERE item = item.id
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
                       FROM txn_line JOIN txn ON (txn = txn.id)
                      WHERE type = 'customer'
                        AND txn_line.item = item.id
                        AND filled > NOW() - INTERVAL 3 MONTH)
                    AS last3months,
                    (SELECT SUM(ordered - allocated)
                       FROM txn_line JOIN txn ON (txn = txn.id)
                      WHERE type = 'vendor'
                        AND txn_line.item = item.id
                        AND created > NOW() - INTERVAL 12 MONTH)
                    AS ordered,
                    $extra_field
                    IF(minimum_quantity > minimum_quantity - SUM(allocated),
                       minimum_quantity,
                       minimum_quantity - IFNULL(SUM(allocated), 0))
                      AS order_quantity
               FROM item
               LEFT JOIN txn_line ON (item = item.id)
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
              ]);
            })->setName('sale');
  $app->post('/reorder',
             function (Request $req, Response $res, array $args) {
               $vendor_id= $req->getParam('vendor');

               if (!$vendor_id) {
                 throw new \Exception("No vendor specified.");
               }

               \ORM::get_db()->beginTransaction();

               $purchase= $this->txn->create([
                 'type' => 'vendor',
                 'person' => $vendor_id,
                 'tax_rate' => 0,
               ]);

               $items= $req->getParam('item');
               foreach ($items as $item_id => $quantity) {
                 if (!$quantity) {
                   continue;
                 }

                 $vendor_items=
                   \Scat\VendorItem::findByItemIdForVendor($item_id,
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

                 $item= $purchase->items_source()->create();
                 $item->txn= $purchase->id;
                 $item->item= $item_id;
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

              return $this->view->render($res, 'catalog-searchresults.html',
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
                 $image= \Scat\Image::createFromUrl($url);
                 $product->addImage($image);
               } else {
                 foreach ($req->getUploadedFiles() as $file) {
                   $image= \Scat\Image::createFromStream($file->getStream(),
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
                                 ->where('active', 1)
                                 ->find_many();

              $brands= $brand ? null : $this->catalog->getBrands();

              return $this->view->render($res, 'catalog-brand.html',
                                         [ 'depts' => $depts,
                                           'brands' => $brands,
                                           'brand' => $brand,
                                           'products' => $products ]);
            })->setName('catalog-brand');

  $app->get('/item[/{code:.*}]',
            function (Request $req, Response $res, array $args) {
              return $res->withRedirect("/item.php?code={$args['code']}");
            })->setName('catalog-item');

  $app->post('/item-update',
             function (Request $req, Response $res, array $args) {
               $id= $req->getParam('pk');
               $name= $req->getParam('name');
               $value= $req->getParam('value');
               $item= \Model::factory('Item')->find_one($id);
               if (!$item)
                 throw new \Slim\Exception\NotFoundException($req, $res);

               $item->setProperty($name, $value);
               $item->save();

               return $res->withJson([
                 'item' => $item,
                 'replaceRow' => $this->view->fetch('catalog/item-row.twig', [
                                  'i' => $item,
                                  'variations' => $req->getParam('variations'),
                                  'product' => $req->getParam('product')
                                ])
               ]);
             });

  $app->get('/whats-new',
            function (Request $req, Response $res, array $args) {
              $limit= (int)$req->getParam('limit');
              if (!$limit) $limit= 12;
              $products= $this->catalog->getNewProducts($limit);
              $depts= $this->catalog->getDepartments();

              return $this->view->render($res, 'catalog-whatsnew.html',
                                         [
                                           'products' => $products,
                                           'depts' => $depts,
                                         ]);
            })->setName('catalog-whats-new');

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
              return $this->view->render($res, 'page/people.html');
            });
  $app->get('/search',
            function (Request $req, Response $res, array $args) {
              $q= trim($req->getParam('q'));

              $people= \Scat\Person::find($q);

              return $this->view->render($res, 'page/people.html',
                                         [ 'people' => $people, 'q' => $q ]);
            })->setName('person-search');
  $app->get('/{id:[0-9]+}',
            function (Request $req, Response $res, array $args) {
              return $res->withRedirect("/person.php?id={$args['id']}");
            })->setName('person');
  $app->get('/{id:[0-9]+}/items',
            function (Request $req, Response $res, array $args) {
              $person= \Model::factory('Person')->find_one($args['id']);
              $page= (int)$req->getParam('page');
              if ($person->role != 'vendor') {
                throw new \Exception("That person is not a vendor.");
              }
              $limit= 25;
              $items= $person->items()
                             ->limit($limit)->offset($page * $limit)
                             ->order_by_asc('code')
                             ->find_many();
              return $this->view->render($res, 'person/items.html', [
                                           'person' => $person,
                                           'items' => $items,
                                           'page' => $page,
                                           'page_size' => $page_size,
                                          ]);
            })->setName('vendor-items');
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
  $app->get('/empty-products',
            function (Request $req, Response $res, array $args) {
              $data= $this->report->emptyProducts();
              return $this->view->render($res, 'report/empty-products.html',
                                         $data);
            });
  $app->get('/{name}',
            function (Request $req, Response $res, array $args) {
              return $res->withRedirect("/report-{$args['name']}.php");
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
               $image= \Scat\Image::createFromUrl($url);
             } else {
               foreach ($req->getUploadedFiles() as $file) {
                 $image= \Scat\Image::createFromStream($file->getStream(),
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
                      ->where('active', 1)
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
