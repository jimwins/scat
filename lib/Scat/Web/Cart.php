<?php
namespace Scat\Web;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Cart {
  public function __construct(
    private \Scat\Service\Data $data,
    private \Scat\Service\Catalog $catalog,
    private \Scat\Service\Config $config,
    private \Scat\Service\Shipping $shipping,
    private \Scat\Service\Tax $tax,
    private \Scat\Service\Auth $auth,
    private View $view
  ) {
  }

  public function cart(
    Request $request, Response $response,
    \Scat\Service\PayPal $paypal,
    \Scat\Service\AmazonPay $amzn
  ) {
    $cart= $request->getAttribute('cart');
    $person= $this->auth->get_person_details($request);

    if ($person) {
      if (!$cart->name) $cart->name= $person->name;
      if (!$cart->email) $cart->email= $person->email;
      if ($cart->person_id != $person->id) {
        $cart->person_id= $person->id;
      }
      $cart->save(); /* won't do anything if nothing changed */
    }

    $uri= $request->getUri();
    $routeContext= \Slim\Routing\RouteContext::fromRequest($request);
    $return_link= $routeContext->getRouteParser()->fullUrlFor(
      $uri,
      'checkout-amzn',
      [],
      [ 'uuid' => $cart->uuid ]
    );

    return $this->view->render($response, 'cart/index.html', [
      'person' => $person,
      'cart' => $cart,
      'paypal' => $paypal->getClientId(),
      'amzn' => $amzn->getEnvironment($return_link),
      'removed' => $request->getParam('removed'),
      'quantity' => $request->getParam('quantity'),
      'added' => $request->getParam('added'),
    ]);
  }

  public function cartUpdate(
    Request $request, Response $response,
    \Scat\Service\Stripe $stripe
  ) {
    $cart= $request->getAttribute('cart');
    $person= $this->auth->get_person_details($request);

    $recalculate= false;

    foreach ($request->getParams() as $key => $value) {
      switch ($key) {
        case 'email':
          // TODO validate
          $cart->email= $value;
          break;
        case 'name':
          $cart->name= $value;
          break;
        case 'address':
          if ($value['city']) {
            $cart->updateShippingAddress($this->shipping, [
              'name' => $cart->name,
              'street1' => $value['line1'],
              'street2' => $value['line2'] ?? '',
              'city' => $value['city'],
              'state' => $value['state'],
              'zip' => $value['postal_code'],
            ]);
            $recalculate= true;
          }
          break;

        case 'reward':
          if ($cart->loyalty_used()) {
            throw new \Exception("A loyalty reward is already being used.");
          }

          $points= $person->points_available;
          $loyaltyReward= $cart->loyalty_reward_available($points);
          if ($loyaltyReward->id != $value) {
            error_log("{$loyaltyReward->id} != {$value}");
            throw new \Exception("An invalid loyalty reward was attempted.");
          }

          $cart->addPayment(
            'loyalty', -$loyaltyReward->item()->retail_price, false
          );

          break;

        case 'method':
        case 'shipping_method':
          $cart->shipping_method= $value;
          $cart->recalculateTax($this->tax);
          break;

        default:
          throw new \Exception("Not allowed to change {$key}");
      }
    }

    if ($recalculate) {
      $cart->recalculate($this->shipping, $this->tax);
    }

    $cart->save();

    if ($cart->stripe_payment_intent_id) {
      $amount= $cart->due();

      $payment_intent= $stripe->getPaymentIntent($cart);

      if ($payment_intent->status == 'succeeded') {
        throw new \Exception(
          "Stripe payment intent {$payment_intent->id} already succeeded?!"
        );
      }

      $customer_details= [
        'email' => $cart->email,
        'name' => $cart->name,
        'metadata' => [
          "person_id" => $cart->person_id,
        ],
      ];

      if ($cart->shipping_address_id > 1) {
        $address= $cart->shipping_address();

        $customer_details['shipping']= [
          'name' => $address->name,
          'phone' => $address->phone,
          'address' => [
            'line1' => $address->street1,
            'line2' => $address->street2,
            'city' => $address->city,
            'state' => $address->state,
            'postal_code' => $address->zip,
            'country' => 'US',
          ],
        ];
      }

      $intent_options= [
        'amount' => $amount * 100,
      ];

      if ($payment_intent->customer) {
        $stripe->updateCustomer($payment_intent->customer, $customer_details);
      } else {
        $customer= $stripe->createCustomer($customer_details);
        $intent_options['customer']= $customer->id;
      }

      $stripe->updatePaymentIntent(
        $cart->stripe_payment_intent_id,
        $intent_options
      );
    }

    $data= $cart->as_array();

    $data['cart_html']=
      $this->view->fetchBlock('cart/checkout.html', 'cart', [
        'person' => $person,
        'cart' => $cart,
      ]);
    $data['shipping_options_html']=
      $this->view->fetchBlock('cart/checkout.html', 'shipping_options', [
        'person' => $person,
        'cart' => $cart,
      ]);
    $data['loyalty_html']=
      $this->view->fetchBlock('cart/checkout.html', 'loyalty', [
        'person' => $person,
        'cart' => $cart,
      ]);

    return $response->withJson($data);
  }

