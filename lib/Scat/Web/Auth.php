<?php
namespace Scat\Web;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Auth {
  private $view, $data, $auth, $scat;

  public function __construct(
    \Scat\Service\Data $data,
    \Scat\Service\Auth $auth,
    \Scat\Service\Scat $scat,
    View $view
  ) {
    $this->data= $data;
    $this->auth= $auth;
    $this->scat= $scat;
    $this->view= $view;
  }

  public function account(Request $request, Response $response)
  {
    $person= $this->auth->get_person_details($request);

    if (!$person) {
      return $response->withRedirect('/login');
    }

    return $this->view->render($response, "account/index.html", [
      'success' => $request->getParam('success'),
      'person' => $person,
    ]);
  }

  public function loginForm(Request $request, Response $response)
  {
    return $this->view->render($response, "login.html", [
      'success' => $request->getParam('success'),
      'error' => $request->getParam('error'),
      'loyalty' => $request->getParam('loyalty'),
    ]);
  }

  public function handleLogin(Request $request, Response $response,
                              \Scat\Service\Email $email)
  {
    $loyalty= trim($request->getParam('loyalty'));

    $people= $this->scat->find_person($loyalty);

    if (!$people) {
      return $response->withRedirect('/login?error=invalid_loyalty&loyalty=' . rawurlencode($loyalty));
    }

    $person= $people[0];

    $cart_uuid= $request->getAttribute('cart_uuid');

    $key= $this->auth->generate_login_key($person->id, $cart_uuid);

    $uri= $request->getUri();
    $routeContext= \Slim\Routing\RouteContext::fromRequest($request);
    $link= $routeContext->getRouteParser()->fullUrlFor($uri, 'handleLoginKey', [
      'key' => $key
    ]);

    if (strcasecmp($loyalty, $person->email) == 0) {
      $data= [ 'person' => $person, 'link' => $link ];

      $template= $this->view->getEnvironment()->load('email/login-link.html');

      $subject= $template->renderBlock('title', $data);
      $body= $template->render($data);

      $email->send([ $person->email => $person->name], $subject, $body);

      return $response->withRedirect('/login?success=email_sent');
    }

    /* send sms */
    $message= "You can use this link to log in within the next 24 hours: " .
              $link;
    $this->scat->sendSMS($person->loyalty_number, $message);
    return $response->withRedirect('/login?success=sms_sent');
  }

  public function handleLoginKey(Request $request, Response $response, $key)
  {
    $valid= $this->auth->validate_auth_token($key);

    if (!$valid) {
      return $response->withRedirect('/login?error=invalid_key');
    }

    $expires= new \Datetime('+14 days');
    $token= $this->auth->generate_login_key($valid->person_id, $valid->cart_id);

    $this->auth->send_auth_cookie($token, $expires);

    return $response->withRedirect('/account?success=login');
  }

  public function logout(Request $request, Response $response)
  {
    $domain= ($_SERVER['HTTP_HOST'] != 'localhost' ?
              $_SERVER['HTTP_HOST'] : false);

    SetCookie('loginToken', "", (new \Datetime("-24 hours"))->format("U"),
              '/', $domain, true, true);

    return $response->withRedirect('/login?success=logout');
  }
}
