<?php
namespace Scat\Controller;

use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class People {
  public function home(Request $request, Response $response, View $view,
                        \Scat\Service\Data $data) {
    $limit= $request->getParam('limit') ?: 20;
    $people= $data->factory('Person')
                  ->select('*')
                  ->select_expr('(SELECT MAX(IFNULL(txn.paid, txn.created))
                                    FROM txn WHERE txn.person_id = person.id)',
                                'latest')
                  ->where('active', 1)
                  ->order_by_desc('latest')
                  ->limit($limit)
                  ->find_many();

    $accept= $request->getHeaderLine('Accept');
    if ($select2 || strpos($accept, 'application/json') !== false) {
      return $response->withJson($people);
    }
    return $view->render($response, 'person/index.html', [
      'people' => $people
    ]);
  }

  public function search(Request $request, Response $response, View $view,
                          \Scat\Service\Data $data) {
    $q= trim($request->getParam('q'));
    $loyalty= trim($request->getParam('loyalty'));

    if ($q) {
      $people= \Scat\Model\Person::find($q);
    } elseif ($loyalty) {
      $loyalty_number= preg_replace('/[^\d]/', '', $loyalty);
      $person= $data->factory('Person')
                    ->where_any_is([
                      [ 'loyalty_number' => $loyalty_number ?: 'no' ],
                      [ 'email' => $loyalty ]
                    ])
                    ->find_one();
      $people= $person ? [ $person ] : [];
    } else {
      $people= [];
    }

    $select2= $request->getParam('_type') == 'query';
    $accept= $request->getHeaderLine('Accept');
    if ($select2 || strpos($accept, 'application/json') !== false) {
      return $response->withJson($people);
    }

    return $view->render($response, 'person/index.html', [
      'people' => $people,
      'q' => $q
    ]);
  }

  public function person(Request $request, Response $response, $id, View $view,
                          \Scat\Service\Data $data)
  {
    $person= $data->factory('Person')->find_one($id);

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/json') !== false) {
      return $response->withJson($person);
    }

    $page= (int)$request->getParam('page');
    $limit= 25;

    return $view->render($response, 'person/person.html', [
      'person' => $person,
      'page' => $page,
      'limit' => $limit,
    ]);
  }

  public function items(Request $request, Response $response, $id, View $view,
                        \Scat\Service\Data $data) {
    $person= $data->factory('Person')->find_one($id);
    $page= (int)$request->getParam('page');

    if ($person->role != 'vendor') {
      throw new \Exception("That person is not a vendor.");
    }

    $limit= 25;

    $q= $request->getParam('q');

    $items= \Scat\Model\VendorItem::search($person->id, $q);
    $items= $items->select_expr('COUNT(*) OVER()', 'total')
                  ->limit($limit)->offset($page * $limit);
    $items= $items->find_many();

    return $view->render($response, 'person/items.html', [
     'person' => $person,
     'items' => $items,
     'q' => $q,
     'page' => $page,
     'limit' => $limit,
     'page_size' => $page_size,
    ]);
  }

  function uploadItems(Request $request, Response $response, $id,
                        \Scat\Service\Data $data) {
    $person= $data->factory('Person')->find_one($id);
    if (!$person)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $details= [];
    foreach ($request->getUploadedFiles() as $file) {
      $details[]= $person->loadVendorData($file);
    }

    return $response->withJson([
      'details' => $details
    ]);
  }

  public function createPerson(Request $request, Response $response,
                                \Scat\Service\Data $data) {
    $person= $data->factory('Person')->create();
    return $this->processPerson($request, $response, $data, $person);
  }

  public function updatePerson(Request $request, Response $response, $id,
                                \Scat\Service\Data $data) {
    $person= $data->factory('Person')->find_one($id);
    if (!$person)
      throw new \Slim\Exception\HttpNotFoundException($request);

    return $this->processPerson($request, $response, $data, $person);
  }

  public function processPerson(Request $request, Response $response,
                                \Scat\Service\Data $data, $person) {
    $dirty= false;
    foreach ($person->getFields() as $field) {
      if ($field == 'id') continue; // don't allow changing id
      $value= $request->getParam($field);
      if ($value !== null) {
        $person->setProperty($field, $value);
        $dirty= true;
      }
    }

    $new= $person->is_new();

    if ($dirty) {
      try {
        $person->save();
      } catch (\PDOException $e) {
        if ($e->getCode() == '23000') {
          throw new \Scat\Exception\HttpConflictException($request);
        } else {
          throw $e;
        }
      }
    } else {
      return $response->withStatus(304);
    }

    if ($new) {
      $response= $response->withStatus(201)
                          ->withHeader('Location', '/person/' . $person->id);
    }

    return $response->withJson($person);
  }

  public function loyalty(Request $request, Response $response, $id, View $view,
                          \Scat\Service\Data $data) {
    $person= $data->factory('Person')->find_one($id);

    $activity= $person->loyalty()->order_by_desc('id')->find_many();

    return $response->withJson($activity);
  }

}
