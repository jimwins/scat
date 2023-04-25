<?php
namespace Scat\Controller;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Catalog {
  private $catalog, $view, $data;

  public function __construct(
    \Scat\Service\Catalog $catalog,
    \Scat\Service\Data $data,
    View $view
  ) {
    $this->catalog= $catalog;
    $this->data= $data;
    $this->view= $view;
  }

  public function search(Request $request, Response $response,
                          \Scat\Service\Txn $txn,
                          \Scat\Service\Search $search)
  {
    $q= trim($request->getParam('q'));
    $scope= $request->getParam('scope');
    $limit= $request->getParam('limit');

    if (preg_match('/^((%V|@)INV-)(\d+)/i', $q, $m)) {
      $match= $txn->fetchById($m[3]);
      if ($match) {
        return $response->withRedirect(
          ($match->type == 'customer' ? '/sale/' : '/purchase/') . $match->id
        );
      }
    }

    if ($scope == 'items') {
      $items= $search->searchItems($q, $limit);

      /*
        Fallback: if we found nothing and it looks like a barcode, try
        searching for an exact match on the barcode to catch items
        inadvertantly set inactive.
      */
      if (count($items) == 0 && preg_match('/^[-0-9x]+$/i', $q)) {
        $items= $search->searchItems("barcode:\"$q\" active:0");
      }

      $data= [ 'items' => $items ];
    } elseif ($scope == 'products') {
      $products= $search->searchProducts($q, $limit);

      $data= [ 'products' => $products ];
    } else {
      $data= $search->search($q, $limit);
    }

    $accept= $request->getHeaderLine('Accept');
    $xrequestedwith= $request->getHeaderLine('X-Requested-With');
    if (strpos($accept, 'application/json') !== false ||
        strtolower($xrequestedwith) === 'xmlhttprequest')
    {
      return $response->withJson($data);
    }

    $data['depts']= $this->catalog->getDepartments();
    $data['q']= $q;

    if (!count($data['products']) && count($data['items']) == 1) {
      return $response->withRedirect(
        '/catalog/item/' . $data['items'][0]->code . '?q=' . rawurlencode($q)
      );
    }

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

  public function markInventoried(Request $request, Response $response)
  {
    $items= $request->getParam('items');

    // Validate input
    if (!preg_match('/(\d+)(\d+,)*/', $items)) {
      throw new \Exception("Invalid list of items.");
    }

    $q= "UPDATE item SET inventoried = NOW() WHERE id IN ($items)";
    $this->data->execute($q);

    $last= $this->data->get_last_statement();

    return $response->withJson([ 'count' => $last->rowCount() ]);
  }

  public function printCountSheet(Request $request, Response $response,
                                  \Scat\Service\Printer $print)
  {
    $id_list= $request->getParam('items');
    $q= $request->getParam('q');

    // Validate input
    if (!preg_match('/(\d+)(\d+,)*/', $id_list)) {
      throw new \Exception("Invalid list of items.");
    }

    $ids= preg_split('/,/', $id_list);

    $items= $this->catalog->getItems()
                          ->where_in('id', $ids)
                          ->order_by_asc('code')
                          ->find_many();

    $product_id= $items[0]->product_id;
    $variation= $items[0]->variation;
    $use_short_name= true;
    $use_variation= false;

    foreach ($items as $item) {
      if ($item->product_id != $product_id) {
        $use_short_name= false;
      }
      if ($item->variation != $variation) {
        $use_variation= true;
      }
    }

    $product= ($use_short_name ? $items[0]->product() : null);

    if ($product && !$q) {
      $q= "product:{$product->id} stocked:1";
    }

    $pdf= $print->generateFromTemplate('print/inventory.html', [
      'items' => $items,
      'product' => $product,
      'use_short_name' => $use_short_name,
      'use_variation' => $use_variation,
      'q' => $q,
    ]);

    if ($request->getParam('download')) {
      $response->getBody()->write($pdf);
      return $response->withHeader('Content-type', 'application/pdf');
    }

    return $print->printPDF($response, 'letter', $pdf);
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

    $products= null;
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
    $brandO->warning= $request->getParam('warning');
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
    $dept->featured= (int)$request->getParam('featured');
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

  public function productEditMedia(Request $request, Response $response,
                                    \Scat\Service\Media $media, $id)
  {
    $product= $id ? $this->catalog->getProductById($id) : null;
    if ($id && !$product)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $grabs= [];
    if ($request->getParam('grab')) {
      foreach($product->items()->find_many() as $item) {
        $grabs= array_merge($item->media(), $grabs);
      }
    }

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/vnd.scat.dialog+html') !== false) {
      return $this->view->render($response, 'dialog/media.html', [
        'product' => $product,
        'media' => $product->media(),
        'related' => $grabs,
      ]);
    }

    return $response->withJson($product->media->find_many());
  }

  public function productAddMedia(Request $request, Response $response,
                                  \Scat\Service\Media $media, $id)
  {
    $product= $id ? $this->catalog->getProductById($id) : null;
    if ($id && !$product)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $url= $request->getParam('url');
    $media_id= $request->getParam('media_id');

    if ($media_id) {
      $image= $media->findById($media_id);
      if (!$image)
        throw new \Slim\Exception\HttpNotFoundException($request);
      $product->addImage($image);
    } elseif ($url) {
      $image= $media->createFromUrl($url);
      $product->addImage($image);
    } else {
      foreach ($request->getUploadedFiles() as $file) {
        if ($file->getError() != UPLOAD_ERR_OK) {
          throw new \Scat\Exception\FileUploadException($file->getError());
        }
        $image= $media->createFromStream($file->getStream(),
                                          $file->getClientFilename());
        $product->addImage($image);
      }
    }

    return $response->withJson($product);
  }

  public function productUnlinkMedia(Request $request, Response $response,
                                      \Scat\Service\Media $media,
                                      $id, $image_id)
  {
    $product= $id ? $this->catalog->getProductById($id) : null;
    if ($id && !$product)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $link= $product->media_link()->where('image_id', $image_id)->find_one();

    if (!$link) {
      throw new \Slim\Exception\HttpNotFoundException($request);
    }

    $link->delete();

    return $response->withJson([]);
  }

  public function item(Request $request, Response $response, $code= null) {
    $item= $code ? $this->catalog->getItemByCode($code) : null;
    if ($code && !$item)
      throw new \Slim\Exception\HttpNotFoundException($request);
    $id= null;

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/vnd.scat.dialog+html') !== false) {
      if (($id= $request->getParam('vendor_item_id'))) {
        $vendor_item= $this->catalog->getVendorItemById($id);
      }

      return $this->view->render($response, 'dialog/item-add.html', [
        'product_id' => $request->getParam('product_id'),
        'vendor_item' => $vendor_item ?? null,
        'item' => $item,
      ]);
    }

    // Can get form to create new item, but not other representations
    if (!$id && !$item)
      throw new \Slim\Exception\HttpNotFoundException($request);

    if (strpos($accept, 'application/json') !== false) {
      return $response->withJson($item);
    }

    if (($row= $request->getParam('row'))) {
      return $this->view->render($response, 'catalog/item-row.twig', [
        'i' => $item,
      ]);
    }

    if (($block= $request->getParam('block'))) {
      $html= $this->view->fetchBlock('catalog/item.html', $block, [
        'item' => $item,
      ]);

      $response->getBody()->write($html);
      return $response;
    }

    return $this->view->render($response, 'catalog/item.html', [
      'item' => $item,
      'q' => $request->getParam('q'),
    ]);
  }

