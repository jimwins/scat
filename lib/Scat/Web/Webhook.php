<?php
namespace Scat\Web;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Webhook {
  public function __construct(
    private \Scat\Service\Auth $auth,
    private \Scat\Service\Config $config
  ) {
  }

  public function production(Request $request, Response $response, $name) {
    $key= $request->getParam('key');

    if ($key != $this->config->get('webhook.key')) {
      throw new \Exception("Wrong key.");
    }

    $client= new \GuzzleHttp\Client();
    $request_uri= $request->getServerParam('REQUEST_URI');

    $url= $this->config->get('scat.url') . $request_uri;

    /* Pass through everything but Host */
    $headers= $request->getHeaders();
    unset($headers['Host']);

    $res= $client->request($request->getMethod(), $url, [
      'headers' => $headers,
      'body' => $request->getBody(),
    ]);

    return $res;
  }

  public function testWebhook(Request $request, Response $response, $name) {
    $key= $request->getParam('key');

    if ($key != $this->config->get('webhook.key')) {
      throw new \Exception("Wrong key.");
    }

    $client= new \GuzzleHttp\Client();
    $request_uri= preg_replace(
      '!/test/!', '/',
      $request->getServerParam('REQUEST_URI')
    );

    $url= $this->config->get('scat.test_url') . $request_uri;

    /* Pass through everything but Host */
    $headers= $request->getHeaders();
    unset($headers['Host']);

    $res= $client->request($request->getMethod(), $url, [
      'headers' => $headers,
      'body' => $request->getBody(),
    ]);

    return $res;
  }
  public function testWebWebhook(Request $request, Response $response, $name) {
    $key= $request->getParam('key');

    if ($key != $this->config->get('webhook.key')) {
      throw new \Exception("Wrong key.");
    }

    $client= new \GuzzleHttp\Client();
    $request_uri= preg_replace(
      '!/test-www/!', '/',
      $request->getServerParam('REQUEST_URI')
    );

    $url= $this->config->get('scat.test_www_url') . $request_uri;

    /* Pass through everything but Host */
    $headers= $request->getHeaders();
    unset($headers['Host']);

    $res= $client->request($request->getMethod(), $url, [
      'headers' => $headers,
      'body' => $request->getBody(),
    ]);

    return $res;
  }
}
