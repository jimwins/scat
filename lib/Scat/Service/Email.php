<?php
namespace Scat\Service;

class Email {
  private $postmark_token;
  public $from_email, $from_name;

  public function __construct(Config $config) {
    $this->postmark_token= $config->get('postmark.token');
    $this->from_email= $config->get('email.from_email');
    $this->from_name= $config->get('email.from_name');
  }

  public function default_from_address() {
    return [
      'name' => $this->from_name,
      'email' => $this->from_email,
    ];
  }

  public function send($to, $subject, $body,
                        $attachments= null, $options= null)
  {
    $postmark= new \Postmark\PostmarkClient($this->postmark_token);

    if (isset($options['from'])) {
      $from= $options['from']['name'] . " " . $options['from']['email'];
    } else {
      $from= $this->from_name . " " . $this->from_email;
    }

    $addr= [];

    /* Convert [ email = '', name = '' ] to [ email => name ] */
    if (is_array($to) && array_key_exists('email', $to)) {
      $to= [ $to['email'] => @$to['name'] ];
    }

    /* We might have just gotten an email in $to */
    foreach (is_array($to) ? $to : [ $to => '' ] as $address => $name) {
      /* For DEBUG, always just send to ourselves */
      if ($GLOBALS['DEBUG']) {
        $address= $this->from_email;
      }

      error_log("Sending email to $name <$address>\n");
      $addr[]= str_replace(',', '', $name) . " " . $address;

      /* And just send to one recipient. */
      if ($GLOBALS['DEBUG']) {
        break;
      }
    }
    $to_list= join(', ', $addr);

    $cc= NULL;
    if (isset($options['cc'])) {
      $cc= $options['cc'];
      if ($GLOBALS['DEBUG']) {
        $cc= $this->from_email;
      }
      error_log("Sending email cc: to <$cc>\n");
    }

    /* Always Bcc ourselves (for now) */
    $bcc= NULL;
    if (!$GLOBALS['DEBUG'] && !@$options['no_bcc']) {
      $bcc= $this->from_name . " " . $this->from_email;
    }

    $attach= [ ];

    if ($attachments) {
      foreach ($attachments as $part) {
        $attach[]= \Postmark\Models\PostmarkAttachment::fromBase64EncodedData(
          $part[0], $part[2], $part[1]
        );
      }
    }

    return $postmark->sendEmail(
      $from, $to_list, $subject, $body, NULL, NULL, NULL,
      @$options['replyTo'], $cc, $bcc, NULL, $attach, NULL, NULL,
      @$options['stream']
    );
  }

}
