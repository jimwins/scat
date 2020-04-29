<?php
namespace Scat\Controller;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Push {
  function home(Request $request, Response $response,
                View $view, \Scat\Service\Config $config) {
    $uri= $request->getUri();
    // Not getting \Slim\Http\Uri for some reason, so more work
    $scheme = $uri->getScheme();
    $authority = $uri->getAuthority();
    $baseUrl= ($scheme !== '' ? $scheme . ':' : '') .
              ($authority !== '' ? '//' . $authority : '');
    $ident= $config->get('push.websitePushID');
    return $view->render($response, "push/index.html", [
                          'url' => $baseUrl,
                          'ident' => $ident,
                        ]);
  }

  function pushPackages(Request $request, Response $response, $id,
                        \Scat\Service\Push $push) {
    $uri= $request->getUri();
    // Not getting \Slim\Http\Uri for some reason, so more work
    $scheme = $uri->getScheme();
    $authority = $uri->getAuthority();
    $baseUrl= ($scheme !== '' ? $scheme . ':' : '') .
              ($authority !== '' ? '//' . $authority : '');
    $zip= $push->getPushPackage($baseUrl, $id);
    return $response->withHeader("Content-type", "application/zip")
                ->withBody($zip);
  }

  function registerDevice(Request $request, Response $response, $token, $id) {
    error_log("PUSH: Registered device: '$token'");
    $device= \Scat\Model\Device::register($token);
    return $response;
  }

  function forgetDevice(Request $request, Response $response, $token, $id) {
    error_log("PUSH: Forget device: '$token'");
    $device= \Scat\Model\Device::forget($token);
    return $response;
  }

  function log(Request $request, Response $response) {
    $data= $request->getParsedBody();
    error_log("PUSH: " . json_encode($data));
    return $response;
  }

  function pushNotification(Request $request, Response $response,
                            \Scat\Service\Push $push) {
     $devices= \Model::factory('Device')->find_many();

     foreach ($devices as $device) {
       $push->sendNotification(
         $device->token,
         $request->getParam('title'),
         $request->getParam('body'),
         $request->getParam('action'),
         'clicked' /* Not sure what to do about arguments yet. */
       );
     }

     return $response->withRedirect("/push");
  }
}
