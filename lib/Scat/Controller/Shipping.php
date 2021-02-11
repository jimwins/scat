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
    $page_size= 20;

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
    if ($shipment->status != $tracker->status) {
      switch ($tracker->status) {
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

        foreach ($tracker->tracking_details as $details) {
          if ($details->status == 'in_transit') {
            $shipped= $details->datetime;
            break;
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

      case 'pre_transit':
      case 'out_for_delivery':
      case 'return_to_sender':
      case 'failure':
      case 'cancelled':
      case 'error':
        break;

      default:
        throw new \Exception("Did not understand new shipping status {$tracker->status}");
      }

      $shipment->status= $tracker->status;
      $shipment->save();
    }
  }

  function handleWebhook(Request $request, Response $response)
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

    return $response->withJson([ 'status' => 'Processed.' ]);
  }

  function handleShippoWebhook(Request $request, Response $response)
  {
    $data= json_decode($request->getBody());

    if ($data->event != 'track_updated') {
      throw new \Exception("Don't know how to handle this update.");
    }

    $tracker= $data->data;
    $id= ($tracker->carrier ?: 'shippo') . '/' . $tracker->tracking_number;

    if (!$id) {
      throw new \Exception("No tracking_status id available.");
    }

    $shipment= $this->txn->fetchShipmentByTracker($id);
    if (!$shipment)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $status= strtolower($tracker->tracking_status->status);

    if ($shipment->status != $status) {
      switch ($status) {
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

        if ($txn->online_sale_id) {
          $this->ordure->markOrderShipped($txn->uuid);
        }

        if (!$txn->person()->email) {
          error_log("Don't know the email for txn {$txn->id}, can't update\n");
          break;
        }

        foreach ($tracker->tracking_history as $details) {
          if ($details->status == 'TRANSIT') {
            $shipped= $details->status_date;
            break;
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

        foreach ($tracker->tracking_history as $details) {
          if ($details->status == 'DELIVERED') {
            $delivered= $details->status_date;
          }
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

      case 'unknown':
      case 'pre_transit':
      case 'returned':
      case 'failure':
        // TODO should really handle at least those last two
        break;

      default:
        throw new \Exception("Did not understand new shipping status '$status'");
      }

      $shipment->status= $status;
      $shipment->save();
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
        'NoonPriorityService',
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
          'name' => '6x6x4 12oz',
          'length' => 6.25,
          'width' => 6.25,
          'height' => 4.5,
          'weight' => 12,
        ],
        [
          'name' => '6x6x4 2lb',
          'length' => 6.25,
          'width' => 6.25,
          'height' => 4.5,
          'weight' => 2 * 16,
        ],
        [
          'name' => '8x6x3 12oz',
          'length' => 8.25,
          'width' => 6.25,
          'height' => 3.5,
          'weight' => 12,
        ],
        [
          'name' => '8x6x3 2lb',
          'length' => 8.25,
          'width' => 6.25,
          'height' => 3.5,
          'weight' => 2 * 16,
        ],
        [
          'name' => '12x9.5x4 3lb',
          'length' => 12.25,
          'width' => 9.75,
          'height' => 4.5,
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
        [ 6, 6, 4 ],
        [ 8, 6, 3 ],
        [ 3, 3, 12.25 ],
        [ 3, 3, 18.25 ],
        [ 12, 9.5, 4 ],
        [ 18, 16, 4 ],
      ];

      $laff= new \Cloudstek\PhpLaff\Packer();

      $results= [];

      $txns= $this->txn->find('customer', 0, 1000)
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
          error_log("packing " . json_encode($items) . " into " . json_encode($size));
          $laff->pack($items, [
            'length' => $size[0],
            'width' => $size[1],
            'height' => $size[2],
          ]);

          $container= $laff->get_container_dimensions();

          error_log("dimensions: " . json_encode($container));
          if (!count($laff->get_remaining_boxes())) {
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
}
