<?php
namespace Scat\Service;

class Phone
{
  private $token;
  private $from;

  public function __construct(array $config) {
    $this->token= $config['token'];
    $this->account_id= $config['account_id'];
    $this->from= $config['from'];
  }

  public function sendSMS($to, $text) {
    $curl= curl_init();

    $data= [ 'from' => $this->from, 'to' => $to, 'text' => $text ];

    curl_setopt_array($curl, array(
        CURLOPT_URL =>
          "https://api.phone.com/v4/accounts/{$this->account_id}/sms",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => array(
            "authorization: Bearer " . $this->token,
            "cache-control: no-cache",
            "content-type: application/json",
      ),
    ));

    $response= curl_exec($curl);
    $err= curl_error($curl);

    curl_close($curl);

    if ($err) {
      throw new \Exception("cURL Error #:" . $err);
    } else {
      return json_decode($response);
    }
  }
}
