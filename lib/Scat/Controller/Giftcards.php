<?php
namespace Scat\Controller;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Giftcards {
  private $data, $view;

  public function __construct(\Scat\Service\Data $data, View $view) {
    $this->data= $data;
    $this->view= $view;
  }

  public function home(Request $request, Response $response) {
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

    \ORM::get_db()->beginTransaction();

    $card= $this->data->factory('Giftcard')->create();

    $card->set_expr('pin', 'SUBSTR(RAND(), 5, 4)');
    if ($expires) {
      $card->expires= $expires . ' 23:59:59';
    }
    $card->active= 1;

    $card->save();

    /* Reload the card to make sure we have calculated values */
    $card= $this->data->factory('Giftcard')->find_one($card->id);

    if ($balance) {
      $txn= $card->txns()->create();
      $txn->amount= $balance;
      $txn->card_id= $card->id;
      if ($txn_id) $txn->txn_id= $txn_id;
      $txn->save();
    }

    \ORM::get_db()->commit();

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

  public function printCard(Request $request, Response $response, $card) {
    $card= $this->fetch($card);

    $body= $response->getBody();
    $body->write($card->getPDF());
    return $response->withHeader("Content-type", "application/pdf");
  }

  public function getEmailForm(Request $request, Response $response, $card) {
    return $this->view->render($response, 'dialog/email-gift-card.html',
                                [ "card" => $card ]);
  }

  public function emailCard(Request $request, Response $response,
                            \Scat\Service\Email $email, $card)
  {
    $card= $this->fetch($card);

    $email_body= $this->view->fetch('email/gift-card.html',
                                    $request->getParams());
    $subject= $this->view->fetchBlock('email/gift-card.html',
                                      'title',
                                      $request->getParams());

    $giftcard_pdf= $card->getPDF();

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

    return $response->withJson($res->body() ?:
                                [ "message" => "Email sent." ]);
  }

  public function addTransaction(Request $request, Response $response, $card) {
    $card= $this->fetch($card);
    $card->add_txn($request->getParam('amount'),
                   $request->getParam('txn_id'));
    return $response->withJson($card);
  }
}
