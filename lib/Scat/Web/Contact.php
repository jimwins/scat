<?php
namespace Scat\Web;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Contact {
  public function __construct(
    private View $view,
    private \Scat\Service\Config $config,
    private \Scat\Service\Email $email,
    private \Scat\Service\Data $data
  ) {
  }

  function handleContact(Request $request, Response $response)
  {
    $email= trim($request->getParam('email'));
    if (!v::email()->validate($email)) {
      throw new \Exception("Sorry, you must provide a valid email address.");
    }

    $donation= (int)$request->getParam('donation');

    $name= trim($request->getParam('name'));
    $ip= $request->getAttribute('ip_address');

    $subject= $request->getParam('subject') ?: 'Contact';
    $message= $request->getParam('comment');

    $key= $this->config->get('cleantalk.key');
    if ($key) {
      $req= new \Cleantalk\CleantalkRequest();
      $req->auth_key= $key;
      $req->agent= 'php-api';
      $req->sender_email= $email;
      $req->sender_ip= $ip;
      $req->sender_nickname= $name;
      $req->js_on= $request->getParam('scriptable');
      $req->message= $message;
      // Calculate how long they took to fill out the form
      $when= $request->getParam('when');
      $now= $request->getServerParam('REQUEST_TIME_FLOAT');
      $req->submit_time= (int)($now - $when);

      $ct= new \Cleantalk\Cleantalk();
      $ct->server_url= 'http://moderate.cleantalk.org/api2.0/';

      $res= $ct->isAllowMessage($req);
      if ($res->allow == 1) {
        error_log("Message allowed. Reason = " . $res->comment);
      } else {
        error_log("Message forbidden. Reason = " . $res->comment);
        throw new \Exception("Sorry, your message looks like spam.");
      }
    }

    if (preg_match('/(B[li]ahGaky)/i', $name)) {
      throw new \Exception("Sorry, your message looks like spam.");
    }


    if (preg_match('/(erype|bitcoin|cryptocurrency|sexy?.*girl|seowriters|telegra\.ph|goo\\.gl|go\\.obermatsa\\.com|0j35)/i', $message)) {
      throw new \Exception("Sorry, your message looks like spam.");
    }

    $body= $this->view->fetch('email/contact.html', [
      'TIME' => $request->getServerParam('REQUEST_TIME_FLOAT'),
      'name' => $name,
      'email' => $email,
      'subject' => $subject,
      'message' => $message,
      'ip' => $ip,
      'request' => $request->getParams(),
    ]);

    /* We are sending mail to ourselves, but we set the Reply-To */
    $to= $this->email->default_from_address();

    $this->email->send($to, $subject, $body, NULL, [ 'replyTo' => $email, 'no_bcc' => true ]);

    $dest= $donation ? '/contact/donation-request-received' : '/contact/thanks';

    return $response->withRedirect($dest);
  }
}