  public function updateItem(Request $request, Response $response,
                              $code= null)
  {
    $this->data->beginTransaction();

    $item= $code ? $this->catalog->getItemByCode($code) : null;
    if ($code && !$item)
      throw new \Slim\Exception\HttpNotFoundException($request);

    if (!$item)
      $item= $this->catalog->createItem();

    foreach ($item->getFields() as $field) {
      if ($field == 'id') continue;
      $value= $request->getParam($field);
      if ($value !== null) {
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

    if (isset($vendor_item)) {
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

    $this->data->commit();

    /* If the code changed, redirect to the new resource */
    if ($code != $item->code) {
      return $response->withRedirect('/catalog/item/' . $item->code);
    }

    return $response->withJson($item);
  }

  public function bulkAddItems(Request $request, Response $response) {
    $this->data->beginTransaction();

    $items= $request->getParam('items');

    foreach (explode(',', $items) as $item) {
      $vendor_item= $this->catalog->getVendorItemById($item);
      if (!$vendor_item)
        throw new \Slim\Exception\HttpNotFoundException($request);

      $item= $this->catalog->createItem();
      $item->code= trim($vendor_item->code);
      $item->name= trim($vendor_item->name);
      $item->retail_price= $vendor_item->retail_price;
      $item->purchase_quantity= $vendor_item->purchase_quantity;
      $item->length= $vendor_item->length;
      $item->width= $vendor_item->width;
      $item->height= $vendor_item->height;
      $item->weight= $vendor_item->weight;
      $item->prop65= $vendor_item->prop65;
      $item->hazmat= $vendor_item->hazmat;
      $item->oversized= $vendor_item->oversized;

      $item->save();

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

    $this->data->commit();

    return $response->withJson($item);
  }

  public function printItemLabel(Request $request, Response $response, $code) {
    $item= $this->catalog->getItemByCode($code);
    if (!$item)
     throw new \Slim\Exception\HttpNotFoundException($request);

    $body= $response->getBody();
    $body->write($item->getPDF($request->getParams()));
    return $response->withHeader("Content-type", "application/pdf");
  }

  public function mergeItem(Request $request, Response $response, $code) {
    $item= $this->catalog->getItemByCode($code);
    if (!$item)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $from= $this->catalog->getItemByCode($request->getParam('from'));
    if (!$from)
      throw new \Slim\Exception\HttpNotFoundException($request);

    if ($item->id == $from->id)
      throw new \Exception("Can't merge an item into itself");

    $this->data->beginTransaction();

    /* Move over barcodes */
    $q= "UPDATE IGNORE barcode
            SET item_id = {$item->id}
          WHERE item_id = {$from->id}";
    $this->data->execute($q);

    /* Leftovers? Nuke them. */
    $q= "DELETE FROM barcode
          WHERE item_id = {$from->id}";
    $this->data->execute($q);

    /* Move over images */
    $q= "UPDATE IGNORE item_to_image
            SET item_id = {$item->id}
          WHERE item_id = {$from->id}";
    $this->data->execute($q);

    /* Leftovers? Nuke them. */
    $q= "DELETE FROM item_to_image
          WHERE item_id = {$from->id}";
    $this->data->execute($q);

    /* Change references to old item to new one */
    $q= "UPDATE txn_line
            SET item_id = {$item->id}
          WHERE item_id = {$from->id}";
    $this->data->execute($q);

    $q= "UPDATE vendor_item
            SET item_id = {$item->id}
          WHERE item_id = {$from->id}";
    $this->data->execute($q);

    $q= "UPDATE loyalty_reward
            SET item_id = {$item->id}
          WHERE item_id = {$from->id}";
    $this->data->execute($q);

    $q= "UPDATE note
            SET attach_id = {$item->id}
          WHERE attach_id = {$from->id}
            AND kind = 'item'";
    $this->data->execute($q);

    $from->delete();

    $this->data->commit();

    return $response->withJson($item);
  }

  public function addItemBarcode(Request $request, Response $response, $code) {
    $item= $this->catalog->getItemByCode($code);
    if (!$item)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $code= trim($request->getParam('barcode'));

    if (preg_match('/^(000000|400400)/', $code)) {
      throw new \Exception("That barcode is for internal use only.");
    }

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

  public function addKitItem(Request $request, Response $response, $code) {
    $item= $this->catalog->getItemByCode($code);
    if (!$item)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $kit_item= $item->kit_items()->create();
    $kit_item->kit_id= $item->id;
    $kit_item->item_id= $request->getParam('id');
    $kit_item->quantity= 1;
    $kit_item->save();

    return $response->withJson($item);
  }

  public function updateKitItem(Request $request, Response $response,
                                $code, $id)
  {
    $item= $this->catalog->getItemByCode($code);
    if (!$item)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $kit_item= $item->kit_items()->find_one($id);

    if (!$kit_item)
      throw new \Slim\Exception\HttpNotFoundException($request);

    foreach ($kit_item->getFields() as $field) {
      $value= $request->getParam($field);
      if (isset($value)) {
        $kit_item->set($field, $value);
      }
    }

    $kit_item->save();

    return $response->withJson($item);
  }

  public function deleteKitItem(Request $request, Response $response,
                                $code, $id)
  {
    $item= $this->catalog->getItemByCode($code);
    if (!$item)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $kit_item= $item->kit_items()->find_one($id);

    if (!$kit_item)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $kit_item->delete();

    return $response->withJson($item);
  }

  public function itemGetMedia(Request $request, Response $response,
                                \Scat\Service\Media $media, $code)
  {
    $item= $code ? $this->catalog->getItemByCode($code) : null;
    if ($code && !$item)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $grabs= [];

    if ($request->getParam('grab')) {
      foreach ($item->vendor_items()->find_many() as $vi) {
        $vendor= $vi->vendor();
        if ($vendor->salsify_url) {
          $search_url= 'https://app.salsify.com/catalogs/api/catalogs/' . $vendor->salsify_url . '/products?filter=%3D&page=1&per_page=36&product_identifier_collection_id=&query=' . rawurlencode($vi->vendor_sku);

          error_log("checking $search_url for images from {$vendor->company}\n");

          $results= json_decode(file_get_contents($search_url));

          if ($results->products) {
            foreach ($results->products as $product) {
              $product_url= 'https://app.salsify.com/catalogs/api/catalogs/' . $vendor->salsify_url . '/products/' . $product->id;
              error_log("checking $product_url for images\n");
              $details= json_decode(file_get_contents($product_url));

              $grabs= array_merge($grabs, $details->asset_properties);
            }
          }
        }
      }
    }

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/vnd.scat.dialog+html') !== false) {
      return $this->view->render($response, 'dialog/media.html', [
        'item' => $item,
        'grabs' => $grabs,
        'media' => $item->media(),
      ]);
    }

    return $response->withJson($item->media());
  }

  public function itemAddMedia(Request $request, Response $response,
                                \Scat\Service\Media $media, $code)
  {
    $item= $code ? $this->catalog->getItemByCode($code) : null;
    if ($code && !$item)
      throw new \Slim\Exception\HttpNotFoundException($request);

    // TODO should be a Media service for this
    $url= $request->getParam('url');
    if ($url) {
      $image= $media->createFromUrl($url);
      $item->addImage($image);
    } else {
      foreach ($request->getUploadedFiles() as $file) {
        if ($file->getError() != UPLOAD_ERR_OK) {
          throw new \Scat\Exception\FileUploadException($file->getError());
        }
        $image= $media->createFromStream($file->getStream(),
                                          $file->getClientFilename());
        $item->addImage($image);
      }
    }

    return $response->withJson($item);
  }

  public function itemUnlinkMedia(Request $request, Response $response,
                                  \Scat\Service\Media $media,
                                  $code, $image_id)
  {
    $item= $code ? $this->catalog->getItemByCode($code) : null;
    if ($code && !$item)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $link= $item->media_link()->where('image_id', $image_id)->find_one();

    if (!$link) {
      throw new \Slim\Exception\HttpNotFoundException($request);
    }

    $link->delete();

    return $response->withJson([]);
  }

  public function itemGetGoogleHistory(Request $request, Response $response,
                                        \Scat\Service\Google $google, $code)
  {
    $item= $code ? $this->catalog->getItemByCode($code) : null;
    if ($code && !$item)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $data= $google->getItemShoppingHistory($item->code);

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/vnd.scat.dialog+html') !== false) {
      return $this->view->render($response, 'dialog/item-google-history.html', [
        'item' => $item,
        'history' => $data,
      ]);
    }

    return $response->withJson($data);
  }

  public function itemGetShippingEstimate(Request $request, Response $response,
                                          \Scat\Service\Shipping $shipping,
                                          $code)
  {
    $item= $code ? $this->catalog->getItemByCode($code) : null;
    if ($code && !$item)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $addresses= \Scat\Service\Shipping::$test_addresses;

    $box= $shipping->get_shipping_box([ [
      'length' => $item->length,
      'width' => $item->width,
      'height' => $item->height,
    ]]);

    $data= [];
    foreach ($addresses as $address) {
      $data[]= [
        'address' =>
          "{$address['city']}, {$address['state']} {$address['zip']}",
        'rate' => $shipping->get_shipping_estimate($box, $item->weight, $item->hazmat, $address)
      ];
    }

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/vnd.scat.dialog+html') !== false) {
      return $this->view->render($response,
                                  'dialog/item-shipping-estimates.html', [
        'item' => $item,
        'data' => $data,
      ]);
    }

    return $response->withJson($data);
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
        // Important: check that value is not empty!
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
                              $dept= null, $subdept= null, $product= null,
                              $item= null)
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

      $itemO= $item ?
        $productO->items()
                 ->where('code', $item)
                 ->find_one() : null;
      if ($item && !$itemO)
        throw new \Slim\Exception\HttpNotFoundException($request);

      $items= ($productO && !$item) ?
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

      $variations= null;
      if ($items) {
        $variations= array_unique(
          array_map(function ($i) {
            return $i->variation;
          }, $items));

        /* If the only variation is '', we don't really have any */
        if (count($variations) == 1 && $variations[0] == '') {
          $variations= null;
        }

        if ($request->getAttribute('no_solo_item') && count($items) == 1) {
          $routeContext= \Slim\Routing\RouteContext::fromRequest($request);
          $routeParser= $routeContext->getRouteParser();
          return $response->withRedirect(
            $routeParser->urlFor(
              'catalog',
              $items[0]->url_params()
            )
          );
        }
      }

      if ($request->getAttribute('only_active') &&
          (($deptO && !$deptO->active) ||
            ($subdeptO && !$subdeptO->active) ||
            ($productO && !$productO->active) ||
            ($itemO && !$itemO->active)))
      {
        throw new \Slim\Exception\HttpNotFoundException($request);
      }

      $brands= $deptO ? null : $this->catalog->getBrands();

      return $this->view->render($response, 'catalog/page.html',
                                 [ 'brands' => $brands,
                                   'dept' => $deptO,
                                   'depts' => $depts,
                                   'subdept' => $subdeptO,
                                   'subdepts' => $subdepts,
                                   'product' => $productO,
                                   'products' => $products,
                                   'item' => $itemO,
                                   'variations' => $variations,
                                   'items' => $items ]);
    }
    catch (\Slim\Exception\HttpNotFoundException $ex) {
      /* TODO figure out a way to not have to add/remove /catalog/ */
      $base= $request->getAttribute('catalog_base') ?: 'catalog';
      $path= preg_replace("!^/$base/!", '',
                          $request->getUri()->getPath());
      $re= $this->catalog->getRedirectFrom($path);

      if ($re) {
        return $response->withRedirect('/' . $base . '/' . $re->dest, 301);
      }

      throw $ex;
    }
  }

