<?php
require '../scat.php';
require '../lib/txn.php';
require '../lib/catalog.php';

$reason= $_REQUEST['reason'];
if (!$reason)
  die_jsonp("Must provide a reason for withdrawal.");

$amount= (float)$_REQUEST['amount'];
if (!$amount)
  die_jsonp("Must provide an amount to withdraw.");

ORM::get_db()->beginTransaction();

$txn= Model::factory('Txn')->create();

$number= Model::factory('Txn')
           ->raw_query("SELECT MAX(number) + 1 AS number
                          FROM txn
                         WHERE type = 'drawer'")
           ->find_one();

$txn->type= 'drawer';
$txn->number= $number->number;
$txn->set_expr('created', 'NOW()');
$txn->set_expr('filled', 'NOW()');
$txn->set_expr('paid', 'NOW()');
$txn->tax_rate= 0.0;

$txn->save();

$payment= Model::factory('Payment')->create();
$payment->txn= $txn->id;
$payment->method= 'withdrawal';
$payment->amount= -$amount;
$payment->set_expr('processed', 'NOW()');

$payment->save();

$note= Model::factory('Note')->create();
$note->kind= 'txn';
$note->attach_id= $txn->id;
$note->content= $reason;

$note->save();

ORM::get_db()->commit();

echo jsonp(array('message' =>
                 "Success! Withdrew " . amount($amount) . " from till."));
