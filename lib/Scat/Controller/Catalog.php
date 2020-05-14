<?php
namespace Scat\Controller;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Catalog {
  private $catalog, $view;

  public function __construct(\Scat\Service\Catalog $catalog, View $view) {
    $this->catalog= $catalog;
    $this->view= $view;
  }

  public function search(Request $request, Response $response,
                          \Scat\Service\Search $search)
  {
    $q= trim($request->getParam('q'));
    $scope= $request->getParam('scope');

    if ($scope == 'items') {
      $items= $search->searchItems($q);

      /*
        Fallback: if we found nothing and it looks like a barcode, try
        searching for an exact match on the barcode to catch items
        inadvertantly set inactive.
      */
      if (count($items) == 0 && preg_match('/^[-0-9x]+$/i', $q)) {
        $items= $search->searchItems("barcode:\"$q\" active:0");
      }

      $data= [ 'items' => $items ];
    } else {
      $data= $search->search($q);
    }

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/json') !== false) {
      return $response->withJson($data);
    }

    $data['depts']= $this->catalog->getDepartments();
    $data['q']= $q;

    return $this->view->render($response, 'catalog/searchresults.html', $data);
  }

  public function reindex(Request $request, Response $response,
                          \Scat\Service\Search $search)
  {
    $search->flush();

    $rows= 0;
    foreach ($this->catalog->getProducts() as $product) {
      $rows+= $search->indexProduct($product);
    }

    $response->getBody()->write("Indexed $rows rows.");
    return $response;
  }

  public function custom(Request $request, Response $response) {
    $depts= $this->catalog->getDepartments();
    return $this->view->render($response, 'catalog/custom.html', [
      'depts' => $depts,
    ]);
  }

  public function whatsNew(Request $request, Response $response) {
    $limit= (int)$request->getParam('limit');
    if (!$limit) $limit= 12;

    $products= $this->catalog->getNewProducts($limit);
    $depts= $this->catalog->getDepartments();

    return $this->view->render($response, 'catalog/whatsnew.html', [
      'products' => $products,
      'depts' => $depts,
    ]);
  }

  public function brand(Request $request, Response $response, $brand= null) {
    $depts= $this->catalog->getDepartments();

    $brandO= $brand ? $this->catalog->getBrandBySlug($brand) : null;
    if ($brand && !$brandO)
      throw new \Slim\Exception\HttpNotFoundException($request);

    if ($brandO)
      $products= $brandO->products()
                       ->order_by_asc('name')
                       ->where('product.active', 1)
                       ->find_many();

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/json') !== false) {
      return $response->withJson($brandO);
    }
    if (strpos($accept, 'application/vnd.scat.dialog+html') !== false) {
      return $this->view->render($response, 'dialog/brand-edit.html', [
        'brand' => $brandO
      ]);
    }

    $brands= $brandO ? null : $this->catalog->getBrands();

    return $this->view->render($response, 'catalog/brand.html', [
      'depts' => $depts,
      'brands' => $brands,
      'brand' => $brandO,
      'products' => $products
    ]);
  }

  public function brandUpdate(Request $request, Response $response, $brand= null)
  {
    $brandO= $brand ? $this->catalog->getBrandBySlug($brand) : null;
    if ($brand && !$brandO)
      throw new \Slim\Exception\HttpNotFoundException($request);

    if (!$brandO)
      $brandO= $this->catalog->createBrand();

    $brandO->name= $request->getParam('name');
    $brandO->slug= $request->getParam('slug');
    $brandO->description= $request->getParam('description');
    $brandO->active= (int)$request->getParam('active');
    $brandO->save();

    if ($brand) {
      return $response->withJson($brandO);
    } else {
      return $response->withRedirect('/catalog/brand/' . $brandO->slug);
    }
  }


  public function dept(Request $request, Response $response, $id= null) {
    $dept= $this->catalog->getDepartmentById($id);

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/vnd.scat.dialog+html') !== false) {
      $depts= $this->catalog->getDepartments();

      return $this->view->render($response, 'dialog/department-edit.html', [
        'parent_id' => $request->getParam('parent_id'),
        'depts' => $depts,
        'dept' => $dept
      ]);
    }

    // Can get form to create new department, but not other representations
    if (!$id && !$dept)
      throw new \Slim\Exception\HttpNotFoundException($request);

    if (strpos($accept, 'application/json') !== false) {
      return $response->withJson($dept);
    }

    $routeContext= \Slim\Routing\RouteContext::fromRequest($request);
    $url= $routeContext->getRouteParser()->urlFor('catalog', [
      'dept' => $dept->slug,
    ]);
    return $response->withRedirect($url);
  }

  public function deptUpdate(Request $request, Response $response, $id= null)
  {
    $dept= $id ? $this->catalog->getDepartmentById($id) : null;
    if ($id && !$dept)
      throw new \Slim\Exception\HttpNotFoundException($request);

    if (!$dept)
      $dept= $this->catalog->createDepartment();

    $dept->name= $request->getParam('name');
    $dept->parent_id= $request->getParam('parent_id');
    $dept->slug= $request->getParam('slug');
    $dept->description= $request->getParam('description');
    $dept->active= (int)$request->getParam('active');
    $dept->save();

    if ($id) {
      return $response->withJson($dept);
    } else {
      $routeContext= \Slim\Routing\RouteContext::fromRequest($request);
      $url= $routeContext->getRouteParser()->urlFor('catalog', [
        'dept' => $dept->slug,
      ]);
      return $response->withRedirect($url);
    }
  }

  public function product(Request $request, Response $response, $id= null) {
    $product= $this->catalog->getProductById($id);

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/vnd.scat.dialog+html') !== false) {
      return $this->view->render($response, 'dialog/product-edit.html', [
        'department_id' => $request->getParam('department_id'),
        'depts' => $this->catalog->getDepartments(),
        'brands' => $this->catalog->getBrands(),
        'product' => $product
      ]);
    }

    // Can get form to create new product, but not other representations
    if (!$id && !$product)
      throw new \Slim\Exception\HttpNotFoundException($request);

    if (strpos($accept, 'application/json') !== false) {
      return $response->withJson($product);
    }

    $routeContext= \Slim\Routing\RouteContext::fromRequest($request);
    $url= $routeContext->getRouteParser()->urlFor('catalog', [
      'dept' => $product->dept()->parent()->slug,
      'subdept' => $product->dept()->slug,
      'product' => $product->slug,
    ]);
    return $response->withRedirect($url);
  }

  public function productUpdate(Request $request, Response $response,
                                \Scat\Service\Search $search, $id= null)
  {
    $product= $id ? $this->catalog->getProductById($id) : null;
    if ($id && !$product)
      throw new \Slim\Exception\HttpNotFoundException($request);

    if (!$product)
      $product= $this->catalog->createProduct();

    foreach ($product->getFields() as $field) {
      $value= $request->getParam($field);
      if (isset($value)) {
        $product->set($field, $value);
      }
    }
    $product->save();

    $search->indexProduct($product);

    if ($id) {
      return $response->withJson($product);
    } else {
      $routeContext= \Slim\Routing\RouteContext::fromRequest($request);
      $url= $routeContext->getRouteParser()->urlFor('catalog', [
        'dept' => $product->dept()->parent()->slug,
        'subdept' => $product->dept()->slug,
        'product' => $product->slug,
      ]);
      return $response->withRedirect($url);
    }
  }

  public function productAddMedia(Request $request, Response $response, $id) {
    $product= $id ? $this->catalog->getProductById($id) : null;
    if ($id && !$product)
      throw new \Slim\Exception\HttpNotFoundException($request);

    // TODO should be a Media service for this
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
  }

  public function item(Request $request, Response $response, $code= null) {
    $item= $code ? $this->catalog->getItemByCode($code) : null;
    if ($code && !$item)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/vnd.scat.dialog+html') !== false) {
      return $this->view->render($response, 'dialog/item-add.html', [
        'product_id' => $request->getParam('product_id'),
        'item' => $item,
      ]);
    }

    // Can get form to create new item, but not other representations
    if (!$id && !$item)
      throw new \Slim\Exception\HttpNotFoundException($request);

    if (strpos($accept, 'application/json') !== false) {
      return $response->withJson($item);
    }

    return $this->view->render($response, 'catalog/item.html', [
      'item' => $item,
    ]);
  }

  public function updateItem(Request $request, Response $response,
                              $code= null)
  {
    \ORM::get_db()->beginTransaction();

    $item= $code ? $this->catalog->getItemById($code) : null;
    if ($code && !$item)
      throw new \Slim\Exception\HttpNotFoundException($request);

    if (!$item)
      $item= $this->catalog->createItem();

    foreach ($item->getFields() as $field) {
      if ($field == 'id') continue;
      $value= $request->getParam($field);
      if (strlen($value)) {
        $item->setProperty($field, $value);
      }
    }

    if (($id= $request->getParam('vendor_item'))) {
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
        $barcode->item_id= $item->id;
        $barcode->save();
      }
      if (!$vendor_item->item) {
        $vendor_item->item_id= $item->id;
        $vendor_item->save();
      }
    }

    \ORM::get_db()->commit();

    return $response->withJson([
      'item' => $item,
      'newValue' => $item->$name,
      'replaceRow' => $this->view->fetch('catalog/item-row.twig', [
                       'i' => $item,
                       'variations' => $item->variation,
                       'product' => $item->product_id,
                     ])
    ]);
  }

  public function printItemLabel(Request $request, Response $response, $code) {
    $item= $this->catalog->getItemByCode($code);
    if (!$item)
     throw new \Slim\Exception\HttpNotFoundException($request);

    $body= $response->getBody();
    $body->write($item->getPDF($request->getParams()));
    return $response->withHeader("Content-type", "application/pdf");
  }

  public function addItemBarcode(Request $request, Response $response, $code) {
    $item= $this->catalog->getItemByCode($code);
    if (!$item)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $barcode= $item->barcodes()->create();
    $barcode->item_id= $item->id;
    $barcode->code= trim($request->getParam('barcode'));
    $barcode->quantity= 1;
    $barcode->save();

    return $response->withJson($item);
  }

  public function updateItemBarcode(Request $request, Response $response,
                                    $code, $barcode)
  {
    $item= $this->catalog->getItemByCode($code);
    if (!$item)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $barcode= $item->barcodes()
      ->where('code', $barcode)
      ->find_one();

    if (!$barcode)
      throw new \Slim\Exception\HttpNotFoundException($request);

    foreach ($barcode->getFields() as $field) {
      $value= $request->getParam($field);
      if (isset($value)) {
        $barcode->set($field, $value);
      }
    }

    $barcode->save();

    return $response->withJson($item);
  }

  public function deleteItemBarcode(Request $request, Response $response,
                                    $code, $barcode)
  {
    $item= $this->catalog->getItemByCode($code);
    if (!$item)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $barcode= $item->barcodes()
      ->where('code', $barcode)
      ->find_one();

    if (!$barcode)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $barcode->delete();

    return $response->withJson($item);
  }

  function bulkItemUpdate(Request $request, Response $response) {
    $items= $request->getParam('items');

    if (!preg_match('/^(?:\d+)(?:,\d+)*$/', $items)) {
      throw new \Exception("Invalid items specified.");
    }

    foreach (explode(',', $items) as $id) {
      $item= $this->catalog->getItemById($id);
      if (!$item)
        throw new \Slim\Exception\HttpNotFoundException($request);

      foreach ($item->getFields() as $field) {
        if ($field == 'id') continue;
        $value= $request->getParam($field);
        if (strlen($value)) {
          $item->setProperty($field, $value);
        }
      }

      $item->save();
    }

    return $response->withJson([ 'message' => 'Okay.' ]);
  }

  public function findVendorItems(Request $request, Response $response, $code) {
    $item= $this->catalog->getItemByCode($code);
    if (!$item)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $item->findVendorItems();

    return $response->withJson($item);
  }

  public function unlinkVendorItem(Request $request, Response $response,
                                    $code, $id)
  {
    $item= $this->catalog->getItemByCode($code);
    if ($code && !$item)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $vendor_item= $item->vendor_items()->find_one($id);
    if (!$vendor_item)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $vendor_item->item_id= 0;
    $vendor_item->save();

    return $response->withJson($vendor_item);
  }

  public function vendorItem(Request $request, Response $response,
                              $id= null)
  {
    $vendor_item= $id ? $this->catalog->getVendorItemById($id) : null;
    if ($id && !$vendor_item)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $item_id= $request->getParam('item_id');
    $item= $item_id ? $this->catalog->getItemById($item_id) : null;

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/vnd.scat.dialog+html') !== false) {
      return $this->view->render($response, 'dialog/vendor-item-edit.html', [
        'item' => $item,
        'vendor_item' => $vendor_item,
      ]);
    }

    // Can get form to create new item, but not other representations
    if (!$vendor_item)
      throw new \Slim\Exception\HttpNotFoundException($request);

    if (strpos($accept, 'application/json') !== false) {
      return $response->withJson($vendor_item);
    }

    // TODO add HTML pages for vendor items?
    throw new \Slim\Exception\HttpNotFoundException($request);
  }

  public function vendorItemSearch(Request $request, Response $response) {
    $item= $this->catalog->getVendorItemByCode($request->getParam('code'));
    return $response->withJson($item);
  }

  public function updateVendorItem(Request $request, Response $response,
                                    $id= null)
  {
    $vendor_item= $id ? $this->catalog->getVendorItemById($id) : null;
    if ($id && !$vendor_item)
      throw new \Slim\Exception\HttpNotFoundException($request);

    if (!$vendor_item)
      $vendor_item= $this->catalog->createVendorItem();

    foreach ($vendor_item->getFields() as $field) {
      $value= $request->getParam($field);
      if (isset($value)) {
        $vendor_item->set($field, $value);
      }
    }

    $vendor_item->save();

    return $response->withJson($vendor_item);
  }

  public function vendorItemStock(Request $request, Response $response, $id) {
    $vendor_item= $id ? $this->catalog->getVendorItemById($id) : null;
    if ($id && !$vendor_item)
      throw new \Slim\Exception\HttpNotFoundException($request);

    return $response->withJson($vendor_item->checkVendorStock());
  }

  public function catalogPage(Request $request, Response $response,
                              $dept= null, $subdept= null, $product= null)
  {
    try {
      $depts= $this->catalog->getDepartments();
      $deptO= $dept ?
        $this->catalog->getDepartmentBySlug($dept) : null;

      if ($dept && !$deptO)
        throw new \Slim\Exception\HttpNotFoundException($request);

      $subdepts= $deptO ?
        $deptO->departments()->order_by_asc('name')->find_many() : null;

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
                                   array('product.brand_id', '=', 'brand.id'))
                 ->order_by_asc('brand.name')
                 ->order_by_asc('product.name')
                 ->find_many() : null;

      $productO= $product ?
        $subdeptO->products(false)
                 ->where('slug', $product)
                 ->find_one() : null;
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

      $brands= $deptO ? null : $this->catalog->getBrands();

      return $this->view->render($response, 'catalog/layout.html',
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
      $re= $this->catalog->getRedirectFrom($path);

      if ($re) {
        return $response->withRedirect('/catalog/' . $re->dest, 301);
      }

      throw $ex;
    }
  }
}
