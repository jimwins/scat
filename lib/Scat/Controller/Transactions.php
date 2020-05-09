<?php
namespace Scat\Controller;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Transactions {
  private $view, $txn;

  public function __construct(View $view, \Scat\Service\Txn $txn) {
    $this->view= $view;
    $this->txn= $txn;
  }

  public function sales(Request $request, Response $response) {
    $page= (int)$request->getParam('page');
    $limit= 25;
    $txns= $this->txn->find('customer', $page, $limit);
    return $this->view->render($response, 'txn/index.html', [
      'type' => 'customer',
      'txns' => $txns,
      'page' => $page,
      'limit' => $limit,
    ]);
  }

  public function newSale(Response $response) {
    ob_start();
    include "../old-index.php";
    $content= ob_get_clean();
    return $this->view->render($response, 'sale/old-new.html', [
      'title' => $GLOBALS['title'],
      'content' => $content,
    ]);
  }

  public function sale(Response $response, $id) {
    return $response->withRedirect("/?id=$id");
  }

  public function createSale(Request $request, Response $response) {
    \ORM::get_db()->beginTransaction();

    $copy_from_id= $request->getParam('copy_from_id');
    $copy= $copy_from_id ? $this->txn->fetchById($copy_from_id) : null;

    $sale= $this->txn->create('customer', [
      'tax_rate' => 0,
    ]);

    if ($copy) {
      // Just copy a limited number of fields
      foreach ([
        'person_id', 'shipping_address_id', 'tax_rate',
        'returned_from_id', 'no_rewards'
      ] as $field) {
        $sale->set($field, $copy->$field);
      }
    }

    $sale->save();

    if ($copy) {
      foreach ($copy->items()->find_many() as $line) {
        $new= $sale->items()->create();
        $data= $line->as_array();
        unset($data['id']); // don't copy id!
        $new->set($data);
        $new->txn_id= $sale->id;
        $new->save();
      }
    }

    /* We don't copy notes. */

    \ORM::get_db()->commit();

    $path= '/sale/' . $sale->id;

    return $response->withRedirect($path);
  }

  public function emailForm(Request $request, Response $response, $id) {
    $txn= $this->txn->fetchById($id);

    return $this->view->render($response, 'dialog/email-invoice.html', [
      'txn' => $txn
    ]);
  }

  public function email(Request $request, Response $response, $id,
                        \Scat\Service\Email $email)
  {
    $txn= $this->txn->fetchById($id);

    $to_name= $request->getParam('name');
    $to_email= $request->getParam('email');
    $subject= trim($request->getParam('subject'));

    $body= $this->view->fetch('email/invoice.html', [
      'txn' => $txn,
      'subject' => $subject,
      'content' =>
        $request->getParam('content'),
    ]);

    $attachments= [];
    if ($request->getParam('include_details')) {
      $pdf= $txn->getInvoicePDF();
      $attachments[]= [
        base64_encode($pdf->Output('', 'S')),
        'application/pdf',
        (($txn->type == 'vendor') ? 'PO' : 'I') .
          $txn->formatted_number() . '.pdf',
        'attachment'
      ];
    }

    $res= $email->send([ $to_email => $to_name ],
                       $subject, $body, $attachments);

    return $response->withJson($res->body() ?:
                                [ "message" => "Email sent." ]);
  }

  public function saleShipments(Request $request, Response $response,
                                $id, $shipment_id= null) {
    $txn= $this->txn->fetchById($id);
    if (!$txn)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $shipment= $shipment_id ? $txn->shipments()->find_one($shipment_id) : null;
    if ($shipment_id && !$shipment)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/vnd.scat.dialog+html') !== false) {
      return $this->view->render($response, 'dialog/shipment.html', [
        'txn' => $txn,
        'shipment' => $shipment
      ]);
    }

