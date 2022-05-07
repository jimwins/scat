<?php
namespace Scat\Service;
use \Slim\Http\ServerRequest as Request;

class Auth
{
  private $data, $scat;

  public function __construct(
    \Scat\Service\Data $data,
    \Scat\Service\Scat $scat
  ) {
    $this->data= $data;
    $this->scat= $scat;
  }

  public function generate_auth_token($person_id, $expires, $cart_uuid) {
    $selector= bin2hex(random_bytes(6));
    $validator= base64_encode(random_bytes(24));
    $token= hash('sha256', $validator);

    $auth_token= $this->data->factory('AuthToken')->create();
    $auth_token->selector= $selector;
    $auth_token->token= $token;
    $auth_token->person_id= $person_id;
    $auth_token->expires= $expires->format('Y-m-d H:i:s');
    $auth_token->cart= $cart_uuid;

    $auth_token->save();

    return "$selector:$validator";
  }

  public function validate_auth_token($token) {
    $parts= explode(':', $token);
    if (count($parts) != 2) return false;

    list($selector, $validator)= $parts;
    if (!$selector || !$validator) {
      return false;
    }

    $auth_token=
      $this->data->factory('AuthToken')
            ->where('selector', $selector)->find_one();

    if (!$auth_token) {
      return false;
    }

    if ($auth_token->expires &&
        new \Datetime() > new \Datetime($auth_token->expires))
    {
      $auth_token->delete();
      return false; // expired
    }

    if (hash_equals($auth_token->token, hash('sha256', $validator))) {
      return $auth_token;
    }

    return false; // failed validation
  }

  public function generate_login_key($person_id, $cart_uuid) {
    $expires= new \Datetime('+24 hours');
    return $this->generate_auth_token($person_id, $expires, $cart_uuid);
  }

  public function send_auth_cookie($token, $expires) {
    $domain= ($_SERVER['HTTP_HOST'] != 'localhost' ?
              $_SERVER['HTTP_HOST'] : false);

    SetCookie('loginToken', $token, $expires->format('U'),
              '/', $domain, true, true);
  }

  public function get_authenticated_person_id(Request $request) {
    $cookies= $request->getCookieParams();
    if (isset($cookies['loginToken'])) {
      $login_token= $cookies['loginToken'];
      $auth_token= $this->validate_auth_token($login_token);
      if ($auth_token) {
        /* Push out expiry of token if more than a day since we've seen it */
        if (new \Datetime('-1 day') > new \Datetime($auth_token->modified)) {
          $expires= new \Datetime('+14 days');
          $auth_token->expires= $expires->format('Y-m-d H:i:s');
          $auth_token->save();

          $this->send_auth_cookie($login_token, $expires);
        }

        return $auth_token->person_id;
      }
    }
    return false;
  }

  public function get_person_details(Request $request) {
    $person_id= $this->get_authenticated_person_id($request);
    if (!$person_id) {
      return false;
    }

    return $this->scat->get_person_details($person_id);
  }
}
