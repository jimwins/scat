<?php
namespace Scat\Web;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Wishlist {
  public function __construct(
    private \Scat\Service\Catalog $catalog,
    private \Scat\Service\Data $data,
    private \Scat\Service\Auth $auth,
    private View $view
  ) {
  }

  protected function getCurrentWishlist($request, $person) {
    $wishlist= null;

    /* Check cookie and request to see if we have a wishlist */
    $cookies= $request->getCookieParams();
    if (isset($cookies['wishlistID'])) {
      $wishlist=
        $this->data->factory('Wishlist')->where('uuid', $cookies['wishlistID'])->find_one();

      // XXX validate wishlist (right person, etc)
    } else if ($person) {
      $wishlist=
        $this->data->factory('Wishlist')->where('person_id', $person->id)->find_one();
    }

    if (!$wishlist) {
      $wishlist= $this->data->factory('Wishlist')->create();
      if ($person) {
        $wishlist->person_id= $person->id;
      }
      // Could use real UUID() but this is shorter. Hardcoded '2' could be
      // replaced with a server-id to further avoid collisions
      $wishlist->uuid= sprintf("%08x%02x%s", time(), 2, bin2hex(random_bytes(8)));
      $wishlist->save();
    }

    $this->updateWishlistCookies($wishlist);

    return $wishlist;
  }

  protected function updateWishlistCookies($wishlist) {
    $domain= ($_SERVER['HTTP_HOST'] != 'localhost' ?  $_SERVER['HTTP_HOST'] : false);

    $details= json_encode([
      'items' => $wishlist->items()->count()
    ]);
    SetCookie('wishlistID', $wishlist->uuid, strtotime("+365 days"),
              '/', $domain, true, false); /* JavaScript accessible */
    SetCookie('wishlistDetails', $details, 0 /* session cookie */,
              '/', $domain, true, false); /* JavaScript accessible */
  }

  public function top(Request $request, Response $response) {
    $person= $this->auth->get_person_details($request);
    $wishlist= $this->getCurrentWishlist($request, $person);

    if ($person && !$wishlist->person_id) {
      $wishlist->person_id= $person->id;
      $wishlist->save();
    }

    return $this->view->render($response, 'wishlist/index.html', [
      'person' => $person,
      'wishlist' => $wishlist,
    ]);
  }

  public function addItem(Request $request, Response $response) {
    $person= $this->auth->get_person_details($request);
    $wishlist= $this->getCurrentWishlist($request, $person);

    $item_code= trim($request->getParam('item') ?? '');
    $quantity= max((int)$request->getParam('quantity'), 1);

    // get item details
    $item= $this->catalog->getItemByCode($item_code);
    if (!$item) {
      throw new \Slim\Exception\HttpNotFoundException($request);
    }

    $this->data->beginTransaction();

    /* If this is a brand new wishlist, it won't have an ID yet. Save to create! */
    if (!$wishlist->id) {
      $wishlist->save();
    }

    $existing=
      $wishlist->items()
            ->where('item_id', $item->id)
            ->find_one();

    if ($existing) {
      $existing->quantity= $existing->quantity + $quantity;
      $existing->save();
    } else {
      $line= $wishlist->items()->create([
        'wishlist_id' => $wishlist->id,
        'item_id' => $item->id,
      ]);

      $line->quantity= $quantity;

      $line->save();
    }

    $wishlist->save();

    $this->data->commit();

    $this->updateWishlistCookies($wishlist);

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/json') !== false) {
      return $response->withJson($wishlist);
    }

    return $response->withRedirect('/wishlist');
  }

  public function removeItem(Request $request, Response $response) {
    $person= $this->auth->get_person_details($request);
    $wishlist= $this->getCurrentWishlist($request, $person);

    $item_id= $request->getParam('item_id');

    $item= $wishlist->items()->where('id', $item_id)->find_one();

    $item->delete();

    $this->updateWishlistCookies($wishlist);

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/json') !== false) {
      return $response->withJson($wishlist);
    }

    return $response->withRedirect('/wishlist');
  }

  public function show(Request $request, Response $response, $uuid) {
    $person= $this->auth->get_person_details($request);

    $wishlist= $this->data->factory('Wishlist')->where('uuid', $uuid)->find_one();
    if (!$wishlist) {
      throw new \Slim\Exception\HttpNotFoundException($request);
    }

    return $this->view->render($response, 'wishlist/shared.html', [
      'person' => $person,
      'wishlist' => $wishlist,
    ]);
  }
}
