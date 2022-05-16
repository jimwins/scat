<?php
namespace Scat\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class Cart implements MiddlewareInterface
{
  private $cart;

  public function __construct(\Scat\Service\Cart $cart) {
    $this->cart= $cart;
  }

  public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
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
        error_log("Cart is already complete");
        if ($cart->status == 'paid') {
          // TODO this is ugly, but it works
          $response= $GLOBALS['app']->getResponseFactory()->createResponse();
          return $response->withRedirect(
            '/sale/' . $cart->uuid . '/thanks'
          );
        }
        unset($cart);
      }
    }

    if (!isset($cart)) {
      $cart= $this->cart->create([ 'status' => 'cart' ]);
    }

    $response= $handler->handle($request->withAttribute('cart', $cart));

    /* Update the cart cookie if necessary. */
    if (@$cookies['cartID'] != $cart->uuid) {
      $domain= ($_SERVER['HTTP_HOST'] != 'localhost' ?
                $_SERVER['HTTP_HOST'] : false);

      if ($cart->id <= 0) {
        SetCookie('cartID', "", (new \Datetime("-24 hours"))->format("U"),
                  '/', $domain, true, true);
        SetCookie('cartDetails', "", (new \Datetime("-24 hours"))->format("U"),
                  '/', $domain, true, false);
      } else {
        $details= json_encode([
          'items' => $cart->items()->count(),
          'total' => $cart->total()
        ]);
        SetCookie('cartID', $cart->uuid, null /* don't expire */,
                  '/', $domain, true, true);
        SetCookie('cartDetails', "", 0 /* session cookie */,
                  '/', $domain, true, false); /* Javascript accessible */
      }
    }

    return $response;
  }
}
