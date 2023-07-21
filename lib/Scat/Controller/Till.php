<?php
namespace Scat\Controller;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Till {
  private $txn, $data;

  public function __construct(\Scat\Service\Txn $txn, \Scat\Service\Data $data)
  {
    $this->txn= $txn;
    $this->data= $data;
  }

  protected function getExpected() {
    return $this->txn->getPayments()
                ->where_in('method', [ 'cash', 'change', 'withdrawal' ])
                ->sum('amount');
  }

  public function home(Request $request, Response $response, View $view) {
    return $view->render($response, "till/index.html", [
      'expected' => $this->getExpected(),
    ]);
  }

  public function printChangeOrder(Request $request, Response $response,
                                   \Scat\Service\Printer $printer)
  {
    return $printer->printFromTemplate(
      $response, 'receipt',
      'print/change-order.html',
      $request->getParams()
    );
  }

  public function count(Request $request, Response $response) {
    try {
      v::numericVal()::positive()->assert($request->getParam('counted'));
      v::numericVal()->assert($request->getParam('withdraw'));
    } catch (\Respect\Validation\Exceptions\ValidationException $e) {
      return $response->withJson([
        'error' => "Validation failed.",
        'validation_errors' => $e->getMessages()
      ]);
    }

    $counted= $request->getParam('counted');
    $withdraw= $request->getParam('withdraw');

    $expected= $this->getExpected();

    $this->data->beginTransaction();

    $txn= $this->txn->create('drawer');

    if ($counted != $expected) {
      $amount= $counted - $expected;

      $payment= $txn->payments()->create();
      $payment->txn_id= $txn->id;
      $payment->method= 'cash';
      $payment->amount= $amount;
      $payment->set_expr('processed', 'NOW()');

      $payment->save();
    }

    if ($withdraw) {
      $payment= $txn->payments()->create();
      $payment->txn_id= $txn->id;
      $payment->method= 'withdrawal';
      $payment->amount= -$withdraw;
      $payment->set_expr('processed', 'NOW()');

      $payment->save();
    }

    $this->data->commit();

    return $response->withJson([ 'expected' => $this->getExpected() ]);
  }

  public function withdrawCash(Request $request, Response $response) {
    try {
      v::numericVal()->positive()->assert($request->getParam('amount'));
      v::stringType()->notBlank()->assert($request->getParam('reason'));
    } catch (\Respect\Validation\Exceptions\ValidationException $e) {
      return $response->withJson([
        'error' => "Validation failed.",
        'validation_errors' => $e->getMessages()
      ]);
    }

    $amount= $request->getParam('amount');
    $reason= $request->getParam('reason');

    $this->data->beginTransaction();

    $txn= $this->txn->create('drawer');
    $txn->filled= $txn->created;
    $txn->status= 'complete';

    $payment= $txn->payments()->create();
    $payment->txn_id= $txn->id;
    $payment->method= 'withdrawal';
    $payment->amount= -$amount;
    $payment->set_expr('processed', 'NOW()');

    $payment->save();

    $note= $txn->notes()->create();
    $note->kind= 'txn';
    $note->attach_id= $txn->id;
    $note->content= $reason;

    $note->save();

    $txn->save();

    $this->data->commit();

    return $response->withJson($txn);
  }

}
