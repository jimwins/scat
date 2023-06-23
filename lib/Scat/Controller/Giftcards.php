<?php
namespace Scat\Controller;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Giftcards {
  private $data, $gift, $view;

  public function __construct(\Scat\Service\Data $data, \Scat\Service\Giftcard $gift, View $view) {
    $this->data= $data;
    $this->gift= $gift;
    $this->view= $view;
  }

  public function home(Request $request, Response $response) {
    $page= (int)$request->getParam('page');
    $page_size= 25;

    $cards= $this->data->factory('Giftcard')
              ->select('*')
              ->select_expr('COUNT(*) OVER()', 'total')
              ->order_by_desc('id')
              ->limit($page_size)->offset($page * $page_size)
              ->where('active', 1)
              ->find_many();

    return $this->view->render($response, 'gift-card/index.html', [
      'cards' => $cards,
      'error' => $request->getParam('error'),
    ]);
  }

  protected function fetch($card) {
    $card= preg_replace('/^RAW-/', '', $card);
    $id= substr($card, 0, 7);
    $pin= substr($card, -4);
    return $this->data->factory('Giftcard')
            ->where('id', $id)
            ->where('pin', $pin)
            ->find_one();
  }

  public function lookup(Request $request, Response $response) {
    $card= $this->fetch($request->getParam('card'));

    if ($card) {
      return $response->withRedirect("/gift-card/" . $card->card());
    } else {
      return $response->withRedirect("/gift-card?error=not-found");
    }
  }

  public function create(Request $request, Response $response) {
    $expires= $request->getParam('expires');
    $txn_id= $request->getParam('txn_id');
    $balance= $request->getParam('balance');

    $this->data->beginTransaction();

    $card= $this->gift->create($expires);

    /* Reload the card to make sure we have calculated values */
    $card= $this->data->factory('Giftcard')->find_one($card->id);

    if ($balance) {
      $txn= $card->txns()->create();
      $txn->amount= $balance;
      $txn->card_id= $card->id;
      if ($txn_id) $txn->txn_id= $txn_id;
      $txn->save();
    }

    $this->data->commit();

    return $response->withJson($card);
  }

  public function card(Request $request, Response $response, $card) {
    $card= $this->fetch($card);

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/json') !== false) {
      return $response->withJson($card);
    }

    return $this->view->render($response, 'gift-card/card.html', [
      'card' => $card,
    ]);
  }

  public function printCard(
    Request $request,
    Response $response,
    \Scat\Service\Printer $print,
    $card
  ) {
    $card= $this->fetch($card);

    $pdf= $print->generateFromTemplate('print/gift-card.html', [
      'card' => $card,
    ]);

    if ($request->getParam('download')) {
      $response->getBody()->write($pdf);
      return $response->withHeader('Content-type', 'application/pdf');
    }

    return $print->printPDF($response, 'letter', $pdf);
  }

  public function getEmailForm(Request $request, Response $response, $card) {
    $txn= $this->fetch($card)->txns()->order_by_asc('id')->find_one();

    if ($txn && ($txn= $txn->txn())) {
      $to= $txn->shipping_address();
      $from= $txn->person();
      $message= $txn->notes()->order_by_asc('id')->find_one();
    }

    return $this->view->render($response, 'dialog/email-gift-card.html', [
      "card" => $card,
      "to_name" => isset($to) ? $to->name : '',
      "to_email" => isset($to) ? $to->email : '',
      "from_name" => isset($from) ? $from->name : '',
      "message" => isset($message) ? $message->content : '',
    ]);
  }

  public function emailCard(
    Request $request,
    Response $response,
    \Scat\Service\Email $email,
    \Scat\Service\Printer $print,
    $card
  ) {
    $card= $this->fetch($card);

    $email_body= $this->view->fetch('email/gift-card.html',
                                    $request->getParams());
    $subject= $this->view->fetchBlock('email/gift-card.html',
                                      'title',
                                      $request->getParams());

    $giftcard_pdf= $print->generateFromTemplate('print/gift-card.html', [
      'card' => $card,
    ]);


    $from_name= $request->getParam('from_name');
    $from= $from_name ? "$from_name via " . $email->from_name
                      : $email->from_name;
    $to_name= $request->getParam('to_name');
    $to_email= $request->getParam('to_email');

    $attachments= [
      [
        base64_encode($giftcard_pdf),
        'application/pdf',
        'Gift Card.pdf',
      ]
    ];

    $res= $email->send([ $to_email => $to_name ],
                       $subject, $email_body, $attachments,
                       [
                        'from' => [
                          'name' => $from,
                          'email' => $email->from_email
                        ]
                       ]);

    return $response->withJson([ "message" => "Email sent." ]);
  }

  public function addTransaction(Request $request, Response $response, $card) {
    $card= $this->fetch($card);
    $card->add_txn($request->getParam('amount'),
                   $request->getParam('txn_id'));
    return $response->withJson($card);
  }
}