  public function itemFeed(Request $request, Response $response,
                            \Scat\Service\Config $config,
                            \Scat\Service\Shipping $shipping)
  {
    $ordure_url= $config->get('ordure.url');
    $ordure_static= $config->get('ordure.static_url');

    /* turn off logging here, it's just too much */
    $this->data->configure('logging', false);

    $items= $this->catalog->getItems()
      ->select('*')
      ->select_expr('COUNT(*) OVER (PARTITION BY product_id)', 'siblings')
      ->where_gt('product_id', 0)
      ->where_gt('minimum_quantity', 0);

    if (($code= $request->getParam('code'))) {
      $items= $items->where_like('code', "{$code}%");
    }

    $items= $items->find_many();

    $fields= [
      'id', 'title', 'description', 'rich_text_description',
      'availability', 'condition', 'price', 'sale_price',
      'link', 'link_template', 'image_link', 'additional_image_link',
      'brand', 'gtin', 'mpn',
      'color', 'size',
      'item_group_id',
      'google_product_category',
      'gender',
      'product_type',
      'inventory',
      'shipping_label',
      'shipping_length',
      'shipping_width',
      'shipping_height',
      'shipping_weight',
      'custom_label_0'
    ];

    //$output= fopen("php://temp/maxmemory:" . (5*1024*1024), 'r+');
    $output= fopen("php://memory", 'r+');
    fputcsv($output, $fields);

    foreach ($items as $item) {
      $html= $item->description ?: $item->product()->description;
      $html= preg_replace('/\s+/', ' ', $html); // replace all whitespace
      $html= preg_replace('/{{ *@STATIC *}}/', $ordure_static, $html);
      $text= strip_tags($html);

      $product= $item->product();

      // only include items for which we have an image
      $image= $item->default_image();
      if (!$image) continue;

      $barcodes= $item->barcodes()->find_many();
      $barcode= $barcodes ? $barcodes[0]->code : null;

      if ($item->length && $item->width && $item->height) {
        if ($item->packaged_for_shipping) {
          $box= [ $item->length, $item->width, $item->height ];
        } else {
          $box= $shipping->get_shipping_box([ [
            'length' => $item->length,
            'width' => $item->width,
            'height' => $item->height,
          ]]);
        }
      } else {
        $box= null;
      }

      $link=
        ($item->siblings > 1 ?
         $ordure_url . '/art-supplies/' . $product->full_slug() . '/' . $item->code :
         $ordure_url . '/art-supplies/' . $product->full_slug());

      $record= [
        $item->code,
        $item->title(),
        $text,
        $html,
        ($item->stock() > 0 ? 'in stock' : 'out of stock'),
        'new',
        $item->retail_price . ' USD',
        $item->sale_price() . ' USD',
        $link,
        $link . '?store={store_code}',
        ($item->siblings > 1 ?
         $ordure_url . '/art-supplies/' . $product->full_slug() . '/' . $item->code :
         $ordure_url . '/art-supplies/' . $product->full_slug()),
        $image,
        '',#'additional_image_link',
        $product->brand()->name,
        $barcode,
        $item->code,
        '', # should be color, sometimes $item->short_name,
        '', # should be size, sometimes $item->variation,
        'P' . $item->product_id,
        $item->google_product_category_id ?: '',
        '', # gender
        $product->dept()->parent()->name . ' > ' .  $product->dept()->name,
        max($item->stock(), 0),
        $item->shipping_rate(),
        $box ? "{$box[0]} in" : '',
        $box ? "{$box[1]} in" : '',
        $box ? "{$box[2]} in" : '',
        ($box && $item->weight) ? ($box[3] + $item->weight) . " lb" : '',
        $item->shipping_rate(), // repeated as custom_label_0 so Shopping ads can use it
      ];

      fputcsv($output, $record);
    }

    $this->data->configure('logging', true);

    $response= $response->withBody(\GuzzleHttp\Psr7\stream_for($output));

    return $response->withHeader("Content-type", "text/csv");
  }

