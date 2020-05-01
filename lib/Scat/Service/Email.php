<?php
namespace Scat\Service;

class Email {
  private $sendgrid_key;
  public $from_email, $from_name;

  public function __construct(Config $config) {
    $this->sendgrid_key= $config->get('sendgrid.key');
    $this->from_email= $config->get('email.from_email');
    $this->from_name= $config->get('email.from_name');
  }

  public function send($to, $subject, $body, $attachments= null) {
    $email= new \SendGrid\Mail\Mail();

    $email->setFrom($this->from_email, $this->from_name);
    $email->setSubject($subject);

    /* We might have just gotten an email in $to */
    foreach (is_array($to) ? $to : [ $to => '' ] as $address => $name) {
      /* For DEBUG, always just send to ourselves */
      if ($GLOBALS['DEBUG']) {
        $address= $this->from_email;
      }

      $email->addTo($address, $name);

      /* And just send to one recipient. */
      if ($GLOBALS['DEBUG']) {
        break;
      }
    }

    /* Always Bcc ourselves (for now) */
    if (!$GLOBALS['DEBUG']) {
      $email->addBcc($this->from_email, $this->from_name);
    }

    $email->addContent("text/html", $body);

    if ($attachments) {
      $email->addAttachments($attachments);
    }

    $sendgrid= new \SendGrid($this->sendgrid_key);

    return $sendgrid->send($email);
  }

}
