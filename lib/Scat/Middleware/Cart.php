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
        error_log("UUID {$this->cart->uuid} as param, UUID {$cookies['cartID']} as cookie!");
      } else {
        $uuid= $cookies['cartID'];
      }
    }

    if ($uuid) {
      error_log("Loading cart $uuid");
      $cart= $this->cart->findByUuid($uuid);
    } else {
      $cart= $this->cart->create();
    }

    if (!$cart) {
      error_log("Cart not found for $uuid");
    }

    $response= $handler->handle($request->withAttribute('cart', $cart));

    /* Update the cart cookie if necessary. */
    if ($cart->id && @$cookies['cartID'] != $cart->uuid) {
      $domain= ($_SERVER['HTTP_HOST'] != 'localhost' ?
                $_SERVER['HTTP_HOST'] : false);

      SetCookie('cartID', $cart->uuid, null /* don't expire */,
                '/', $domain, true, true);
    }

    return $response;
  }
}
