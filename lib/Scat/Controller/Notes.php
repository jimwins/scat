<?php
namespace Scat\Controller;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Notes {
  private $data;

  public function __construct(\Scat\Service\Data $data) {
    $this->data= $data;
  }

  public function view(Request $request, Response $response,
                        View $view, $id= 0) {
    $kind= $request->getParam('kind');
    $attach_id= $request->getParam('attach_id');
    $staff= $this->data->factory('Person')
              ->where('role', 'employee')
              ->where('person.active', 1)
              ->order_by_asc('name')
              ->find_many();

    if (!$id) {
      $notes= $this->data->factory('Note')
                ->select('*')
                ->select_expr('(SELECT COUNT(*)
                                  FROM note children
                                 WHERE children.parent_id = note.id)',
                              'children')
                ->where('parent_id', 0)
                ->order_by_desc('id');
      if ($kind) {
        $notes= $notes->where('kind', $kind)
                      ->where('attach_id', $attach_id);
      } else {
        $notes= $notes->where('todo', 1);
      }
      $notes= $notes->find_many();
    } else {
      $notes= $this->data->factory('Note')
                ->select('*')
                ->select_expr('0', 'children')
                ->where_any_is([
                  [ 'id' => $id ],
                  [ 'parent_id' => $id ]
                ])
                ->order_by_asc('id')
                ->find_many();
    }

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/json') !== false) {
      return $response->withJson($notes);
    }

    if ($request->getParam('body_only')) {
      $block= $view->fetchBlock('dialog/notes.html', 'form', [
        'parent_id' => $id,
        'kind' => $kind,
        'attach_id' => $attach_id,
        'staff' => $staff,
        'notes' => $notes
      ]);

      $response->getBody()->write($block);
      return $response;
    } else {
      return $view->render($response, 'dialog/notes.html', [
        'parent_id' => $id,
        'kind' => $kind,
        'attach_id' => $attach_id,
        'staff' => $staff,
        'notes' => $notes
      ]);
    }
  }

  public function create(Request $request, Response $response,
                    \Scat\Service\Phone $phone) {
    $sms= (int)$request->getParam('sms');

    $note= $this->data->factory('Note')->create();
    $note->source= $sms ? 'sms' : 'internal';
    $note->kind= $request->getParam('kind') ?: 'general';
    $note->attach_id= $request->getParam('attach_id') ?: null;
    $note->parent_id= (int)$request->getParam('parent_id');
    $note->person_id= (int)$request->getParam('person_id');
    $note->content= $request->getParam('content');
    $note->todo= (int)$request->getParam('todo');
    $note->public= (int)$request->getParam('public');

    if ($sms) {
      try {
        $person= $note->about();
        if (!$person) {
          throw new \Exception("Nobody to send an SMS to.");
        }
        error_log("Sending message to {$person->phone}");
        $data= $phone->sendSMS($person->phone,
                                     $request->getParam('content'));
        $note->save();
       } catch (\Exception $e) {
         error_log("Got exception: " . $e->getMessage());
       }
    }

    $note->save();

    $response= $response->withStatus(201)
                        ->withHeader('Location', '/note/' . $note->id);
    return $response->withJson($note);
  }

  public function update(Request $request, Response $response, $id) {
    $this->data->beginTransaction();

    $note= $this->data->factory('Note')->find_one($id);
    if (!$note) {
      throw new \Slim\Exception\HttpNotFoundException($request);
    }

    $todo= $request->getParam('todo');
    if ($todo !== null && $todo != $note->todo) {
      $note->todo= (int)$request->getParam('todo');
      $update= $this->data->factory('Note')->create();
      // TODO who did this?
      $update->parent_id= $note->parent_id ?: $note->id;
      $update->content= $todo ? "Marked todo." : "Marked done.";
      $update->save();
    }

    $public= $request->getParam('public');
    if ($public !== null && $public != $note->public) {
      $note->public= (int)$request->getParam('public');
      $update= $this->data->factory('Note')->create();
      // TODO who did this?
      $update->parent_id= $note->parent_id ?: $note->id;
      $update->content= $public ? "Marked public." : "Marked private.";
      $update->save();
    }

    $note->save();

    $this->data->commit();

    return $response->withJson($note);
  }
}
