<?php
namespace Scat\Web;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Cart {
  private $view, $catalog, $data;

  public function __construct(
    \Scat\Service\Data $data,
    \Scat\Service\Catalog $catalog,
    \Scat\Service\Auth $auth,
    View $view
  ) {
    $this->data= $data;
    $this->auth= $auth;
    $this->catalog= $catalog;
    $this->view= $view;
  }

  public function cart(Request $request, Response $response)
  {
    $cart= $request->getAttribute('cart');
    $person= $this->auth->get_person_details($request);

    return $this->view->render($response, 'cart/index.html', [
      'person' => $person,
      'cart' => $cart,
    ]);
  }

  public function addItem(Request $request, Response $response)
  {
    $cart= $request->getAttribute('cart');

    // attach it to person, if logged in

    $item_code= trim($request->getParam('item'));
    $quantity= max((int)$request->getParam('quantity'), 1);

    if ($cart->closed()) {
      throw new \Exception("Cart already completed, start another one.");
    }

    // get item details
    $item= $this->catalog->getItemByCode($item_code);
    if (!$item) {
      throw new \Slim\Exception\HttpNotFoundException($request);
    }

    /* If this is a brand new cart, it won't have an ID yet. Save to create! */
    if (!$cart->id) {
      $cart->save();
    }

    // TODO handle kits
    // TODO add quantity to existing line instead of changing quantity

    $line= $cart->items()->create([
      'sale_id' => $cart->id,
      'item_id' => $item->id,
    ]);

    // TODO update price on existing lines
    $line->retail_price= $item->retail_price;
    $line->discount= $item->discount;
    $line->discount_type= $item->discount_type;
    $line->tic= $item->tic;

    $line->quantity+= $quantity;

    $line->save();

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/json') !== false) {
      return $response->withJson($cart);
    }

    return $response->withRedirect('/cart');
  }
}