  public function cartComment(
    Request $request, Response $response,
    \Scat\Service\Stripe $stripe
  ) {
    $cart= $request->getAttribute('cart');
    $person= $this->auth->get_person_details($request);

    // Save comment, not fatal if it doesn't work
    if (($comment= $request->getParam('comment'))) {
      $note= $cart->notes()->create([
        'sale_id' => $cart->id,
        'person_id' => $cart->person_id,
        'content' => $comment,
      ]);
      try {
        $note->save();
      } catch (\Exception $e) {
        error_log("Failed to save comment for {$cart->uuid}: {$comment}");
      }
    }

    return $response->withJson($cart);
  }

  public function applyTaxExemption(Request $request, Response $response) {
    $cart= $request->getAttribute('cart');
    $person= $this->auth->get_person_details($request);

    if (!$person) {
      throw new \Exception("Unable to apply exemption, nobody is logged in.");
    }

    if (!$cart->person_id) $cart->person_id= $person->id;
    $cart->tax_exemption= $person->exemption_certificate_id;

    $cart->recalculateTax($this->tax);

    $cart->save();

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/json') !== false) {
      return $response->withJson($cart);
    }

    return $response->withRedirect('/cart');
  }

  public function removeTaxExemption(Request $request, Response $response) {
    $cart= $request->getAttribute('cart');

    $cart->tax_exemption= null;

    $cart->recalculateTax($this->tax);

    $cart->save();

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/json') !== false) {
      return $response->withJson($cart);
    }

    return $response->withRedirect('/cart');
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

    $existing=
      $cart->items()
            ->where('item_id', $item->id)
            ->where_null('kit_id')
            ->find_one();

    if ($existing) {
      $existing->updateQuantity($existing->quantity + $quantity);
      $existing->save();
    } else {
      $line= $cart->items()->create([
        'sale_id' => $cart->id,
        'item_id' => $item->id,
      ]);

      $line->retail_price= $item->retail_price;
      $line->discount= $item->discount;
      $line->discount_type= $item->discount_type;
      $line->tic= $item->tic;

      $line->updateQuantity($quantity);

      $line->save();
    }

    $cart->recalculate($this->shipping, $this->tax);

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/json') !== false) {
      return $response->withJson($cart);
    }

    return $response->withRedirect('/cart');
  }

  public function updateItem(Request $request, Response $response)
  {
    $cart= $request->getAttribute('cart');

    $line_id= $request->getParam('line_id');
    if (!$line_id) throw new \Exception("Must specify a line to remove.");

    if ($cart->closed()) {
      throw new \Exception("Cart already completed, start another one.");
    }

    $quantity= $request->getParam('quantity');

    $line= $cart->items()->find_one($line_id);
    if (!$line) {
      throw new \Slim\Exception\HttpNotFoundException($request);
    }

    $line->updateQuantity($quantity);

    $line->save();

    $cart->recalculate($this->shipping, $this->tax);

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/json') !== false) {
      return $response->withJson($cart);
    }

    return $response->withRedirect('/cart');
  }

  public function removeItem(Request $request, Response $response)
  {
    $cart= $request->getAttribute('cart');

    $line_id= $request->getParam('line_id');
    if (!$line_id) throw new \Exception("Must specify a line to remove.");

    if ($cart->closed()) {
      throw new \Exception("Cart already completed, start another one.");
    }

    // get item details
    $line= $cart->items()->find_one($line_id);
    if (!$line) {
      throw new \Slim\Exception\HttpNotFoundException($request);
    }

    $item= $line->item();

    $details= [
      'removed' => $item->code,
      'quantity' => $line->quantity,
    ];

    $line->delete(); /* takes care of kit items, too */

    $cart->recalculate($this->shipping, $this->tax);

    $cart->save();

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/json') !== false) {
      return $response->withJson($cart);
    }

    /* Bounce back to the cart with details so item can be re-added */
    $routeContext= \Slim\Routing\RouteContext::fromRequest($request);
    $link= $routeContext->getRouteParser()->urlFor('cart', [], $details);

    return $response->withRedirect($link);
  }

  public function guestCheckout(Request $request, Response $response) {
    $cart= $request->getAttribute('cart');

    $cart->email= $request->getParam('email');

    return $response->withRedirect('/cart/checkout');
  }

  public function checkout(
    Request $request, Response $response,
    \Scat\Service\PayPal $paypal,
    \Scat\Service\Stripe $stripe
  ) {
    $cart= $request->getAttribute('cart');
    $person= $this->auth->get_person_details($request);

    $paymentIntent= $stripe->getPaymentIntent($cart);

    $cart->stripe_payment_intent_id= $paymentIntent->id;
    $cart->save();

    return $this->view->render($response, 'cart/checkout.html', [
      'person' => $person,
      'cart' => $cart,
      'stripe' => [
        'key' => $stripe->getPublicKey(),
        'payment_intent' => $paymentIntent,
      ],
      'paypal' => $paypal->getClientId(),
    ]);
  }

  public function amznCheckout(
    Request $request, Response $response,
    \Scat\Service\AmazonPay $amzn
  ) {
    $cart= $request->getAttribute('cart');
    $person= $this->auth->get_person_details($request);

    $amzn_session_id= $request->getParam('amazonCheckoutSessionId');

    $session= $amzn->getCheckoutSession($amzn_session_id);

    if ($cart->amz_order_reference_id &&
        $cart->amz_order_reference_id != $session->checkoutSessionId)
    {
      error_log("Got new checkoutSessionId, hope that's okay.");
    }

    $cart->amz_order_reference_id= $session->checkoutSessionId;

    $name= $session->buyer->name;
    $email= $session->buyer->email;

    if (!$cart->name) $cart->name= $name;
    if (!$cart->email) $cart->email= $email;

    // TODO handle $session->shippingAddress->countryCode != 'US'

    $amzn_address= [
      'name' => $session->shippingAddress->name,
      // we put addressLine3 in company, because why not?
      'company' =>  $session->shippingAddress->addressLine3,
      'phone' =>  $session->shippingAddress->phoneNumber ?? '',
      'street1' =>  $session->shippingAddress->addressLine1 ?? '',
      'street2' =>  $session->shippingAddress->addressLine2 ?? '',
      'city' =>  $session->shippingAddress->city,
      'state' =>  $session->shippingAddress->stateOrRegion,
      'zip' =>  $session->shippingAddress->postalCode,
    ];

    $cart->updateShippingAddress($this->shipping, $amzn_address);

    $cart->recalculate($this->shipping, $this->tax);

    $cart->save();

    return $this->view->render($response, 'cart/checkout-amzn.html', [
      'person' => $person,
      'cart' => $cart,
      'amzn' => $session,
    ]);
  }

  public function amznPay(
    Request $request, Response $response,
    \Scat\Service\AmazonPay $amzn
  ) {
    $cart= $request->getAttribute('cart');
    $person= $this->auth->get_person_details($request);

    // TODO validate

    $amzn_session_id= $cart->amz_order_reference_id;

    $uri= $request->getUri();
    $routeContext= \Slim\Routing\RouteContext::fromRequest($request);
    $link= $routeContext->getRouteParser()->fullUrlFor(
      $uri,
      'finalize-amzn',
      [],
      [ 'uuid' => $cart->uuid ]
    );

    $data= [
      'webCheckoutDetails' => [
        'checkoutResultReturnUrl' => $link,
      ],
      'paymentDetails' => [
        'paymentIntent' => 'Authorize',
        'canHandlePendingAuthorization' => false,
        'softDescriptor' => null,
        'chargeAmount' => [
          'amount' => $cart->due(),
          'currencyCode' => 'USD',
        ],
      ],
      'merchantMetadata' => [
        'merchantReferenceId' => $cart->id,
        'merchantStoreName' => 'Raw Materials Art Supplies',
        'noteToBuyer' => 'Your order of art supplies',
        'customInformation' => $cart->uuid,
      ],
    ];

    $session= $amzn->updateCheckoutSession($amzn_session_id, $data);

    // TODO handle errors
    if (!isset($session->statusDetails)) {
      throw new \Exception($session->message);
    }

    return $response->withRedirect(
      $session->webCheckoutDetails->amazonPayRedirectUrl
    );
  }

  public function amznFinalize(
    Request $request, Response $response,
    \Scat\Service\AmazonPay $amzn
  ) {
    $cart= $request->getAttribute('cart');
    $person= $this->auth->get_person_details($request);

    // TODO validate

    $amzn_session_id= $cart->amz_order_reference_id;

    $data= [
      'chargeAmount' => [
        'amount' => $cart->due(),
        'currencyCode' => 'USD',
      ],
    ];

    $session= $amzn->completeCheckoutSession($amzn_session_id, $data);

    if (!isset($session->statusDetails)) {
      throw new \Exception($session->message);
    }

    $cart->addPayment('amazon', $cart->due(), false, $session);

    $cart->save();

    if ($cart->status != 'paid') {
      throw new \Exception("Not completely paid!");
    }

    $uri= $request->getUri();
    $routeContext= \Slim\Routing\RouteContext::fromRequest($request);
    $link= $routeContext->getRouteParser()->fullUrlFor(
      $uri,
      'sale-thanks',
      [ 'uuid' => $cart->uuid ]
    );

    /* Set cart id to -1 so cookie will get unset */
    $cart->id= -1;

    return $response->withRedirect($link);
  }

  public function setShipped(Request $request, Response $response)
  {
    $cart= $request->getAttribute('cart');

    $cart->shipping_address_id= 0;
    $cart->shipping_method= null;
    $cart->shipping= 0;
    $cart->shipping_tax= 0;

    $cart->recalculateTax($this->tax);

    $cart->save();

    return $response->withRedirect('/cart/checkout');
  }

  public function setCurbsidePickup(Request $request, Response $response) {
    $cart= $request->getAttribute('cart');

    $cart->shipping_address_id= 1;
    $cart->shipping_method= 'pickup';

    // and then recalculate sales tax
    $cart->recalculateTax($this->tax);

    $cart->save();

    return $response->withRedirect('/cart/checkout');
  }

  public function stripeFinalizeCart(
    Request $request, Response $response,
    \Scat\Service\Stripe $stripe,
    \Scat\Model\Cart $cart
  ) {
    $payment_intent_id= $cart->stripe_payment_intent_id;

    // if we already have it, don't do it again!
    $has= $cart->payments()
                ->where_raw(
                  'data->"$.payment_intent_id" = ?', [ $payment_intent_id ])
                ->find_one();
    if ($has) {
      error_log("Already processed Stripe payment $payment_intent_id\n");
      goto endStripeFinalize;
    }

    $payment_intent= $stripe->getPaymentIntent($cart);

    if ($payment_intent->status != 'succeeded') {
      throw new \Exception("Can only handle successful payment attempts here, got {$payment_intent->status}.");
    }

    foreach ($payment_intent->charges->data as $charge) {
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
        'payment_intent_id' => $payment_intent_id,
        'charge_id' => $charge->id,
        'cc_brand' => $cc_brand,
        'cc_last4' => $cc_last4,
      ];

      $cart->addPayment('credit', $charge->amount / 100, true, $data);
      $cart->stripe_payment_intent_id= null; // close this payment_intent out
    }

    $cart->save();

