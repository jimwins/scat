<?
include '../scat.php';
include '../lib/txn.php';

$id= (int)$_REQUEST['txn'];
if (!$id)
  die_jsonp("No transaction specified.");

$txn= new Transaction($db, $id);

if (!$txn)
  die_jsonp("No such transaction..");

$httpClient= new \Http\Adapter\Guzzle6\Client(new \GuzzleHttp\Client());
$sparky= new \SparkPost\SparkPost($httpClient,
                                  [ 'key' => SPARKPOST_KEY ]);

if (!$txn->person_details['email'])
  die_jsonp("No email address available for this transaction.");

$data= array(
  'sale' => array(
    'number' => $txn->formatted_number,
    'created' => $txn->created,
    'filled' => $txn->filled,
    'paid' => $txn->paid,
    'name' => $txn->person_details['name'],
    'email' => $txn->person_details['email'],
    'subtotal' => amount($txn->subtotal),
    'shipping' => '',
    'tax' => amount($txn->tax),
    'total' => amount($txn->total),
    'due' => amount($txn->due),
  ),
  'items' => array(),
  'payments' => array(),
);

foreach ($txn->items as $item) {
  $data['items'][]= array(
    'quantity' => $item['ordered'],
    'code' => $item['code'],
    'name' => $item['name'],
    'detail' => $item['discount'],
    'sale_price' => amount($item['price']),
    'ext_price' => amount($item['ext_price']),
  );
}

foreach ($txn->payments as $payment) {
  $data['payments'][]= array(
    'method' => $payment['method'],
    'processed' => $payment['processed'],
    'amount' => amount($payment['amount']),
    'discount' => $payment['discount'],
    'data' => array(
      'cc_brand' => $payment['cc_type'],
      'cc_last4' => $payment['cc_lastfour'],
    ),
  );
}

$promise= $sparky->transmissions->post([
  'content' => [
    'template_id' => 'scat-invoice',
  ],
  'substitution_data' => $data,
  'recipients' => [
    [
      'address' => [
        'name' => $data['sale']['name'],
        'email' => $data['sale']['email'],
      ],
    ],
  ],
  'options' => [
    'inlineCss' => true,
  ],
]);

try {
  $response= $promise->wait();

  echo json_encode(array("message" => "Email sent."));
} catch (\Exception $e) {
  error_log(sprintf("SparkPost failure: %s (%s)",
                    $e->getMessage(), $e->getCode()));
  die_jsonp(sprintf("SparkPost failure: %s (%s)",
                    $e->getMessage(), $e->getCode()));
}
