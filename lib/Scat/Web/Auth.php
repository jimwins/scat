<?php
namespace Scat\Web;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Auth {
  public function __construct(
    private \Scat\Service\Data $data,
    private \Scat\Service\Auth $auth,
    private \Scat\Service\Scat $scat,
    private \Scat\Service\Config $config,
    private View $view
  ) {
  }

  public function account(Request $request, Response $response)
  {
    $person= $this->auth->get_person_details($request);

    if (!$person) {
      return $response->withRedirect('/login');
    }

    $page= $request->getParam('page') ?: 0;
    $limit= 25;

    $orders= $this->scat->get_orders($person->id, $page, $limit);
    $wishlist= $this->data->factory('Wishlist')->where('person_id', $person->id)->find_one();

    return $this->view->render($response, "account/index.html", [
      'success' => $request->getParam('success'),
      'person' => $person,
      'orders' => $orders,
      'wishlist' => $wishlist,
      'page' => $page,
      'limit' => $limit,
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

    $cart_uuid= $request->getParam('cart_uuid');

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

      $email->send([ $person->email => $person->name], $subject, $body, null, [
        'no_bcc' => true
      ]);

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

    /* If this was a key with a cart, start checkout */
    if ($valid->cart) {
      return $response->withRedirect('/cart?uuid=' . $valid->cart);
    }

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

  public function processSignup(Request $request, Response $response)
  {
    $name= trim($request->getParam('name'));
    $email= trim($request->getParam('email'));
    $phone= trim($request->getParam('phone'));

    if (!$email && !$phone) {
      throw new \Exception("You need to provide an email or phone number.");
    }

    if (!v::email()->validate($email)) {
      throw new \Exception("Sorry, you must provide a valid email address.");
    }

    $key= $this->config->get('cleantalk.key');
    if ($key && $email) {
      $ip= $request->getAttribute('ip_address');

      $req= new \Cleantalk\CleantalkRequest();
      $req->auth_key= $key;
      $req->agent= 'php-api';
      $req->sender_email= $email;
      $req->sender_ip= $ip;
      $req->sender_nickname= $name;
      $req->js_on= $request->getParam('scriptable');
      // Calculate how long they took to fill out the form
      $when= $request->getParam('when');
      $now= $request->getServerParam('REQUEST_TIME_FLOAT');
      $req->submit_time= (int)($now - $when);

      $ct= new \Cleantalk\Cleantalk();
      $ct->server_url= 'http://moderate.cleantalk.org/api2.0/';

      $res= $ct->isAllowUser($req);
      if ($res->allow == 1) {
        error_log("User allowed. Reason = " . $res->comment);
      } else {
        error_log("User forbidden. Reason = " . $res->comment);
        throw new \Exception("Sorry, there was a problem processing your email address.");
      }

    }

    $signup= $this->data->factory('Signup')->create();

    $signup->name= $name;
    $signup->email= $email;
    $signup->phone= $phone;
    $signup->loyalty_number= preg_replace('/\D+/', '', $phone);
    $signup->code= $request->getParam('receipt_code');
    $signup->subscribe= $request->getParam('subscribe') ?? 0;
    $signup->rewardsplus= $request->getParam('plus') ?? 0;

    $signup->save();

    $data= [];
    if ($signup->subscribe) {
      $data['subscribe']= 1;
    }
    if ($signup->code) {
      $data['code']= 1;
    }

    return $response->withRedirect(
      '/reward-thanks' . (count($data) ? '?' . http_build_query($data) : '')
    );
  }

  public function getPendingSignups(Request $request, Response $response)
  {
    if (!$this->auth->verify_access_key($request->getParam('key'))) {
      throw new \Slim\Exception\HttpForbiddenException($request, "Wrong key");
    }

    $signups=
      $this->data->factory('Signup')->where('processed', 0)->find_many();
    return $response->withJson($signups);
  }

  public function markSignupProcessed(Request $request, Response $response)
  {
    if (!$this->auth->verify_access_key($request->getParam('key'))) {
      throw new \Slim\Exception\HttpForbiddenException($request, "Wrong key");
    }

    $signup_id= (int)$request->getParam('id');

    $signup= $this->data->factory('Signup')->find_one($signup_id);
    if (!$signup) {
      throw new \Slim\Exception\HttpNotFoundException($request);
    }

    $signup->processed= 1;
    $signup->save();

    return $response->withJson($signup);
  }
}