  public function itemLocalFeed(Request $request, Response $response,
                                \Scat\Service\Config $config)
  {
    $store_code= $config->get('google.store_code') ?: 'dummy';

    $items= $this->catalog->getItems()
      ->select('*')
      ->select_expr('COUNT(*) OVER (PARTITION BY product_id)', 'siblings')
      ->where_gt('product_id', 0)
      ->find_many();

    $fields= [
      'store code', 'id', 'quantity', 'price', 'sale price', 'availability',
      'pickup method', 'pickup sla'
    ];

    //$output= fopen("php://temp/maxmemory:" . (5*1024*1024), 'r+');
    $output= fopen("php://memory", 'r+');
    fputcsv($output, $fields);

    foreach ($items as $item) {
      // only include items for which we have an image
      if ($item->siblings > 1) {
        $media= $item->media();
        if (!$media) {
          continue;
        }
      } else {
        $media= $item->product()->media();
      }

      $record= [
        $store_code,
        $item->code,
        $item->stock() > 0 ? $item->stock() : 0,
        $item->retail_price . ' USD',
        $item->sale_price() . ' USD',
        ($item->stock() > 2 ? 'in stock' :
         ($item->stock() > 0 ? 'limited availability' : 'out of stock')),
        'buy',
        'same day'
      ];

      fputcsv($output, $record);
    }

    $response= $response->withBody(\GuzzleHttp\Psr7\stream_for($output));

    return $response->withHeader("Content-type", "text/csv");
  }

  public function itemCostFeed(Request $request, Response $response,
                                \Scat\Service\Config $config)
  {
    $store_code= $config->get('google.store_code') ?: 'dummy';

    $items= $this->catalog->getItems()
      ->select('*')
      ->select_expr('COUNT(*) OVER (PARTITION BY product_id)', 'siblings')
      ->where_gt('product_id', 0)
      ->find_many();

    $fields= [
      'id', 'cost',
    ];

    //$output= fopen("php://temp/maxmemory:" . (5*1024*1024), 'r+');
    $output= fopen("php://memory", 'r+');
    fputcsv($output, $fields);

    foreach ($items as $item) {
      // only include items for which we have an image
      if ($item->siblings > 1) {
        $media= $item->media();
        if (!$media) {
          continue;
        }
      } else {
        $media= $item->product()->media();
      }

      $cost= $item->best_cost() ?: $item->most_recent_cost();

      if (!$cost) continue;

      $record= [
        $item->code,
        $cost
      ];

      fputcsv($output, $record);
    }

    $response= $response->withBody(\GuzzleHttp\Psr7\stream_for($output));

    return $response->withHeader("Content-type", "text/csv");
  }
}
