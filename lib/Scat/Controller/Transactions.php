<?php
namespace Scat\Controller;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Transactions {
  private $view, $txn;

  public function __construct(View $view, \Scat\Service\Txn $txn) {
    $this->view= $view;
    $this->txn= $txn;
  }

  public function sales(Request $request, Response $response) {
    $page= (int)$request->getParam('page');
    $limit= 25;
    $txns= $this->txn->find('customer', $page, $limit);
    return $this->view->render($response, 'txn/index.html', [
      'type' => 'customer',
      'txns' => $txns,
      'page' => $page,
      'limit' => $limit,
    ]);
  }

  public function newSale(Response $response) {
    ob_start();
    include "../old-index.php";
    $content= ob_get_clean();
    return $this->view->render($response, 'sale/old-new.html', [
      'title' => $GLOBALS['title'],
      'content' => $content,
    ]);
  }

  public function sale(Response $response, $id) {
    return $response->withRedirect("/?id=$id");
  }

  public function emailForm(Request $request, Response $response, $id) {
    $txn= $this->txn->fetchById($id);

    return $this->view->render($response, 'dialog/email-invoice.html', [
      'txn' => $txn
    ]);
  }

  public function email(Request $request, Response $response, $id,
                        \Scat\Service\Email $email)
  {
    $txn= $this->txn->fetchById($id);

    $to_name= $request->getParam('name');
    $to_email= $request->getParam('email');
    $subject= trim($request->getParam('subject'));

    $body= $this->view->fetch('email/invoice.html', [
      'txn' => $txn,
      'subject' => $subject,
      'content' =>
        $request->getParam('content'),
    ]);

    $attachments= [];
    if ($request->getParam('include_details')) {
      $pdf= $txn->getInvoicePDF();
      $attachments[]= [
        base64_encode($pdf->Output('', 'S')),
        'application/pdf',
        (($txn->type == 'vendor') ? 'PO' : 'I') .
          $txn->formatted_number() . '.pdf',
        'attachment'
      ];
    }

    $res= $email->send([ $to_email => $to_name ],
                       $subject, $body, $attachments);

    return $response->withJson($res->body() ?:
                                [ "message" => "Email sent." ]);
  }
}