    return $response->withJson($shipment);
  }

  public function trackShipment(Request $request, Response $response,
                                \Scat\Service\Shipping $shipping,
                                $id, $shipment_id)
  {
    $txn= $this->txn->fetchById($id);
    if (!$txn)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $shipment= $shipment_id ? $txn->shipments()->find_one($shipment_id) : null;
    if ($shipment_id && !$shipment)
      throw new \Slim\Exception\HttpNotFoundException($request);

    if (!$shipment->tracker_id)
      throw new \Slim\Exception\HttpNotFoundException($request,
        "No tracker_id found for that shipment.");

    $tracker= $shipping->getTracker($shipment->tracker_id);

    return $response->withRedirect($tracker->public_url);
  }

  public function updateShipment(Request $request, Response $response,
                                  \Scat\Service\Shipping $shipping,
                                  $id, $shipment_id= null) {
    $txn= $this->txn->fetchById($id);
    if (!$txn)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $shipment= $shipment_id ? $txn->shipments()->find_one($shipment_id) : null;
    if ($shipment_id && !$shipment)
      throw new \Slim\Exception\HttpNotFoundException($request);

    if (!$shipment) {
      $shipment= $txn->shipments()->create();
      $shipment->txn_id= $txn->id;
    }

    foreach ($shipment->getFields() as $field) {
      if ($field == 'id') continue;
      $value= $request->getParam($field);
      if (strlen($value)) {
        $shipment->setProperty($field, $value);
      }
    }

    /* New tracking code? */
    if (($tracking_code= $request->getParam('tracking_code'))) {
      $tracker= $shipping->createTracker([
        'tracking_code' => $tracking_code,
        'carrier' => $request->getParam('carrier'),
      ]);
      $shipment->tracker_id= $tracker->id;
      /* Wait for webhook to update status. */
      $shipment->status= 'unknown';

      if (in_array($txn->status, [ 'paid', 'processing' ])) {
        $txn->status= 'shipped';
        $txn->save();
      }
    }

    $shipment->save();

    return $response->withJson($shipment);
  }

  public function purchases(Request $request, Response $response) {
    $page= (int)$request->getParam('page');
    $limit= 25;
    $txns= $this->txn->find('vendor', $page, $limit);
    return $this->view->render($response, 'txn/index.html', [
      'type' => 'vendor',
      'txns' => $txns,
      'page' => $page,
      'limit' => $limit,
    ]);
  }

  public function reorderForm(Request $request, Response $response) {
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
      $extra.= " AND code LIKE " . \ORM::get_db()->quote($code.'%');
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

    \ORM::configure('logging', false);
    $items= \ORM::for_table('item')->raw_query($q)->find_many();

    return $this->view->render($response, 'purchase/reorder.html', [
      'items' => $items,
      'all' => $all,
      'code' => $code,
      'vendor' => $vendor,
      'person' => \Model::factory('Person')->find_one($vendor)
    ]);
  }

  public function createPurchase(Request $request, Response $response) {
    $vendor_id= $request->getParam('vendor');

    error_log("Creating purchase for $vendor_id");

    if (!$vendor_id) {
      throw new \Exception("No vendor specified.");
    }

    $purchase= $this->txn->create('vendor', [
      'person_id' => $vendor_id,
      'tax_rate' => 0,
    ]);

    $purchase->save();

    /* Pass through to addToPurchase() to handle adding items */
    return $this->addToPurchase($request, $response, $purchase->id);
  }

  public function addToPurchase(Request $request, Response $response, $id) {
    $purchase= $this->txn->fetchById($id);
    if (!$purchase) {
      throw new \Exception("Unable to find transaction.");
    }

    \ORM::get_db()->beginTransaction();

    $vendor_id= $purchase->person_id;

    if (!$vendor_id) {
      throw new \Exception("No vendor specified.");
    }

    $items= $request->getParam('item');
    if ($items) {
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
    }

    \ORM::get_db()->commit();

    $path= '/purchase/' . $purchase->id;

    return $response->withRedirect($path);
  }

  public function purchase(Response $response, $id) {
    return $response->withRedirect("/?id=$id");
  }

}
