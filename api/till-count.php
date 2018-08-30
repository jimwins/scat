<?php
require '../scat.php';

$count= $_REQUEST['count'];
if (empty($count))
  die_jsonp("Must have counted something!");

$withdrawal= $_REQUEST['withdrawal'];

ORM::get_db()->beginTransaction();

$current= Model::factory('Payment')
            ->raw_query("SELECT CAST(SUM(amount) AS DECIMAL(9,2)) AS amount
                           FROM payment
                          WHERE method IN ('cash','change','withdrawal')")
            ->find_one();

$number= Model::factory('Txn')
           ->raw_query("SELECT MAX(number) + 1 AS number
                          FROM txn
                         WHERE type = 'drawer'")
           ->find_one();

$txn= Model::factory('Txn')->create();

$txn->type= 'drawer';
$txn->number= $number->number;
$txn->set_expr('created', 'NOW()');
$txn->set_expr('filled', 'NOW()');
$txn->set_expr('paid', 'NOW()');
$txn->tax_rate= 0.0;

$txn->save();

if ($count != $current->amount) {
  $amount= $count - $current->amount;

  $payment= Model::factory('Payment')->create();
  $payment->txn= $txn->id;
  $payment->method= 'cash';
  $payment->amount= $amount;
  $payment->set_expr('processed', 'NOW()');

  $payment->save();
}

if ($withdrawal) {
  $payment= Model::factory('Payment')->create();
  $payment->txn= $txn->id;
  $payment->method= 'withdrawal';
  $payment->amount= -$withdrawal;
  $payment->set_expr('processed', 'NOW()');

  $payment->save();
}

ORM::get_db()->commit();

echo jsonp(array('txn_id' => $txn->id));
