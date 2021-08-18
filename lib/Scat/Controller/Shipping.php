<?php
namespace Scat\Controller;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Shipping {
  private $shipping, $txn, $email, $view, $ordure, $paypal;

  public function __construct(\Scat\Service\Shipping $shipping,
                              \Scat\Service\Txn $txn,
                              \Scat\Service\Email $email,
                              \Scat\Service\Ordure $ordure,
                              \Scat\Service\PayPal $paypal,
                              \Scat\Service\Data $data,
                              View $view)
  {
    $this->txn= $txn;
    $this->shipping= $shipping;
    $this->email= $email;
    $this->ordure= $ordure;
    $this->paypal= $paypal;
    $this->data= $data;
    $this->view= $view;
  }

  function index(Request $request, Response $response) {
    $page= (int)$request->getParam('page');
    $page_size= 50;

    $shipments= $this->data->factory('Shipment')
      ->select('*')
      ->select_expr('COUNT(*) OVER()', 'records')
      ->order_by_desc('created')
      ->limit($page_size)->offset($page * $page_size);

    $q= $request->getParam('q');
    if ($q) {
      // todo
    }

    $shipments= $shipments->find_many();

    return $this->view->render($response, 'shipping/index.html', [
      'shipments' => $shipments,
      'q' => $q,
      'page' => $page,
      'page_size' => $page_size,
    ]);
  }

  function shipment(Request $request, Response $response, $id) {
    $shipment= $this->data->factory('Shipment')->find_one($id);
    if (!$shipment) {
      throw new \Slim\Exception\HttpNotFoundException($request);
    }

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/vnd.scat.dialog+html') !== false) {
      return $this->view->render($response, 'dialog/shipment.html', [
        'shipment' => $shipment,
        'easypost' => $shipment->method_id ? $this->shipping->getShipment($shipment) : null,
        'tracker' => $shipment->tracker_id ? $this->shipping->getTracker($shipment) : null,
      ]);
    }

