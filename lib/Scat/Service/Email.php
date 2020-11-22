<?php
namespace Scat\Service;

class Email {
  private $postmark_key;
  public $from_email, $from_name;

  public function __construct(Config $config) {
    $this->postmark_token= $config->get('postmark.token');
    $this->from_email= $config->get('email.from_email');
    $this->from_name= $config->get('email.from_name');
  }

  public function send($to, $subject, $body,
                        $attachments= null, $options= null)
  {
    $postmark= new \Postmark\PostmarkClient($this->postmark_token);

    if ($options['from']) {
      $from= $options['from']['name'] . " " . $options['from']['email'];
    } else {
      $from= $this->from_name . " " . $this->from_email;
    }

    $addr= [];

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

    /* Always Bcc ourselves (for now) */
    $bcc= NULL;
    if (!$GLOBALS['DEBUG']) {
      $bcc= $this->from_name . " " . $this->from_email;
    }

    $logo= \Postmark\Models\PostmarkAttachment::fromFile(
      '../ui/logo.png',
      'logo.png',
      'image/png',
      'cid:logo.png',
    );

    $attach= [ $logo ];

    if ($attachments) {
      foreach ($attachments as $part) {
        $attach[]= \Postmark\Models\PostmarkAttachment::fromBase64EncodedData(
          $part[0], $part[2], $part[1]
        );
      }
    }

    return $postmark->sendEmail(
      $from, $to_list, $subject, $body, NULL, NULL, NULL,
      NULL, NULL, $bcc, NULL, $attach, NULL, NULL, $options['stream']
    );
  }

}
