<?php
namespace Scat\Controller;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Shipping {
  private $shipping, $txn, $email, $view, $ordure;

  public function __construct(\Scat\Service\Shipping $shipping,
                              \Scat\Service\Txn $txn,
                              \Scat\Service\Email $email,
                              \Scat\Service\Ordure $ordure,
                              View $view)
  {
    $this->txn= $txn;
    $this->shipping= $shipping;
    $this->email= $email;
    $this->ordure= $ordure;
    $this->view= $view;
  }

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
          $this->ordure->markOrderShipped($txn->uuid);
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
}