    return $this->view->render($response, 'shipping/shipment.html', [
      'shipment' => $shipment
    ]);
  }

  /* Webhooks */
  function register(Request $request, Response $response) {
    return $response->withJson($this->shipping->registerWebhook());
  }

  function checkStalledTrackers(Request $request, Response $response) {
    $shipments= $this->txn->getShipments()
      ->where_in('status', [ 'unknown', 'pre_transit', 'in_transit' ])
      ->find_many();

    $updating= [];
    foreach ($shipments as $shipment) {
      $tracker= $this->shipping->getTracker($shipment->tracker_id);
      if ($tracker->status != 'unknown') {
        $this->handleUpdate($shipment, $tracker);
        $updating[]= $tracker->id;
      }
    }

    return $response->withJson([ 'updating' => $updating ]);
  }

  function handleUpdate($shipment, $tracker) {
    if ($shipment->status == 'pre_transit' ||
        $shipment->status != $tracker->status)
    {
      $status= $tracker->status;
      $shipped= null;

      switch ($tracker->status) {
      case 'pre_transit':
        /* We treat pre_transit/arrived_at_facility as in_transit */
        foreach ($tracker->tracking_details as $details) {
          if ($details->status_detail == 'arrived_at_facility') {
            $shipped= $details->datetime;
            $status= 'in_transit';
            break;
          }
        }
        if (!$shipped) {
          error_log("still pre_transit, skipping.\n");
          break;
        }
        /* FALLTHROUGH */
      case 'in_transit':
        // send order shipped email
        $txn= $shipment->txn();

        if ($txn->status == 'shipped') {
          error_log("Not sending notification for {$txn->id}, already sent!\n");
          break;
        }

        if (in_array($txn->status, [ 'paid', 'processing', 'shipping' ])) {
          $txn->status= 'shipped';
          $txn->save();
        }

        if ($txn->type != 'customer') {
          break;
        }

        if ($txn->online_sale_id) {
          try {
            $this->ordure->markOrderShipped($txn->uuid);
          } catch (\Exception $e) {
            error_log("failed to mark txn {$txn->id} shipped: " . $e->getMessage());
          }
        }

        if (!$txn->person()->email) {
          error_log("Don't know the email for txn {$txn->id}, can't update\n");
          break;
        }

        if (!$shipped) {
          foreach ($tracker->tracking_details as $details) {
            if ($details->status == 'in_transit')
            {
              $shipped= $details->datetime;
              break;
            }
          }
        }

        $subject= $this->view->fetchBlock('email/shipped.html', 'title', [
          'tracker' => $tracker,
          'shipped' => $shipped,
          'txn' => $txn,
        ]);
        $body= $this->view->fetch('email/shipped.html', [
          'tracker' => $tracker,
          'shipped' => $shipped,
          'txn' => $txn,
        ]);

        $res= $this->email->send(
          [ $txn->person()->email => $txn->person()->name ],
          $subject, $body
        );

        foreach ($txn->payments()->find_many() as $payment) {
          if ($payment->method == 'paypal') {
            $this->paypal->addTracker($payment, $tracker);
          }
        }

        break;

      case 'delivered':
        // send order delivered email
        $txn= $shipment->txn();

        if (in_array($txn->status, [ 'paid', 'processing', 'shipping', 'shipped' ])) {
          $txn->status= 'complete';
          $txn->save();
        }

        if (!$txn->person()->email) {
          error_log("Don't know the email for txn {$txn->id}, can't update");
          break;
        }

        foreach ($tracker->tracking_details as $details) {
          if ($details->status == 'delivered') {
            $delivered= $details->datetime;
          }
        }

        if ($txn->type != 'customer') {
          break;
        }

        $subject= $this->view->fetchBlock('email/delivered.html', 'title', [
          'tracker' => $tracker,
          'delivered' => $delivered,
          'txn' => $txn,
        ]);
        $body= $this->view->fetch('email/delivered.html', [
          'tracker' => $tracker,
          'delivered' => $delivered,
          'txn' => $txn,
        ]);

        $res= $this->email->send(
          [ $txn->person()->email => $txn->person()->name ],
          $subject, $body
        );

        break;

      case 'available_for_pickup':
        // send order available for pickup
        $txn= $shipment->txn();

        if ($txn->type != 'customer') {
          break;
        }

        if (!$txn->person()->email) {
          error_log("Don't know the email for txn {$txn->id}, can't update");
          break;
        }

        foreach ($tracker->tracking_details as $details) {
          if ($details->status == 'available_for_pickup') {
            $available_for_pickup= $details->datetime;
          }
        }

        $subject= $this->view->fetchBlock('email/available_for_pickup.html', 'title', [
          'tracker' => $tracker,
          'available_for_pickup' => $available_for_pickup,
          'txn' => $txn,
        ]);
        $body= $this->view->fetch('email/available_for_pickup.html', [
          'tracker' => $tracker,
          'available_for_pickup' => $available_for_pickup,
          'txn' => $txn,
        ]);

        $res= $this->email->send(
          [ $txn->person()->email => $txn->person()->name ],
          $subject, $body
        );

        break;

      case 'out_for_delivery':
      case 'return_to_sender':
      case 'failure':
      case 'cancelled':
      case 'error':
        break;

      default:
        throw new \Exception("Did not understand new shipping status {$tracker->status}");
      }

      $shipment->status= $status;
      $shipment->save();
    }
  }

  function handleWebhook(Request $request, Response $response,
                          \Scat\Service\Printer $printer)
  {
    $data= json_decode($request->getBody());

    if ($data->description == 'tracker.updated' ||
        $data->description == 'tracker.created')
    {
      $tracker= $data->result;

      $shipment= $this->txn->fetchShipmentByTracker($tracker->id);
      if (!$shipment)
        throw new \Slim\Exception\HttpNotFoundException($request);

      $this->handleUpdate($shipment, $tracker);
    }

    if ($data->description == 'scan_form.updated' &&
        $data->result->status == 'created')
    {
      $form= $data->result->form_url;
      $pdf= file_get_contents($form);
      return $printer->printPDF($response, 'letter', $pdf);
    }

    return $response->withJson([ 'status' => 'Processed.' ]);
  }

  public function analyze(Request $request, Response $response) {

    if ($request->getParam('rates')) {
      $services= [
        'Priority',
        'First',
        'Ground',
        'CaliforniaParcelService',
        'FEDEX_GROUND',
        'GROUND_HOME_DELIVERY',
      ];

      $addresses= [
        [
          'name' => 'Richard Q. Jonnes',
          'company' => 'WIBSTR',
          'street1' => '301 Platt Blvd',
          'city' => 'Claremont',
          'state' => 'CA',
          'zip' => '91711',
          'residential' => true,
        ],
        [
          'name' => 'Richard Q. Jonnes',
          'company' => 'WIBSTR',
          'street1' => '226 West 46th St',
          'city' => 'New York',
          'state' => 'NY',
          'zip' => '10036',
        ],
        [
          'name' => 'Richard Q. Jonnes',
          'company' => 'WIBSTR',
          'street1' => '411 Elm St',
          'city' => 'Dallas',
          'state' => 'TX',
          'zip' => '75202',
        ],
        [
          'name' => 'Richard Q. Jonnes',
          'company' => 'WIBSTR',
          'street1' => '605 S Main St',
          'city' => 'Seattle',
          'state' => 'WA',
          'zip' => '98104',
        ],
        [
          'name' => 'Richard Q. Jonnes',
          'company' => 'WIBSTR',
          'street1' => '302 S Greene St',
          'city' => 'Greenville',
          'state' => 'NC',
          'zip' => '27834',
          'residential' => true,
        ],
      ];

      $addresses= array_map(function($address) {
        return $this->shipping->createAddress($address);
      }, $addresses);

      $parcels= [
        [
          'name' => '5x5x3.5 12oz',
          'length' => 5,
          'width' => 5.25,
          'height' => 4,
          'weight' => 12,
        ],
        [
          'name' => '5x5x3.5 2lb',
          'length' => 5,
          'width' => 5.25,
          'height' => 4,
          'weight' => 2 * 16,
        ],
        [
          'name' => '9x5x3 12oz',
          'length' => 9,
          'width' => 5.25,
          'height' => 3.5,
          'weight' => 12,
        ],
        [
          'name' => '9x5x3 2lb',
          'length' => 9,
          'width' => 5.25,
          'height' => 3.5,
          'weight' => 2 * 16,
        ],
        [
          'name' => '10x7x5 3lb',
          'length' => 10,
          'width' => 7.25,
          'height' => 5,
          'weight' => 3 * 16,
        ],
        [
          'name' => '12x9.5x4 3lb',
          'length' => 12,
          'width' => 10,
          'height' => 4.25,
          'weight' => 3 * 16,
        ],
        [
          'name' => '12x9.5x4 3lb',
          'length' => 12,
          'width' => 10,
          'height' => 4.25,
          'weight' => 3 * 16,
        ],
        [
          'name' => '18x16x4 5lb',
          'length' => 18.25,
          'width' => 16.25,
          'height' => 4.5,
          'weight' => 5 * 16,
        ],
        [
          'name' => '3x3x12.25 12oz',
          'length' => 3.25,
          'width' => 3.25,
          'height' => 12.5,
          'weight' => 12,
        ],
        [
          'name' => '3x3x12.25 3lb',
          'length' => 3.25,
          'width' => 3.25,
          'height' => 12.5,
          'weight' => 3*16,
        ],
        [
          'name' => '3x3x18.25 12oz',
          'length' => 3.25,
          'width' => 3.25,
          'height' => 19.5,
          'weight' => 12,
        ],
        [
          'name' => '3x3x18.25 3lb',
          'length' => 3.25,
          'width' => 3.25,
          'height' => 19.5,
          'weight' => 3 * 16,
        ],
/*
        [
          'name' => '15x18x8 5lb',
          'length' => 15,
          'width' => 18,
          'height' => 8,
          'weight' => 10 * 16,
        ],
        [
          'name' => '15x18x8 10lb',
          'length' => 15,
          'width' => 18,
          'height' => 8,
          'weight' => 10 * 16,
        ],
        [
          'name' => '19x25x8 5lb',
          'length' => 19,
          'width' => 25,
          'height' => 8,
          'weight' => 10 * 16,
        ],
        [
          'name' => '19x25x8 20lb',
          'length' => 19,
          'width' => 25,
          'height' => 8,
          'weight' => 10 * 16,
        ],
        [
          'name' => '33x42x5 5lb',
          'length' => 32,
          'width' => 42,
          'height' => 5,
          'weight' => 5 * 16,
        ],
        [
          'name' => '33x42x5 30lb',
          'length' => 32,
          'width' => 42,
          'height' => 5,
          'weight' => 30 * 16,
        ],
*/
      ];

      $parcels= array_map(function($parcel) {
        $res= $this->shipping->createParcel($parcel);
        $res->name= $parcel['name'];
        return $res;
      }, $parcels);

      $from= $this->shipping->getDefaultFromAddress();

      $results= [];

      foreach ($parcels as $parcel) {
        foreach ($addresses as $address) {
          $shipment= $this->shipping->createShipment([
            'to_address' => $address,
            'from_address' => $from,
            'parcel' => $parcel,
          ]);

          foreach ($shipment->rates as $rate) {
            if (in_array($rate->service, $services)) {
              $results[]= [
                'parcel' => $parcel->name,
                'address' => "{$address->city}, {$address->state}",
                'carrier' => $rate->carrier,
                'service' => $rate->service,
                'rate' => $rate->rate,
              ];
            }
          }
        }
      }

      return $this->view->render($response, 'shipping/analyze.html', [
        'rates' => $results,
      ]);
    }

    if ($request->getParam('sizes')) {
      $sizes= [
        [ 5, 5, 3.5 ],
        [ 9, 5, 3 ],
        [ 10, 7, 5 ],
        [ 9, 8, 8 ],
        [ 3, 3, 12.25 ],
        [ 3, 3, 18.25 ],
        [ 12, 9.5, 4 ],
        [ 12, 9, 9 ],
        [ 15, 12, 4 ],
        [ 18, 16, 4 ],
        [ 22, 18, 6 ],
        [ 33, 19, 4.25 ],
      ];

      $laff= new \Cloudstek\PhpLaff\Packer();

      $results= [];

      $limit= $request->getParam('limit') ?: 1000;
      $txns= $this->txn->find('customer', 0, $limit)
        ->where_null('returned_from_id')
        ->where_gt('shipping_address_id', 1)
        ->find_many();

      foreach ($txns as $txn) {
        $items= []; $weight= 0;
        foreach ($txn->items()->find_many() as $line) {
          $item= $line->item();
          if ($item->tic != 0) continue;
          $items= array_merge($items, array_fill(0, $line->ordered * -1,
            [
              'length' => $item->length,
              'width' => $item->width,
              'height' => $item->height,
            ]));
          $weight+= -1 * $line->ordered * $item->weight;
        }

        if (!count($items)) continue;

        foreach ($sizes as $size) {
          $laff->pack($items, [
            'length' => $size[0],
            'width' => $size[1],
            'height' => $size[2],
          ]);

          $container= $laff->get_container_dimensions();

          if ($container['height'] <= $size[2] &&
              !count($laff->get_remaining_boxes()))
          {
            $results[join('x',$size)][]= $weight;
            continue 2; // done with this $txn
          }
        }

        $results["oversized"][]= $weight;
      }

      $processed= [];

      foreach ($results as $name => $values) {
        sort($values);
        $processed[]= [
          'name' => $name,
          'count' => count($values),
          'median' => self::median($values),
          'maximum' => array_pop($values),
        ];
      }

      return $this->view->render($response, 'shipping/analyze.html', [
        'sizes' => $processed,
        'box_sizes' => $sizes
      ]);
    }

    return $this->view->render($response, 'shipping/analyze.html', []);
  }

  static function median($array) {
    if (!is_array($array)) {
      throw new \Exception('$array must be an array.');
    }

    $n= count($array);
    if (!$n) return false; // empty array has no median value

    $middle= floor(($n - 1) / 2);

    if ($n % 2) {
      return $array[$middle];
    } else {
      return ($array[$middle] + $array[$middle + 1]) / 2;
    }
  }

  function populateShipmentData(Request $request, Response $response) {
    $shipments= $this->data->factory('Shipment')
      ->select('*')
      ->select_expr('COUNT(*) OVER()', 'records')
      ->where_null('rate')
      ->where_not_equal('status', 'pending')
      ->where_equal('method', 'easypost')
      ->where_not_null('method_id')
      ->order_by_desc('created')
      ->find_many();

    foreach ($shipments as $shipment) {
      $ep= $this->shipping->getShipment($shipment);
      $shipment->carrier= $ep->selected_rate->carrier;
      $shipment->service= $ep->selected_rate->service;
      $shipment->rate= $ep->selected_rate->rate;
      $shipment->insurance= $ep->insurance;
      $shipment->save();
    }

    return $response->withJson([]);
  }

  public function createBatch(Request $request, Response $response) {
    $ids= $request->getParam('shipments');

    $shipments= $this->data->factory('Shipment')
      ->where_in('id', $ids)
      ->find_many();

    $ep_ids= [];
    foreach ($shipments as $shipment) {
      $ep_ids[]= $shipment->method_id;
    }

    $batch= $this->shipping->createBatch($ep_ids);

    $scan_form= $batch->create_scan_form();

    return $response->withJson([ 'form' => $scan_form->from_url, 'id' => $scan_form->id ]);
  }

  public function createShipmentReturn(Request $request, Response $response,
                                        $id)
  {
    $shipment= $this->data->factory('Shipment')->find_one($id);
    if (!$shipment) {
      throw new \Slim\Exception\HttpNotFoundException($request);
    }

    $txn= $shipment->txn();

    $new_shipment= $txn->shipments()->create();
    $new_shipment->txn_id= $txn->id;

    $return_shipment= $this->shipping->createReturn($shipment);

    $new_shipment->method_id= $return_shipment->id;
    $new_shipment->status= 'pending';

    $new_shipment->save();

    return $response->withJson($new_shipment);
  }

  public function trackShipment(Request $request, Response $response,
                                $shipment_id)
  {
    $shipment= $this->data->factory('Shipment')->find_one($shipment_id);
    if (!$shipment) {
      throw new \Slim\Exception\HttpNotFoundException($request);
    }

    if (!$shipment->tracker_id)
      throw new \Slim\Exception\HttpNotFoundException($request,
        "No tracker_id found for that shipment.");

    $tracker_url= $this->shipping->getTrackerUrl($shipment);

    return $response->withRedirect($tracker_url);
  }

  public function deleteShipment(Request $request, Response $response, $id)
  {
    $shipment= $this->data->factory('Shipment')->find_one($id);
    if (!$shipment) {
      throw new \Slim\Exception\HttpNotFoundException($request);
    }

    if ($shipment->status != 'pending') {
      throw new \Exception("Unable to delete {$shipment->status} shipment");
    }

    $shipment->delete();

    return $response->withJson([]);
  }

  public function printShipmentLabel(Request $request, Response $response,
                                      \Scat\Service\Printer $print, $id)
  {
    $shipment= $this->data->factory('Shipment')->find_one($id);
    if (!$shipment) {
      throw new \Slim\Exception\HttpNotFoundException($request);
    }

    if (!$shipment->method_id)
      throw new \Slim\Exception\HttpNotFoundException($request,
        "No details found for that shipment.");

    $details= $this->shipping->getShipment($shipment);

    $PNG= 1;

    if ($PNG) {
      $png= file_get_contents($details->postage_label->label_url);

      return $print->printPNG($response, 'shipping-label', $png);
    }

    if ($ZPL) {
      if (!$details->postage_label->label_zpl_url) {
        $details->label([ 'file_format' => 'zpl' ]);
      }

      $zpl= file_get_contents($details->postage_label->label_zpl_url);

      return $print->printZPL($response, 'shipping-label', $zpl);
    }

    if (!$details->postage_label->label_pdf_url) {
      $details->label([ 'file_format' => 'pdf' ]);
    }

    $pdf= file_get_contents($details->postage_label->label_pdf_url);

    return $print->printPDF($response, 'shipping-label', $pdf);
  }

  public function refundShipment(Request $request, Response $response, $id)
  {
    $shipment= $this->data->factory('Shipment')->find_one($id);
    if (!$shipment) {
      throw new \Slim\Exception\HttpNotFoundException($request);
    }

    $res= $this->shipping->refundShipment($shipment);

    $shipment->status= 'cancelling';
    $shipment->save();

    return $response->withJson($res);
  }

}