endStripeFinalize:

    if ($cart->status != 'paid') {
      throw new \Exception("Not completely paid!");
    }

    /* Set cart id to -1 so cookie will get unset */
    $cart->id= -1;

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/json') !== false) {
      return $response->withJson([ 'message' => 'Processed' ] );
    }

    if ($cart->status != 'paid') {
      throw new \Exception("Not completely paid!");
    }

    $uri= $request->getUri();
    $routeContext= \Slim\Routing\RouteContext::fromRequest($request);
    $link= $routeContext->getRouteParser()->fullUrlFor(
      $uri,
      'sale-thanks',
      [ 'uuid' => $cart->uuid ]
    );

    /* Set cart id to -1 so cookie will get unset */
    $cart->id= -1;

    return $response->withRedirect($link);
  }

  public function stripeFinalize(
    Request $request, Response $response,
    \Scat\Service\Stripe $stripe
  ) {
    $cart= $request->getAttribute('cart');

    $payment_intent= $request->getParam('payment_intent');

    if ($payment_intent != $cart->stripe_payment_intent_id)
    {
      error_log("{$payment_intent} != {$cart->stripe_payment_intent_id}");
      throw new \Exception("Got mismatching payment_intent");
    }

    return $this->stripeFinalizeCart(
      $request, $response,
      $stripe,
      $cart
    );
  }

  public function handleStripeWebhook(
    Request $request, Response $response,
    \Scat\Service\Stripe $stripe,
    \Scat\Service\Cart $carts
  ) {
    try {
      $event= $stripe->constructWebhookEvent(
        $request->getBody(),
        $request->getHeaderLine('Stripe-Signature')
      );
    } catch(\UnexpectedValueException $e) {
      // TODO should be 400 error
      throw new \Exception("Invalid payload");
    } catch(\Stripe\Exception\SignatureVerificationException $e) {
      // TODO should be 400 error
      throw new \Exception("Signature exception");
    }

    // Handle the event
    switch ($event->type) {
      case 'payment_intent.succeeded':
        $paymentIntent= $event->data->object; // contains a StripePaymentIntent
        $uuid= $paymentIntent->charges->data[0]->metadata->sale_uuid;
        if (!$uuid) {
          error_log("No uuid on payment_intent, probably a gift card");
          break;
        }

        $cart= $carts->findByUuid($uuid);
        if (!$cart) {
          throw new \Slim\Exception\HttpNotFoundException($request);
        }

        if ($cart->stripe_payment_intent_id != $paymentIntent->id) {
          error_log("Mismatch payment_id '{$cart->stripe_payment_intent_id}' != '{$paymentIntent->id}'");
          $cart->stripe_payment_intent_id= $paymentIntent->id;
        }

        return $this->stripeFinalizeCart(
          $request, $response,
          $stripe,
          $cart
        );

      case 'payment_intent.payment_failed':
        /* Don't do anything with these yet. */

      default:
        error_log("Ignoring {$event->type} from Stripe");
    }

    return $response->withJson([ 'message' => 'Success!' ]);
  }

  public function paypalOrder(
    Request $request, Response $response,
    \Scat\Service\PayPal $paypal
  ) {
    $cart= $request->getAttribute('cart');
    $person= $this->auth->get_person_details($request);

    /* Less detail to the amount if order is partially paid already. */
    if ($cart->total_paid()) {
      $amount= [
        'currency_code' => 'USD',
        'value' => $cart->due(),
      ];
    } else {
      $amount= [
        'currency_code' => 'USD',
        'value' => $cart->total(),
        'breakdown' => [
          'item_total' => [
            'currency_code' => 'USD',
            // TODO need to use Decimal here?
            'value' => $cart->subtotal() + cart->shipping,
          ],
          'tax_total' => [
            'currency_code' => 'USD',
          ],
        ]
      ];
    }

    $address= $cart->shipping_address();

    $shipping= [
      'name' => [
        'full_name' => $address->name ?: $address->company,
      ],
      'address' => [
        'address_line_1' => $address->street1,
        'address_line_2' => $address->street2,
        'admin_area_2' => $address->city,
        'admin_area_1' => $address->state,
        'postal_code' => $address->zip,
        'country_code' => 'US',
      ],
    ];

    $details= [
      'intent' => 'CAPTURE',
      'application_context' => [
        'shipping_preference' => 'SET_PROVIDED_ADDRESS',
        'user_action' => 'PAY_NOW',
      ],
      'purchase_units' => [
        [
          'reference_id' => $cart->uuid,
          'custom_id' => $cart->uuid,
          'amount' => $amount,
          'items' => [],
          'shipping' => $shipping,
        ]
      ],
    ];

    $order= null;

    try {
      if ($cart->paypal_order_id) {
        $order= $paypal->getOrder($cart->paypal_order_id);
      }
    } catch (\PayPalHttp\HttpException $e) {
      error_log("HttpException {$e->statusCode}");
      $cart->paypal_order_id= null;
    }

    if ($order) {
      if ($order->status == 'COMPLETED') {
        throw new \Exception("Payment already completed.");
      }

      $patch = [
        [
          'op' => 'replace',
          'path' => "/purchase_units/@reference_id=='{$cart->uuid}'/amount",
          'value' => $amount,
        ],
        [
          'op' => 'replace',
          'path' => "/purchase_units/@reference_id=='{$cart->uuid}'/shipping/name",
          'value' => $shipping['name'],
        ],
        [
          'op' => 'replace',
          'path' => "/purchase_units/@reference_id=='{$cart->uuid}'/shipping/address",
          'value' => $shipping['address'],
        ]
      ];

      $paypal->updateOrder($cart->paypal_order_id, $patch);
      error_log("Updated PayPal order ({$cart->paypal_order_id}) with new details");

      $order= $paypal->getOrder($cart->paypal_order_id);
    } else {
      $order= $paypal->createOrder($details);
      $cart->paypal_order_id= $order->id;
      error_log("Created PayPal order ({$cart->paypal_order_id})");
    }

    $cart->save();

    return $response->withJson($order);
  }

  public function paypalFinalize(
    Request $request, Response $response,
    \Scat\Service\PayPal $paypal
  ) {
    $cart= $request->getAttribute('cart');
    $person= $this->auth->get_person_details($request);

    // Save comment, not fatal if it doesn't work
    if (($comment= $request->getParam('comment'))) {
      $note= $cart->notes()->create([
        'sale_id' => $cart->id,
        'person_id' => $cart->person_id,
        'content' => $comment,
      ]);
      try {
        $note->save();
      } catch (\Exception $e) {
        error_log("Failed to save comment for {$cart->uuid}: {$comment}");
      }
    }

    $uuid= $cart->uuid;
    $order_id= $request->getParam('order_id');

    // TODO validate that $order_id = $cart->paypal_order_id

    return $this->paypalFinalizeCart(
      $request,
      $response,
      $paypal,
      $cart
    );
  }

  public function paypalFinalizeCart(
    Request $request, Response $response,
    \Scat\Service\PayPal $paypal,
    \Scat\Model\Cart $cart
  ) {
    $order_id= $cart->paypal_order_id;

    // TODO validate
    error_log("Finalizing PayPal {$order_id} on {$cart->uuid}");

    // if we already have it, don't do it again!
    $has= $cart->payments()
                ->where_raw(
                  'data->"$.id" = ?', [ $order_id ])
                ->find_one();
    if ($has) {
      error_log("Already processed PayPal payment $order_id\n");
      goto endPaypalFinalize;
    }

    $order= $paypal->getOrder($order_id);

    $amount= $order->purchase_units[0]->amount->value;

    $cart->addPayment('paypal', $amount, false, $order);

    $cart->save();

endPaypalFinalize:
    if ($cart->status != 'paid') {
      throw new \Exception("Not completely paid!");
    }

    /* Set cart id to -1 so cookie will get unset */
    $cart->id= -1;

    return $response->withJson([ 'message' => 'Processed' ] );
  }

  public function handlePayPalWebhook(
    Request $request, Response $response,
    \Scat\Service\PayPal $paypal,
    \Scat\Service\Cart $carts
  ) {
    $paypal->verifyWebhook($request);

    $event_type= $request->getParam('event_type');
    $resource= $request->getParam('resource');

    // Handle the event
    switch ($event_type) {
      case 'PAYMENT.CAPTURE.COMPLETED':
        $uuid= $resource['custom_id'];
        if (!$uuid) {
          error_log("No uuid on resource, dunno what this is");
          error_log(json_encode($resource));
          break;
        }

        $cart= $carts->findByUuid($uuid);
        if (!$cart) {
          throw new \Slim\Exception\HttpNotFoundException($request);
        }

        return $this->paypalFinalizeCart(
          $request,
          $response,
          $paypal,
          $cart
        );

      case 'CHECKOUT.ORDER.APPROVED':
        /* Ignore these */
        break;

      default:
        error_log("Ignoring {$event_type} from PayPal");
        throw new \Slim\Exception\HttpNotFoundException($request);
    }

    return $response->withJson([ 'message' => 'Success!' ]);
  }
}
