<?php
namespace Scat\Controller;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class SMS {
  private $phone;

  public function __construct(\Scat\Service\Phone $phone) {
    $this->phone= $phone;
  }

  function home(Request $request, Response $response, View $view) {
    return $view->render($response, 'sms/index.html', [
    ]);
  }

  function send(Request $request, Response $response) {
    $data= $this->phone->sendSMS(
      $request->getParam('to'),
      $request->getParam('text')
    );
    return $response->withJson($data);
  }

  function sendRewardsPlus(Request $request, Response $response,
                            \Scat\Service\Data $data)
  {
    $people= $data->factory('Person')
      ->where('rewardsplus', 1)
      ->where('active', 1)
      ->where_in('preferred_contact', [ 'any', 'text' ])
      ->find_many();

    $message= $request->getParam('message');
    $message.= " Reply STOP to cancel";

    if (strlen($message) > 160) {
      throw new \Exception("Message is too long.");
    }

    // TODO should use an external queue for this, but we don't have many to
    // send right now so premature optimization and all that

    $sent= 0;
    foreach ($people as $person) {
      if ($person->loyalty_number) {
        $this->phone->sendSMS($person->loyalty_number, $message);
        sleep(1);
        $sent++;
        set_time_limit(20); // keep resetting php's time limit
      }
    }

    return $response->withJson([
      'message' => sprintf("Sent %d message%s.", $sent, $sent != 1 ? 's' : '')
    ]);
  }

  function register(Request $request, Response $response) {
    $data= $this->phone->registerWebhook();
    return $response->withJson($data);
  }

  function receive(Request $request, Response $response,
                    \Scat\Service\Data $data, \Scat\Service\Config $config)
  {
    if ($request->getParam('type') != 'sms.in') {
      error_log("Did not understand type of message from Phone.com\n");
      error_log(json_encode($request->getParams()));
      return $response;
    }

    $payload= $request->getParam('payload');

    if (strtolower($payload['phone']) == 'help') {
      $message= $config->get('rewards.help_message');
      $this->phone->sendSMS($payload['from_did'], $message);
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
      $this->phone->sendSMS($person->loyalty_number, $message);
      $this->phone->sendSMS($person->loyalty_number, $compliance);
      break;

    case 'stop':
      $person->rewardsplus= 0;
      $person->save();
      $message= $config->get('rewards.stop_message');
      $this->phone->sendSMS($person->loyalty_number, $message);
      $this->phone->sendSMS($person->loyalty_number, $compliance);
      break;

    default:
      /* If there is an ongoing conversation, attach to that */
      $convo= $data->factory('Note')
                ->where('attach_id', $person->id)
                ->where('kind', 'person')
                ->where('todo', 1)
                ->where('parent_id', 0)
                ->find_one();

      $note= $data->factory('Note')->create();
      if ($convo) {
        $note->parent_id= $convo->id;
      }
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
