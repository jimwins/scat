<?php
namespace Scat\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class Cart implements MiddlewareInterface
{
  public function __construct(
    private \Scat\Service\Cart $cart
  ) {
  }

  public function process(
    ServerRequestInterface $request,
    RequestHandlerInterface $handler): ResponseInterface
  {
    $uuid= $request->getParam('uuid');

    /* Check cookie and request to see if we have a cart. */
    $cookies= $request->getCookieParams();
    if (isset($cookies['cartID'])) {
      if ($uuid && $cookies['cartID'] != $uuid) {
        error_log("UUID {$uuid} as param, UUID {$cookies['cartID']} as cookie!");
      } else {
        $uuid= $cookies['cartID'];
      }
    }

    if ($uuid) {
      error_log("Loading cart $uuid");
      $cart= $this->cart->findByUuid($uuid);
      if ($cart && $cart->status != 'cart') {
        if ($cart->status == 'paid') {
          error_log("Cart is already paid");
          // TODO this is ugly, but it works
          $this->dumpCookies();
          $response= $GLOBALS['app']->getResponseFactory()->createResponse();
          return $response->withRedirect(
            '/sale/' . $cart->uuid . '/thanks'
          );
        } else {
          error_log("Not sure what to do with a cart in '{$cart->status}' status\n");
        }
        unset($cart);
      }
    }

    if (!isset($cart)) {
      error_log("Creating new cart $uuid");
      $cart= $this->cart->create([ 'status' => 'cart' ]);
    }

    $response= $handler->handle($request->withAttribute('cart', $cart));

    /* TODO only update when necessary */
    $domain= ($_SERVER['HTTP_HOST'] != 'localhost' ?
              $_SERVER['HTTP_HOST'] : false);

    if ($cart->id <= 0) {
      $this->dumpCookies();
    } else {
      $details= json_encode([
        'items' => $cart->items()->count(),
        'total' => $cart->total()
      ]);
      SetCookie('cartID', $cart->uuid, null /* don't expire */,
                '/', $domain, true, false); /* JavaScript accessible */
      SetCookie('cartDetails', $details, 0 /* session cookie */,
                '/', $domain, true, false); /* JavaScript accessible */
    }

    return $response;
  }

  protected function dumpCookies() {
    $domain= ($_SERVER['HTTP_HOST'] != 'localhost' ?
              $_SERVER['HTTP_HOST'] : false);
    SetCookie('cartID', "", (new \Datetime("-24 hours"))->format("U"),
              '/', $domain, true, true);
    SetCookie('cartDetails', "", (new \Datetime("-24 hours"))->format("U"),
              '/', $domain, true, false);
  }
}
