<?php
namespace Scat\Web;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Giftcard {
  public function __construct(
    private View $view,
    private \Scat\Service\Auth $auth,
    private \Scat\Service\Cart $carts,
    private \Scat\Service\Catalog $catalog,
    private \Scat\Service\Email $email,
    private \Scat\Service\Stripe $stripe
  ) {
  }

  public function form(Request $request, Response $response)
  {
    $person= $this->auth->get_person_details($request);

    /* Create the intent for $1 just to get started */
    $paymentIntent= $this->stripe->createPaymentIntent([ 'amount' => 100 ]);

    return $this->view->render($response, 'buy-gift-card.html', [
      'person' => $person,
      'stripe' => [
        'key' => $this->stripe->getPublicKey(),
        'payment_intent' => $paymentIntent,
      ],
    ]);
  }

  public function process(Request $request, Response $response)
  {
    switch ($request->getParam('action')) {
      case 'update':
        $payment_intent_id= $request->getParam('payment_intent_id');
        $amount= trim($request->getParam('amount'));
        $amount= preg_replace('/^\$/', '', $amount); // strip leading $
        if ($amount == '' || !preg_match('/^\d*(\.\d*)?$/', $amount)) {
          throw new \Exception("Invalid amount ({$amount}).");
        }
        $amount= (int)($amount * 100);
        $payment_intent= $this->stripe->updatePaymentIntent(
          $payment_intent_id,
          [ 'amount' => $amount ]
        );

        return $response->withJson($payment_intent);

        break;

      case 'finalize':
        $sale= $this->carts->create([
          'name' => $request->getParam('name'),
          'email' => $request->getParam('email'),
          'stripe_payment_intent_id' => $request->getParam('payment_intent_id'),
        ]);
        $sale->save();

        $dest= $sale->belongs_to('CartAddress')->create([
          'name' => $request->getParam('recipient_name'),
          'email' => $request->getParam('recipient_email'),
          'street1' => '',
          'city' => '',
          'state' => '',
          'zip' => '',
        ]);
        $dest->save();
        $sale->shipping_address_id= $dest->id;

        $payment_intent= $this->stripe->getPaymentIntent($sale);

        $item= $this->catalog->getItemByCode('ZZ-GIFTCARD');

        $charge= $this->stripe->getCharge($payment_intent->latest_charge);
        $line= $sale->items()->create([
          'sale_id' => $sale->id,
          'item_id' => $item->id,
          'quantity' => 1,
          'retail_price' => $charge->amount / 100,
          'tic' => $item->tic,
          'tax' => 0.00,
        ]);
        $line->save();

        if ($charge->payment_method_details->type == 'afterpay_clearpay') {
          $cc_brand= 'AfterPay';
          $cc_last4= '';
        } if ($charge->payment_method_details->type == 'link') {
          $cc_brand= 'Link';
          $cc_last4= '';
        } else {
          $cc_brand= ucwords($charge->payment_method_details->card->brand);
          $cc_last4= $charge->payment_method_details->card->last4;
        }

        $data= [
          'payment_intent_id' => $payment_intent->id,
          'charge_id' => $charge->id,
          'cc_brand' => $cc_brand,
          'cc_last4' => $cc_last4,
        ];

        $sale->addPayment('credit', $charge->amount / 100, true, $data);

        $sale->set_expr('tax_calculated', 'NOW()');
        $sale->status= 'paid';
        $sale->stripe_payment_intent_id= NULL;
        $sale->save();

        if (($comment= $request->getParam('comment'))) {
          $note= $sale->notes()->create([
            'sale_id' => $sale->id,
            'person_id' => $sale->person_id ?? 0,
            'content' => $comment,
          ]);
          try {
            $note->save();
          } catch (\Exception $e) {
            error_log("Failed to save comment for {$sale->uuid}: {$comment}");
          }
        }

        return $response->withRedirect('/gift-card/thanks');

      default:
        throw new \Exception("Don't know what to do about that.");
    }
  }
}
