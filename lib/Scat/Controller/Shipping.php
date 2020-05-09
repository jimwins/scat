<?php
namespace Scat\Controller;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Shipping {
  private $shipping, $txn;

  public function __construct(\Scat\Service\Shipping $shipping,
                              \Scat\Service\Txn $txn)
  {
    $this->txn= $txn;
    $this->shipping= $shipping;
  }

  function register(Request $request, Response $response) {
    return $response->withJson($this->shipping->registerWebhook());
  }

  function handleUpdate(Request $request, Response $response, View $view,
                        \Scat\Service\Email $email) {
    $data= json_decode($request->getBody());

    if ($data->description == 'tracker.updated' ||
        $data->description == 'tracker.created')
    {
      $tracker= $data->result;

      $shipment= $this->txn->fetchShipmentByTracker($tracker->id);
      if (!$shipment)
        throw new \Slim\Exception\HttpNotFoundException($request);

      if ($shipment->status != $tracker->status) {
        switch ($tracker->status) {
        case 'in_transit':
          // send order shipped email
          $txn= $shipment->txn();

          if (!$txn->person()->email) {
            error_log("Don't know the email for txn {$txn->id}, can't update");
            break;
          }

          $subject= $view->fetchBlock('email/shipped.html', 'title', [
            'tracker' => $tracker,
            'txn' => $txn,
          ]);
          $body= $view->fetch('email/shipped.html', [
            'tracker' => $tracker,
            'txn' => $txn,
          ]);

          $res= $email->send([ $txn->person()->email => $txn->person()->name ],
                             $subject, $body);

          if (in_array($txn->status, [ 'paid', 'processing' ])) {
            $txn->status= 'shipped';
            $txn->save();
          }
          break;

        case 'delivered':
          // send order delivered email
          $txn= $shipment->txn();

          if (!$txn->person()->email) {
            error_log("Don't know the email for txn {$txn->id}, can't update");
          }

          $subject= $view->fetchBlock('email/delivered.html', 'title', [
            'tracker' => $tracker,
            'txn' => $txn,
          ]);
          $body= $view->fetch('email/delivered.html', [
            'tracker' => $tracker,
            'txn' => $txn,
          ]);

          $res= $email->send([ $txn->person()->email => $txn->person()->name ],
                             $subject, $body);

          if (in_array($txn->status, [ 'paid', 'processing', 'shipped' ])) {
            $txn->status= 'complete';
            $txn->save();
          }
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

    return $response->withJson([ 'status' => 'Processed.' ]);
  }

}
