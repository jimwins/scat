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
    $this->cart->uuid= $request->getParam('uuid');

    /* Check cookie and request to see if we have a cart. */
    $cookies= $request->getCookieParams();
    if (isset($cookies['cartID'])) {
      if ($this->cart->uuid && $cookies['cartID'] != $this->cart->uuid) {
        error_log("UUID {$this->cart->uuid} as param, UUID {$cookies['cartID']} as cookie!");
      } else {
        $this->cart->uuid= $cookies['cartID'];
      }
    }

    $response= $handler->handle($request->withAttribute('cart', $this->cart));

    /* Update the cart cookie. */
    if ($this->cart->uuid) {
      $domain= ($_SERVER['HTTP_HOST'] != 'localhost' ?
          $_SERVER['HTTP_HOST'] : false);

      SetCookie('cartID', $this->cart->uuid, null /* don't expire */,
                '/', $domain, true, true);
    }

    return $response;
  }
}
