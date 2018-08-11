<?
include '../scat.php';
include '../lib/giftcard.php';

use Michelf\Markdown;

$card= (int)$_REQUEST['card'];

$id= substr($card, 0, 7);
$pin= substr($card, -4);

$card= Model::factory('Giftcard')
         ->where('id', $id)
         ->where('pin', $pin)
         ->find_one();

if (!$card) {
  die_jsonp(array("error" => "Unable to find info for that card."));
}

if (!$_REQUEST['message'])
  die_jsonp("Message required.");

$to_name= $_REQUEST['to_name'];
$to_email= $_REQUEST['to_email'];

$from_name= $_REQUEST['from_name'];

if (!$to_email)
  die_jsonp(array("error" => "Need at least a destination address."));

$message= $_REQUEST['message'];

$subject= "Gift Card for $to_name";

$preheader= "You have a gift card for Raw Materials Art Supplies!";

$message= preg_replace('/\n/', "\n>", $message);

$template=<<<HTML
Hi,

You've been sent a gift card for Raw Materials Art Supplies. The PDF is attached, just print it out and bring it to the store (or show us the card on your phone).

We were asked to pass along this message with the card:

>$message

Thanks!
HTML;

$content= Markdown::defaultTransform($template);

// XXX poor man's template for now
$html= file_get_contents('../ui/giftcard-email.html');
$html= preg_replace('/{{ @subject }}/', $subject, $html);
$html= preg_replace('/{{ @preheader }}/', $preheader, $html);
$html= preg_replace('/{{ @content }}/', $content, $html);

$giftcard_pdf= $card->getPDF();

// XXX fix hardcoded name
$from= $from_name ? "$from_name via Raw Materials Art Supplies"
                  : "Raw Materials Art Supplies";

$httpClient= new \Http\Adapter\Guzzle6\Client(new \GuzzleHttp\Client());
$sparky= new \SparkPost\SparkPost($httpClient,
                                  [ 'key' => SPARKPOST_KEY ]);

$promise= $sparky->transmissions->post([
  'content' => [
    'html' => $html,
    'subject' => $subject,
    'from' => array('name' => $from,
                    'email' => OUTGOING_EMAIL_ADDRESS),
    'attachments' => [
      [
        'name' => 'Gift Card.pdf',
        'type' => 'application/pdf',
        'data' => base64_encode($giftcard_pdf),
      ]
    ],
  ],
  'substitution_data' => $data,
  'recipients' => [
    [
      'address' => [
        'name' => $to_name,
        'email' => $to_email,
      ],
    ],
    [
      // BCC ourselves
      'address' => [
        'name' => $to_name,
        'header_to' => $to_email,
        'email' => OUTGOING_EMAIL_ADDRESS,
      ],
    ],
  ],
  'options' => [
    'inlineCss' => true,
    'transactional' => true,
  ],
]);

try {
  $response= $promise->wait();

  echo jsonp(array("message" => "Email sent."));
} catch (\Exception $e) {
  error_log(sprintf("SparkPost failure: %s (%s)",
                    $e->getMessage(), $e->getCode()));
  die_jsonp(sprintf("SparkPost failure: %s (%s)",
                    $e->getMessage(), $e->getCode()));
}
