<?php
require '../vendor/autoload.php';

use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;
use \Slim\Routing\RouteCollectorProxy as RouteCollectorProxy;
use \DavidePastore\Slim\Validation\Validation as Validation;

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

/* 404 */
$errorMiddleware->setErrorHandler(
  \Slim\Exception\HttpNotFoundException::class,
  function (Request $request, Throwable $exception,
            bool $displayErrorDetails) use ($container)
  {
    $response= new \Slim\Psr7\Response();
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
  $app->get('',
            function (Request $request, Response $response,
                      \Scat\Service\Txn $txn, View $view) {
              $page= (int)$request->getParam('page');
              $limit= 25;
              $txns= $txn->find('customer', $page, $limit);
              return $view->render($response, 'txn/index.html', [
                'type' => 'customer',
                'txns' => $txns,
                'page' => $page,
                'limit' => $limit,
              ]);
            });
  $app->get('/new',
            function (Response $response, View $view) {
              ob_start();
              include "../old-index.php";
              $content= ob_get_clean();
              return $view->render($response, 'sale/old-new.html', [
                'title' => $GLOBALS['title'],
                'content' => $content,
              ]);
            });
  $app->get('/{id:[0-9]+}',
            function (Response $response, $id) {
              return $response->withRedirect("/?id=$id");
            })->setName('sale');
  $app->get('/email-invoice-form',
            function (Request $request, Response $response,
                      \Scat\Service\Txn $txn, View $view) {
              $txn= $txn->fetchById($request->getParam('id'));
              return $view->render($response, 'dialog/email-invoice.html',
                                   [ 'txn' => $txn ]);
            });
  $app->post('/email-invoice',
            function (Request $request, Response $response,
                      \Scat\Service\Txn $txn, View $view) {
              $txn= $txn->fetchById($request->getParam('id'));
              $name= $request->getParam('name');
              $email= $request->getParam('email');
              $subject= trim($request->getParam('subject'));
              error_log("Sending {$txn->id} to $email");

              $attachments= [];
              if ($request->getParam('include_details')) {
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
                  'html' => $view->fetch('email/invoice.html',
                                         [
                                           'txn' => $txn,
                                           'subject' => $subject,
                                           'content' =>
                                             $request->getParam('content'),
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
                return $response->withJson([ "message" => "Email sent." ]);
	      } catch (\Exception $e) {
		error_log(sprintf("SparkPost failure: %s (%s)",
				  $e->getMessage(), $e->getCode()));
                return $response->withJson([
                  "error" =>
                    sprintf("SparkPost failure: %s (%s)",
                            $e->getMessage(), $e->getCode())
                ], 500);
	      }
            });
});

/* Purchases */
$app->group('/purchase', function (RouteCollectorProxy $app) {
  $app->get('',
            function (Request $request, Response $response,
                      \Scat\Service\Txn $txn, View $view) {
              $page= (int)$request->getParam('page');
              $limit= 25;
              $txns= $txn->find('vendor', $page, $limit);
              return $view->render($response, 'txn/index.html', [
                'type' => 'vendor',
                'txns' => $txns,
                'page' => $page,
                'limit' => $limit,
              ]);
            });
  $app->get('/reorder',
            function (Request $request, Response $response) {

/* XXX This needs to move into a service or something. */
$extra= $extra_field= $extra_field_name= '';

$all= (int)$request->getParam('all');

$vendor_code= "NULL";
$vendor= (int)$request->getParam('vendor');
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

$code= trim($request->getParam('code'));
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

              return $view->render($response, 'purchase/reorder.html', [
                'items' => $items,
                'all' => $all,
                'code' => $code,
                'vendor' => $vendor,
                'person' => \Model::factory('Person')->find_one($vendor)
              ]);
            })->setName('sale');
  $app->get('/create',
             function (Request $request, Response $response,
                       \Scat\Service\Txn $txn, View $view) {
               $vendor_id= $request->getParam('vendor');

               if (!$vendor_id) {
                 throw new \Exception("No vendor specified.");
               }

               $purchase= $txn->create([
                 'type' => 'vendor',
                 'person_id' => $vendor_id,
                 'tax_rate' => 0,
               ]);
               $path= $this->router->pathFor('purchase', [
                 'id' => $purchase->id
               ]);
               return $response->withRedirect($path);
             });
  $app->post('/reorder',
             function (Request $request, Response $response,
                       \Scat\Service\Txn $txn, View $view) {
               $vendor_id= $request->getParam('vendor');

               if (!$vendor_id) {
                 throw new \Exception("No vendor specified.");
               }

               \ORM::get_db()->beginTransaction();

               $txn_id= $request->getParam('txn_id');
               if ($txn_id) {
                 $purchase= $txn->fetchById($txn_id);
                 if (!$txn_id) {
                   throw new \Exception("Unable to find transaction.");
                 }
               } else {
                 $purchase= $txn->create([
                   'type' => 'vendor',
                   'person_id' => $vendor_id,
                   'tax_rate' => 0,
                 ]);
               }

               $items= $request->getParam('item');
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
               return $response->withRedirect($path);
             });
  $app->get('/{id}',
            function (Request $request, Response $response, $id) {
              return $response->withRedirect("/?id=$id");
            })->setName('purchase');
});

/* Catalog */
$app->group('/catalog', function (RouteCollectorProxy $app) {
  $app->get('/search',
            function (Request $request, Response $response,
                      \Scat\Service\Search $search,
                      \Scat\Service\Catalog $catalog,
                      View $view) {
              $q= trim($request->getParam('q'));

              $data= $search->search($q);

              $data['depts']= $catalog->getDepartments();
              $data['q']= $q;

              return $view->render($response, 'catalog/searchresults.html',
                                   $data);
            })->setName('catalog-search');

  $app->get('/~reindex',
            function (Request $request, Response $response,
                      \Scat\Service\Search $search,
                      \Scat\Service\Catalog $catalog) {
    $search->flush();

    $rows= 0;
    foreach ($catalog->getProducts() as $product) {
      $rows+= $search->indexProduct($product);
    }

    $response->getBody()->write("Indexed $rows rows.");
    return $response;
  });

  $app->get('/brand-form',
            function (Request $request, Response $response,
                      \Scat\Service\Catalog $catalog, View $view) {
              $brand= $catalog->getBrandById($request->getParam('id'));
              return $view->render($response, 'dialog/brand-edit.html',
                                         [
                                           'brand' => $brand
                                         ]);
            });

  $app->post('/brand-form',
             function (Request $request, Response $response,
                      \Scat\Service\Catalog $catalog) {
               $brand= $catalog->getBrandById($request->getParam('id'));
               if (!$brand)
                 $brand= $catalog->createBrand();
               $brand->name= $request->getParam('name');
               $brand->slug= $request->getParam('slug');
               $brand->description= $request->getParam('description');
               $brand->active= (int)$request->getParam('active');
               $brand->save();
               return $response->withJson($brand);
             });

  $app->get('/department-form',
            function (Request $request, Response $response,
                      \Scat\Service\Catalog $catalog, View $view) {
              $depts= $catalog->getDepartments();
              $dept= $catalog->getDepartmentById($request->getParam('id'));
              return $view->render($response, 'dialog/department-edit.html',
                                         [
                                           'depts' => $depts,
                                           'dept' => $dept
                                         ]);
            });

  $app->post('/department-form',
             function (Request $request, Response $response,
                      \Scat\Service\Catalog $catalog) {
               $dept= $catalog->getDepartmentById($request->getParam('id'));
               if (!$dept)
                 $dept= $catalog->createDepartment();
               $dept->name= $request->getParam('name');
               $dept->slug= $request->getParam('slug');
               $dept->parent_id= $request->getParam('parent_id');
               $dept->description= $request->getParam('description');
               $dept->active= (int)$request->getParam('active');
               $dept->save();
               return $response->withJson($dept);
             });

  $app->get('/product-form',
            function (Request $request, Response $response,
                      \Scat\Service\Catalog $catalog, View $view) {
              $depts= $catalog->getDepartments();
              $product= $catalog->getProductById($request->getParam('id'));
              $brands= $catalog->getBrands();
              return $view->render($response, 'dialog/product-edit.html',
                                         [
                                           'depts' => $depts,
                                           'brands' => $brands,
                                           'product' => $product,
                                           'department_id' =>
                                             $request->getParam('department_id'),
                                         ]);
            });

  $app->post('/product-form',
             function (Request $request, Response $response,
                       \Scat\Service\Catalog $catalog,
                       \Scat\Service\Search $search) {
               $product= $catalog->getProductById($request->getParam('id'));
               if (!$product) {
                 if (!$request->getParam('slug')) {
                   throw \Exception("Must specify a slug.");
                 }
                 $product= $catalog->createProduct();
               }
               foreach ($product->fields() as $field) {
                 $value= $request->getParam($field);
                 if (isset($value)) {
                   $product->set($field, $value);
                 }
               }
               $product->save();
               $search->indexProduct($product);
               return $response->withJson($product);
             });

  $app->post('/product/add-image',
             function (Request $request, Response $response,
                       \Scat\Service\Catalog $catalog) {
               $product= $catalog->getProductById($request->getParam('id'));

               $url= $request->getParam('url');
               if ($url) {
                 $image= \Scat\Model\Image::createFromUrl($url);
                 $product->addImage($image);
               } else {
                 foreach ($request->getUploadedFiles() as $file) {
                   $image= \Scat\Model\Image::createFromStream($file->getStream(),
                                                         $file->getClientFilename());
                   $product->addImage($image);
                 }
               }

               return $response->withJson($product);
             });

  $app->get('/brand[/{slug}]',
            function (Request $request, Response $response, $slug,
                      View $view, \Scat\Service\Catalog $catalog) {
              $depts= $catalog->getDepartments();

              $brand= $slug ?
                $catalog->getBrandBySlug($slug) : null;
              if ($slug && !$brand)
                throw new \Slim\Exception\HttpNotFoundException($request);

              if ($brand)
                $products= $brand->products()
                                 ->order_by_asc('name')
                                 ->where('product.active', 1)
                                 ->find_many();

              $brands= $brand ? null : $catalog->getBrands();

              return $view->render($response, 'catalog/brand.html',
                                         [ 'depts' => $depts,
                                           'brands' => $brands,
                                           'brand' => $brand,
                                           'products' => $products ]);
            })->setName('catalog-brand');

  $app->get('/item/{code:.*}',
            function (Request $request, Response $response, $code,
                      \Scat\Service\Catalog $catalog, View $view) {
              $item= $catalog->getItemByCode($code);
              if (!$item)
               throw new \Slim\Exception\HttpNotFoundException($request);
              return $view->render($response, 'catalog/item.html', [
                                          'item' => $item,
                                         ]);
            })->setName('catalog-item');

  $app->post('/item/{code:.*}/~print-label',
            function (Request $request, Response $response, $code,
                      \Scat\Service\Catalog $catalog) {
              $item= $catalog->getItemByCode($code);
              if (!$item)
               throw new \Slim\Exception\HttpNotFoundException($request);

              $body= $response->getBody();
              $body->write($item->getPDF($request->getParams()));
              return $response->withHeader("Content-type", "application/pdf");
            });

  $app->post('/item/{code:.*}/~add-barcode',
            function (Request $request, Response $response, $code,
                      \Scat\Service\Catalog $catalog) {
              $item= $catalog->getItemByCode($code);
              if (!$item)
               throw new \Slim\Exception\HttpNotFoundException($request);

              $barcode= $item->barcodes()->create();
              $barcode->item_id= $item->id;
              $barcode->code= trim($request->getParam('barcode'));
              $barcode->quantity= 1;
              $barcode->save();

              return $response->withJson($item);
            });

  $app->post('/item/{code:.*}/~edit-barcode',
            function (Request $request, Response $response, $code,
                      \Scat\Service\Catalog $catalog) {
              $item= $catalog->getItemByCode($code);
              if (!$item)
               throw new \Slim\Exception\HttpNotFoundException($request);

              $barcode= $item->barcodes()
                          ->where('code', $request->getParam('pk'))
                          ->find_one();

              if (!$barcode)
               throw new \Slim\Exception\HttpNotFoundException($request);

              if ($request->getParam('name') == 'quantity') {
                $barcode->quantity= trim($request->getParam('value'));
              }

              if ($request->getParam('name') == 'code') {
                $barcode->code= trim($request->getParam('value'));
              }

              $barcode->save();

              return $response->withJson($item);
            });

  $app->post('/item/{code:.*}/~remove-barcode',
            function (Request $request, Response $response, $code,
                      \Scat\Service\Catalog $catalog) {
              $item= $catalog->getItemByCode($code);
              if (!$item)
               throw new \Slim\Exception\HttpNotFoundException($request);

              $barcode= $item->barcodes()
                          ->where('code', $request->getParam('pk'))
                          ->find_one();

              if (!$barcode)
               throw new \Slim\Exception\HttpNotFoundException($request);

              $barcode->delete();

              return $response->withJson($item);
            });

  $app->post('/item/{code:.*}/~find-vendor-items',
            function (Request $request, Response $response, $code,
                      \Scat\Service\Catalog $catalog) {
              $item= $catalog->getItemByCode($code);
              if (!$item)
               throw new \Slim\Exception\HttpNotFoundException($request);

              $item->findVendorItems();

              return $response->withJson($item);
            });
  $app->post('/item/{code:.*}/~unlink-vendor-item',
            function (Request $request, Response $response, $code,
                      \Scat\Service\Catalog $catalog) {
              $item= $catalog->getItemByCode($code);
              if (!$item)
               throw new \Slim\Exception\HttpNotFoundException($request);
              $vi= $item->vendor_items()
                        ->where('id', $request->getParam('id'))
                        ->find_one();
              if (!$vi)
               throw new \Slim\Exception\HttpNotFoundException($request);

              $vi->item_id= 0;
              $vi->save();

              return $response->withJson($item);
            });
  $app->post('/item/{code:.*}/~check-vendor-stock',
            function (Request $request, Response $response, $code,
                      \Scat\Service\Catalog $catalog) {
              $item= $catalog->getItemByCode($code);
              if (!$item)
               throw new \Slim\Exception\HttpNotFoundException($request);
              $vi= $item->vendor_items()
                        ->where('id', $request->getParam('id'))
                        ->find_one();
              if (!$vi)
               throw new \Slim\Exception\HttpNotFoundException($request);

              return $response->withJson($vi->checkVendorStock());
            });

  $app->get('/item-add-form',
            function (Request $request, Response $response, View $view) {
              return $view->render($response, 'dialog/item-add.html',
                                         [
                                           'product_id' =>
                                             $request->getParam('product_id'),
                                         ]);
            });

  $app->get('/vendor-item-form',
            function (Request $request, Response $response,
                      \Scat\Service\Catalog $catalog, View $view) {
              $item= $catalog->getItemById($request->getParam('item'));
              $vendor_item= $catalog->getVendorItemById($request->getParam('id'));
              return $view->render($response, 'dialog/vendor-item-edit.html',
                                         [
                                           'item' => $item,
                                           'vendor_item' => $vendor_item,
                                         ]);
            });
  $app->post('/~update-vendor-item',
             function (Request $request, Response $response,
                       \Scat\Service\Catalog $catalog) {
               $vi= $catalog->getVendorItemById($request->getParam('id'));
               if (!$vi) {
                 $vi= $catalog->createVendorItem();
               }
               foreach ($vi->fields() as $field) {
                 $value= $request->getParam($field);
                 if (isset($value)) {
                   $vi->set($field, $value);
                 }
               }
               $vi->save();
               return $response->withJson($vi);
             });


  $app->post('/item-update',
             function (Request $request, Response $response,
                       \Scat\Service\Catalog $catalog, View $view) {
               $id= $request->getParam('pk');
               $name= $request->getParam('name');
               $value= $request->getParam('value');
               $item= $catalog->getItemById($id);
               if (!$item)
                 throw new \Slim\Exception\HttpNotFoundException($request);

               $item->setProperty($name, $value);
               $item->save();

               return $response->withJson([
                 'item' => $item,
                 'newValue' => $item->$name,
                 'replaceRow' => $view->fetch('catalog/item-row.twig', [
                                  'i' => $item,
                                  'variations' => $request->getParam('variations'),
                                  'product' => $request->getParam('product')
                                ])
               ]);
             });
  // XXX used by old-report/report-price-change
  $app->post('/item-reprice',
             function (Request $request, Response $response,
                       \Scat\Service\Catalog $catalog) {
               $id= $request->getParam('id');
               $retail_price= $request->getParam('retail_price');
               $discount= $request->getParam('discount');
               $item= $catalog->getItemById($id);
               if (!$item)
                 throw new \Slim\Exception\HttpNotFoundException($request);

               $item->setProperty('retail_price', $retail_price);
               $item->setProperty('discount', $discount);
               $item->save();

               return $response->withJson([ 'item' => $item ]);
             });


  $app->post('/bulk-update',
             function (Request $request, Response $response) {
               $items= $request->getParam('items');

               if (!preg_match('/^(?:\d+)(?:,\d+)*$/', $items)) {
                 throw new \Exception("Invalid items specified.");
               }

               foreach (explode(',', $items) as $id) {
                 $item= \Model::factory('Item')->find_one($id);
                 if (!$item)
                   throw new \Slim\Exception\HttpNotFoundException($request);

                 foreach ([ 'brand_id','product_id','name','short_name','variation','retail_price','discount','minimum_quantity','purchase_quantity','dimensions','weight','prop65','hazmat','oversized','active' ] as $key) {
                   $value= $request->getParam($key);
                   if (strlen($value)) {
                     $item->setProperty($key, $value);
                   }
                 }

                 $item->save();
               }

               return $response->withJson([ 'message' => 'Okay.' ]);
             });

  $app->get('/whats-new',
            function (Request $request, Response $response,
                      \Scat\Service\Catalog $catalog, View $view) {
              $limit= (int)$request->getParam('limit');
              if (!$limit) $limit= 12;
              $products= $catalog->getNewProducts($limit);
              $depts= $catalog->getDepartments();

              return $view->render($response, 'catalog/whatsnew.html',
                                         [
                                           'products' => $products,
                                           'depts' => $depts,
                                         ]);
            })->setName('catalog-whats-new');

  $app->get('/price-overrides',
             function (Request $request, Response $response, View $view) {
               $price_overrides= \Model::factory('PriceOverride')
                                  ->order_by_asc('pattern')
                                  ->order_by_asc('minimum_quantity')
                                  ->find_many();

               return $view->render($response, 'catalog/price-overrides.html',[
                'price_overrides' => $price_overrides,
               ]);
             })->setName('catalog-price-overrides');
  $app->post('/price-overrides/~delete',
             function (Request $request, Response $response) {
               $override= \Model::factory('PriceOverride')
                            ->find_one($request->getParam('id'));
               if (!$override) {
                 throw new \Slim\Exception\HttpNotFoundException($request);
               }
               $override->delete();
               return $response->withJson([ 'message' => 'Success!' ]);
             });
  $app->post('/price-overrides/~edit',
             function (Request $request, Response $response) {
               $override= \Model::factory('PriceOverride')
                            ->find_one($request->getParam('id'));
               if (!$override) {
                 $override= \Model::factory('PriceOverride')->create();
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
              $override= \Model::factory('PriceOverride')
                           ->find_one($request->getParam('id'));
              return $view->render($response,
                                         'dialog/price-override-edit.html',
                                         [ 'override' => $override ]);
            });

  $app->get('[/{dept}[/{subdept}[/{product}]]]',
            function (Request $request, Response $response,
                      \Scat\Service\Catalog $catalog, View $view,
                      $dept= null, $subdept= null, $product= null) {
            try {
              $depts= $catalog->getDepartments();
              $deptO= $dept ?
                $catalog->getDepartmentBySlug($dept):null;

              if ($dept && !$deptO)
                throw new \Slim\Exception\HttpNotFoundException($request);

              $subdepts= $deptO ?
                $deptO->departments()->order_by_asc('name')->find_many():null;

              $subdeptO= $subdept ?
                $deptO->departments(false)
                      ->where('slug', $subdept)
                      ->find_one():null;
              if ($subdept && !$subdeptO)
                throw new \Slim\Exception\HttpNotFoundException($request);

              $products= $subdeptO ?
                $subdeptO->products()
                         ->select('product.*')
                         ->left_outer_join('brand',
                                           array('product.brand_id', '=',
                                                  'brand.id'))
                         ->order_by_asc('brand.name')
                         ->order_by_asc('product.name')
                         ->find_many():null;

              $productO= $product ?
                $subdeptO->products(false)
                         ->where('slug', $product)
                         ->find_one():null;
              if ($product && !$productO)
                throw new \Slim\Exception\HttpNotFoundException($request);

              $items= $productO ?
                $productO->items()
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

              $brands= $deptO ? null : $catalog->getBrands();

              return $view->render($response, 'catalog/layout.html',
                                         [ 'brands' => $brands,
                                           'dept' => $deptO,
                                           'depts' => $depts,
                                           'subdept' => $subdeptO,
                                           'subdepts' => $subdepts,
                                           'product' => $productO,
                                           'products' => $products,
                                           'variations' => $variations,
                                           'items' => $items ]);
             }
             catch (\Slim\Exception\HttpNotFoundException $ex) {
               /* TODO figure out a way to not have to add/remove /catalog/ */
               $path= preg_replace('!/catalog/!', '',
                                   $request->getUri()->getPath());
               $re= $catalog->getRedirectFrom($path);

               if ($re) {
                 return $response->withRedirect('/catalog/' . $re->dest, 301);
               }

               throw $ex;
             }
            })->setName('catalog');

  $app->post('/~add-item',
             function (Request $request, Response $response,
                       \Scat\Service\Catalog $catalog) {
               \ORM::get_db()->beginTransaction();

               $item= $catalog->createItem();

               $item->code= trim($request->getParam('code'));
               $item->name= trim($request->getParam('name'));
               $item->retail_price= $request->getParam('retail_price');

               if ($request->getParam('product_id')) {
                 $item->product_id= $request->getParam('product_id');
               }

               if (($id= $request->getParam('vendor_item'))) {
                 $vendor_item= $catalog->getVendorItemById($id);
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

               return $response->withJson($item);
             });
  $app->post('/~vendor-lookup',
             function (Request $request, Response $response,
                       \Scat\Service\Catalog $catalog) {
               $item=
                 $catalog->getVendorItemByCode($request->getParam('code'));
               return $response->withJson($item);
             });
});

/* Custom (no controller, just one standalone page) */
$app->get('/custom',
          function (Request $request, Response $response, View $view) {
            return $view->render($response, 'custom/index.html');
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
             [ \Scat\Controler\People::class, 'uploadItems' ]);
  $app->get('/{id:[0-9]+}/loyalty',
            [ \Scat\Controller\People::class, 'loyalty' ]);
});

/* Clock */
$app->group('/clock', function (RouteCollectorProxy $app) {
  $app->get('', [ \Scat\Controller\Timeclock::class, 'home' ]);
  $app->post('/~punch', [ \Scat\Controller\Timeclock::class, 'punch' ]);
});

/* Gift Cards */
$app->group('/gift-card', function (RouteCollectorProxy $app) {
  $app->get('',
            function (Request $request, Response $response, View $view) {
              $page_size= 25;

              $cards= \Model::factory('Giftcard')
                        ->select('*')
                        ->select_expr('COUNT(*) OVER()', 'total')
                        ->order_by_desc('id')
                        ->limit($page_size)->offset($page * $page_size)
                        ->where('active', 1)
                        ->find_many();

              return $view->render($response, 'gift-card/index.html', [
                                           'cards' => $cards,
                                           'error' => $request->getParam('error'),
                                          ]);
            });

  $app->get('/lookup',
            function (Request $request, Response $response) {
              $card= $request->getParam('card');
              $card= preg_replace('/^RAW-/', '', $card);
              $id= substr($card, 0, 7);
              $pin= substr($card, -4);
              $card= \Model::factory('Giftcard')
                      ->where('id', $id)
                      ->where('pin', $pin)
                      ->find_one();

              if ($card) {
                return $response->withRedirect("/gift-card/" . $card->card());
              } else {
                return $response->withRedirect("/gift-card?error=not-found");
              }
            });

  $app->post('/create',
            function (Request $request, Response $response) {
              $expires= $request->getParam('expires');
              $txn_id= $request->getParam('txn_id');
              $balance= $request->getParam('balance');

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

              return $response->withJson($card);
            });

  $app->get('/{card:[0-9]+}',
            function (Request $request, Response $response, $card, View $view) {
              $id= substr($card, 0, 7);
              $pin= substr($card, -4);
              $card= \Model::factory('Giftcard')
                      ->where('id', $id)
                      ->where('pin', $pin)
                      ->find_one();

              return $view->render($response, 'gift-card/card.html', [
                                           'card' => $card,
                                          ]);
            });

  $app->get('/{card:[0-9]+}/print',
            function (Request $request, Response $response, $card) {
              $id= substr($card, 0, 7);
              $pin= substr($card, -4);
              $card= \Model::factory('Giftcard')
                      ->where('id', $id)
                      ->where('pin', $pin)
                      ->find_one();

              $body= $response->getBody();
              $body->write($card->getPDF());
              return $response->withHeader("Content-type", "application/pdf");
            });

  $app->get('/{card:[0-9]+}/email-form',
            function (Request $request, Response $response, $card,
                      \Scat\Service\Txn $txn, View $view) {
              $txn= $txn->fetchById($request->getParam('id'));
              return $view->render($response, 'dialog/email-gift-card.html',
                                         [ "card" => $card ]);
            });
  $app->post('/{card:[0-9]+}/email',
            function (Request $request, Response $response, $card, View $view) {
              $id= substr($card, 0, 7);
              $pin= substr($card, -4);
              $card= \Model::factory('Giftcard')
                      ->where('id', $id)
                      ->where('pin', $pin)
                      ->find_one();

              $email_body= $view->fetch('email/gift-card.html',
                                              $request->getParams());
              $subject= $view->fetchBlock('email/gift-card.html',
                                                'title',
                                                $request->getParams());

              $giftcard_pdf= $card->getPDF();

              // XXX fix hardcoded name
              $from= $from_name ? "$from_name via Raw Materials Art Supplies"
                                : "Raw Materials Art Supplies";
              $to_name= $request->getParam('to_name');
              $to_email= $request->getParam('to_email');

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

              return $response->withJson([ 'message' => 'Success!' ]);
            });

  $app->post('/{card:[0-9]+}/add-txn',
            function (Request $request, Response $response, $card) {
              $id= substr($card, 0, 7);
              $pin= substr($card, -4);
              $card= \Model::factory('Giftcard')
                      ->where('id', $id)
                      ->where('pin', $pin)
                      ->find_one();
              $card->add_txn($request->getParam('amount'),
                             $request->getParam('txn_id'));
              return $response->withJson($card);
            });
});
/* Two extras used by Ordure */
$app->get('/~gift-card/check-balance',
          function (Request $request, Response $response,
                    \Scat\Service\Giftcard $giftcard) {
            $card= $request->getParam('card');
            return $response->withJson($giftcard->check_balance($card));
          });

$app->get('/~gift-card/add-txn',
          function (Request $request, Response $response,
                    \Scat\Service\Giftcard $giftcard) {
            $card= $request->getParam('card');
            $amount= $request->getParam('amount');
            return $response->withJson($giftcard->add_txn($card, $amount));
          });


/* Reports */
$app->group('/report', function (RouteCollectorProxy $app) {
  $app->get('/quick',
            function (Request $request, Response $response,
                      \Scat\Service\Report $report, View $view) {
              $data= $report->sales();
              return $view->render($response, 'dialog/report-quick.html',
                                         $data);
            });
  $app->get('/empty-products',
            function (Request $request, Response $response,
                      \Scat\Service\Report $report, View $view) {
              $data= $report->emptyProducts();
              return $view->render($response, 'report/empty-products.html',
                                         $data);
            });
  $app->get('/{name}',
            function (Request $request, Response $response, $name, View $view) {
              ob_start();
              include "../old-report/report-$name.php";
              $content= ob_get_clean();
              return $view->render($response, 'report/old.html', [
                'title' => $GLOBALS['title'],
                'content' => $content,
              ]);
            });
});

/* Media */
$app->get('/media',
          function (Request $request, Response $response, View $view) {
            $page= (int)$request->getParam('page');
            $page_size= 20;
            $media= \Model::factory('Image')
              ->order_by_desc('created_at')
              ->limit($page_size)->offset($page * $page_size)
              ->find_many();
            $total= \Model::factory('Image')->count();

            return $view->render($response, 'media/index.html', [
                                         'media' => $media,
                                         'page' => $page,
                                         'page_size' => $page_size,
                                         'total' => $total,
                                        ]);
          });
$app->post('/media/add',
           function (Request $request, Response $response) {
             $url= $request->getParam('url');
             if ($url) {
               $image= \Scat\Model\Image::createFromUrl($url);
             } else {
               foreach ($request->getUploadedFiles() as $file) {
                 $image= \Scat\Model\Image::createFromStream($file->getStream(),
                                                       $file->getClientFilename());
               }
             }

             return $response->withJson($image);
           });
$app->post('/media/{id}/update',
           function (Request $request, Response $response, $id) {
             \ORM::get_db()->beginTransaction();

             $image= \Model::factory('Image')->find_one($id);
             if (!$image) {
               throw new \Slim\Exception\HttpNotFoundException($request);
             }
             $image->alt_text= $request->getParam('caption');
             $image->save();

             \ORM::get_db()->commit();

             return $response->withJson($image);
           });

/* Notes */
$app->get('/notes',
          function (Request $request, Response $response, View $view) {
            $parent_id= (int)$request->getParam('parent_id');
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
            return $view->render($response, 'dialog/notes.html',
                                       [
                                         'body_only' =>
                                           (int)$request->getParam('body_only'),
                                         'parent_id' => $parent_id,
                                         'staff' => $staff,
                                         'notes' => $notes
                                       ]);
          });
$app->post('/notes/add',
           function (Request $request, Response $response,
                     \Scat\Service\Phone $phone) {
             $note= \Model::factory('Note')->create();
             $note->parent_id= (int)$request->getParam('parent_id');
             $note->person_id= (int)$request->getParam('person_id');
             $note->content= $request->getParam('content');
             $note->todo= (int)$request->getParam('todo');
             $note->public= (int)$request->getParam('public');
             $note->save();

             if ((int)$request->getParam('sms')) {
               try {
                 $txn= $note->parent()->find_one()->txn();
                 $person= $txn->owner();
                 error_log("Sending message to {$person->phone}");
                 $data= $phone->sendSMS($person->phone,
                                              $request->getParam('content'));
                 $note->public= true;
                 $note->save();
                } catch (\Exception $e) {
                  error_log("Got exception: " . $e->getMessage());
                }
             }

             return $response->withJson($note);
           });
$app->post('/notes/{id}/update',
           function (Request $request, Response $response, $id) {
             \ORM::get_db()->beginTransaction();

             $note= \Model::factory('Note')->find_one($id);
             if (!$note) {
               throw new \Slim\Exception\HttpNotFoundException($request);
             }

             $todo= $request->getParam('todo');
             if ($todo !== null && $todo != $note->todo) {
               $note->todo= (int)$request->getParam('todo');
               $update= \Model::factory('Note')->create();
               // TODO who did this?
               $update->parent_id= $note->parent_id ?: $note->id;
               $update->content= $todo ? "Marked todo." : "Marked done.";
               $update->save();
             }

             $public= $request->getParam('public');
             if ($public !== null && $public != $note->public) {
               $note->public= (int)$request->getParam('public');
               $update= \Model::factory('Note')->create();
               // TODO who did this?
               $update->parent_id= $note->parent_id ?: $note->id;
               $update->content= $public ? "Marked public." : "Marked private.";
               $update->save();
             }

             $note->save();

             \ORM::get_db()->commit();

             return $response->withJson($note);
           });

/* Till */
$app->group('/till', function (RouteCollectorProxy $app) {
  $app->get('',
            function (Request $request, Response $response, View $view) {
              $q= "SELECT CAST(SUM(amount) AS DECIMAL(9,2)) AS expected
                     FROM payment
                    WHERE method IN ('cash','change','withdrawal')";
              $data= \ORM::for_table('payment')->raw_query($q)->find_one();
              return $view->render($response, "till/index.html", [
                'expected' => $data->expected,
              ]);
            });

  $app->post('/~print-change-order',
             function (Request $request, Response $response,
                       \Scat\Service\Printer $printer) {
               return $printer->printFromTemplate(
                 $response, 'receipt',
                 'print/change-order.html',
                 $request->getParams()
               );
             });

  $app->post('/~count',
             function (Request $request, Response $response,
                       \Scat\Service\Txn $txn) {
               if ($request->getAttribute('has_errors')) {
                 return $response->withJson([
                   'error' => "Validation failed.",
                   'validation_errors' => $request->getAttribute('errors')
                 ]);
               }

               $counted= $request->getParam('counted');
               $withdraw= $request->getParam('withdraw');

               $q= "SELECT CAST(SUM(amount) AS DECIMAL(9,2)) AS expected
                      FROM payment
                     WHERE method IN ('cash','change','withdrawal')";
               $data= \ORM::for_table('payment')->raw_query($q)->find_one();
               $expected= $data->expected;

               \ORM::get_db()->beginTransaction();

               $txn= $txn->create([ 'type' => 'drawer' ]);

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
               return $response->withJson(['expected' => $data->expected ]);
             })
      ->add(new Validation([
        'counted' => v::numeric()::positive(),
        'withdraw' => v::numeric(),
      ]));

  $app->post('/~withdraw-cash',
             function (Request $request, Response $response,
                       \Scat\Service\Txn $txn) {
               if ($request->getAttribute('has_errors')) {
                 return $response->withJson([
                   'error' => "Validation failed.",
                   'validation_errors' => $request->getAttribute('errors')
                 ]);
               }

               $reason= $request->getParam('reason');
               $amount= $request->getParam('amount');

               \ORM::get_db()->beginTransaction();

               $txn= $txn->create([ 'type' => 'drawer' ]);

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

               return $response->withJson($txn);
             })
      ->add(new Validation([
        'amount' => v::numeric()::positive(),
        'reason' => v::stringType()::notOptional(),
      ]));
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
  $app->get('/~ping',
             function (Request $request, Response $response,
                       \Scat\Service\Tax $tax) {
                return $response->withJson($tax->ping());
             });
});

/* SMS */
$app->group('/sms', function (RouteCollectorProxy $app) {
  $app->map(['GET','POST'], '/~send',
            [ \Scat\Controller\SMS::class, 'send' ]);
  $app->post('/~receive',
             [ \Scat\Controller\SMS::class, 'receive' ]);
  $app->get('/~register', [ \Scat\Controller\SMS::class, 'register' ]);

});

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
