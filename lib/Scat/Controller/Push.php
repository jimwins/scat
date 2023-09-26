<?php
namespace Scat\Controller;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Push {
  private $publicKey, $privateKey;

  public function __construct(
    private \Scat\Service\Config $config
  ) {
    $this->publicKey= $config->get('push.publicKey');
    $this->privateKey= $config->get('push.privateKey');
  }

  public function generateKeys(Request $request, Response $response) {
    if ($this->publicKey) {
      throw new \Exception("Already have keys, delete them before generating new ones.");
    }

    $keys= \Minishlink\WebPush\VAPID::createVapidKeys();

    $this->config->set('push.publicKey', $keys['publicKey']);
    $this->config->set('push.privateKey', $keys['privateKey']);

    return $response->withRedirect("/push");
  }


  function home(Request $request, Response $response,
                View $view, \Scat\Service\Config $config) {
    return $view->render($response, "push/index.html");
  }

  function addSubscription(Request $request, Response $response) {
    $subscription= $request->getParams();
    error_log("Adding subscription: " . json_encode($subscription));

    \Scat\Model\WebPushSubscription::register($subscription['endpoint'], $subscription['keys']);

    return $response->withJson([]);
  }

  function updateSubscription(Request $request, Response $response) {
    $subscription= $request->getParams();

    error_log("Updating subscription: " . json_encode($subscription));

    return $response->withJson([]);
  }

  function removeSubscription(Request $request, Response $response) {
    $subscription= $request->getParams();

    \Scat\Model\WebPushSubscription::forget($subscription['endpoint']);

    return $response->withJson([]);
  }

  function pushNotification(
    Request $request, Response $response,
    \Scat\Service\Data $data
  ) {
    $uri= $request->getUri();
    $routeContext= \Slim\Routing\RouteContext::fromRequest($request);
    $link= $routeContext->getRouteParser()->fullUrlFor($uri, 'home');

    $push= new \Minishlink\WebPush\WebPush([
      'VAPID' => [
        'subject' => $link,
        'publicKey' => $this->publicKey,
        'privateKey' => $this->privateKey
      ],
    ],
    [
      'urgency' => 'normal',
    ]);

    $subscriptions= $data->factory('WebPushSubscription')->find_many();

    /*
     * Should really build an array of notifications and send them all at
     * once, but we are just using this internally right now for few
     * subscribers.
     */
    foreach ($subscriptions as $sub) {
      $keys= json_decode($sub->data);
      $res= $push->sendOneNotification(
        new \Minishlink\WebPush\Subscription($sub->endpoint, $keys->p256dh, $keys->auth, 'aesgcm'),
        $request->getParam('body')
      );
    }

     return $response->withRedirect("/push");
  }

  function getServiceWorker(Request $request, Response $response, View $view) {
    return $view->render($response, 'push/service-worker.js')
                ->withHeader('Content-type', 'application/javascript;charset=UTF-8');
  }
}
