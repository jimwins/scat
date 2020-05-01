<?php
namespace Scat\Controller;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Respect\Validation\Validator as v;

class SMS {
  function send(Request $request, Response $response,
                \Scat\Service\Phone $phone) {
    $data= $phone->sendSMS($request->getParam('to'),
                           $request->getParam('text'));
    return $response->withJson($data);
  }

  function register(Request $request, Response $response,
                    \Scat\Service\Phone $phone) {
    $data= $phone->registerWebhook();
    return $response->withJson($data);
  }

  function receive(Request $request, Response $response,
                    \Scat\Service\Data $data, \Scat\Service\Config $config,
                    \Scat\Service\Phone $phone) {
    if ($request->getParam('type') != 'sms.in') {
      error_log("Did not understand type of message from Phone.com\n");
      error_log(json_encode($request->getParams()));
      return $response;
    }

    $payload= $request->getParam('payload');

    if (strtolower($payload['phone']) == 'help') {
      $message= $config->get('rewards.help_message');
      $phone->sendSMS($payload['from_did'], $message);
      return $response;
    }

    $loyalty= $payload['from_did'];
    $loyalty= preg_replace('/^\+1/', '', $loyalty); # toss leading +1

    $person= $data->factory('Person')
                  ->where_any_is([ [ 'loyalty_number' => $loyalty ], ])
                  ->find_one();

    if (!$person) {
      $person= $data->factory('Person')->create();
      $person->setProperty('phone', $loyalty);
      $person->save();
    }

    switch (strtolower($payload['message'])) {
    case 'rewardsplus':
    case 'unstop':
      $person->rewardsplus= 1;
      $person->save();
      $message= $config->get('rewards.signup_message');
      $compliance= 'Reply STOP to unsubscribe or HELP for help. 6 msgs per month, Msg&Data rates may apply.';
      $phone->sendSMS($person->loyalty_number, $message);
      $phone->sendSMS($person->loyalty_number, $compliance);
      break;

    case 'stop':
      $person->rewardsplus= 0;
      $person->save();
      $message= $config->get('rewards.stop_message');
      $phone->sendSMS($person->loyalty_number, $message);
      $phone->sendSMS($person->loyalty_number, $compliance);
      break;

    default:
      $note= $data->factory('Note')->create();
      $note->kind= 'person';
      $note->attach_id= $person->id;
      $note->source= 'sms';
      $note->person_id= $person->id;
      $note->content= $payload['message'];
      $note->todo= 1;
      $note->save();
    }

    return $response;
  }
}
