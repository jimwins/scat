<?php
namespace Scat\Model;

use \Respect\Validation\Validator as v;

class Person extends \Scat\Model {

  public function friendly_name() {
    if ($this->name || $this->company) {
      return $this->name .
             ($this->name && $this->company ? ' / ' : '') .
             $this->company;
    }
    if ($this->email) {
      return $this->email;
    }
    if ($this->phone) {
      return $this->pretty_phone();
    }
    return $this->id;
  }

  function pretty_phone() {
    if ($this->phone) {
      try {
        $phoneUtil= \libphonenumber\PhoneNumberUtil::getInstance();
        $num= $phoneUtil->parse($this->phone, 'US');
        return $phoneUtil->format($num,
                                  \libphonenumber\PhoneNumberFormat::NATIONAL);
      } catch (\Exception $e) {
        // Punt!
        return $this->phone;
      }
    }
  }

  public function loyalty() {
    return $this->has_many('Loyalty');
  }

  public function points_available() {
    if ($this->suppress_loyalty) return 0;
    return $this->loyalty()
                ->where_raw("(points < 0 OR DATE(processed) < DATE(NOW()))")
                ->sum('points');
  }

  public function points_pending() {
    if ($this->suppress_loyalty) return 0;
    return $this->loyalty()
                ->where_raw("(points > 0 AND DATE(processed) >= DATE(NOW()))")
                ->sum('points');
  }

  function available_loyalty_rewards() {
    $points= $this->points_available();

    return $this->factory('LoyaltyReward')->where_lte('cost', $points);
  }

  #[\ReturnTypeWillChange]
  public function jsonSerialize() {
    $data= parent::jsonSerialize();
    /* Need to decode our JSON field */
    $data['subscriptions']= json_decode($this->subscriptions);
    $data['friendly_name']= $this->friendly_name();
    $data['pretty_phone']= $this->pretty_phone();
    $data['points_available']= $this->points_available();
    $data['points_pending']= $this->points_pending();
    return $data;
  }

  static function find($q, $all= false) {
    $criteria= [];

    $terms= preg_split('/\s+/', trim($q));
    foreach ($terms as $term) {
      if (preg_match('/id:(\d*)/', $term, $m)) {
        $id= (int)$m[1];
        $criteria[]= "(person.id = $id)";
        $all= true;
      } else if (preg_match('/role:(.+)/', $term, $m)) {
        $role= addslashes($m[1]);
        $criteria[]= "(person.role = '$role')";
      } elseif (preg_match('/^active:(.+)/i', $term, $dbt)) {
        $criteria[]= $dbt[1] ? "(person.active)" : "(NOT person.active)";
        $all= true;
      } else {
        $term= addslashes($term);
        $criteria[]= "(person.name LIKE '%$term%'
                   OR person.company LIKE '%$term%'
                   OR person.email LIKE '%$term%'
                   OR person.loyalty_number LIKE '%$term%'
                   OR person.phone LIKE '%$term%'
                   OR person.instagram LIKE '%$term%'
                   OR person.notes LIKE '%$term%')";
      }
    }

    $sql_criteria= join(' AND ', $criteria);

    $people= self::factory('Person')->select('person.*')
                                      ->where_raw($sql_criteria)
                                      ->where_gte('person.active',
                                                  $all ? 0 : 1)
                                      ->where_not_equal('person.deleted', 1)
                                      ->order_by_asc('company')
                                      ->order_by_asc('name')
                                      ->order_by_asc('loyalty_number')
                                      ->find_many();

    return $people;
  }

  public function items($only_active= true) {
    if ($this->role != 'vendor') {
      throw new \Exception("People who are not vendors don't have items.");
    }
    return $this->has_many('VendorItem', 'vendor_id')
                ->where_gte('active', (int)$only_active);
  }

  public function open_orders() {
    return $this->has_many('Txn')
                ->where_in('status', [ 'new' ])
                ->find_many();
  }

  public function txns($page= 0, $limit= 25) {
    return $this->has_many('Txn')
                ->select('*')
                ->select_expr('COUNT(*) OVER()', 'records')
                ->order_by_desc('created')
                ->limit($limit)->offset($page * $limit);
  }

  public function setProperty($name, $value) {
    $value= trim($value);
    if ($name == 'phone') {
      v::optional(v::phone())->assert($value);
      $this->phone= $value ?: null;
      $this->loyalty_number= preg_replace('/[^\d]/', '', $value) ?: null;
    }
    else if ($name == 'email') {
      v::optional(v::email())->assert($value);
      $this->email= $value ?: null;
    }
    else if ($name == 'mailerlite_id') {
      $this->mailerlite_id= $value ?: null;
      if ($value) {
        $this->syncToMailerlite();
      }
    }
    else if ($name == 'rewardsplus') {
      /*
       * If this is a new opt-in to Rewards+, need to send welcome
       * and compliance message.
       */
      if ($value && !$this->rewardsplus && $this->loyalty_number) {
        $config= $GLOBALS['container']->get(\Scat\Service\Config::class);
        $phone= $GLOBALS['container']->get(\Scat\Service\Phone::class);
        $message= $config->get('rewards.signup_message');
        $compliance= 'Reply STOP to unsubscribe or HELP for help. 6 msgs per month, Msg&Data rates may apply.';
        //$phone->sendSMS($this->loyalty_number, $message);
        //$phone->sendSMS($this->loyalty_number, $compliance);
      }
      $this->$name= $value;
    }
    /* If we already have a giftcard attached, attaching a new one will
     * transfer the balance. */
    else if ($name == 'giftcard_id' && $this->giftcard_id) {
      $giftcard= $this->factory('Giftcard')->find_one($value);
      if (!$giftcard) {
        throw new \Exception("Unable to find giftcard '$value'.");
      }

      $store_credit= $this->factory('Giftcard')->find_one($this->giftcard_id);
      if (!$store_credit) {
        throw new \Exception("Unable to find store credit '{$this->giftcard_id}'.");
      }

      $amount= $giftcard->balance();

      $giftcard->add_txn(-$amount);
      $store_credit->add_txn($amount);
    }
    elseif ($this->hasField($name)) {
      $this->$name= ($value !== '') ? $value : null;
    } else {
      throw new \Exception("No way to set '$name' on a person.");
    }
  }

  public function punches() {
    return $this->has_many('Timeclock');
  }

  public function punched() {
    $punch= $this->punches()->where_null('end')->find_one();
    return $punch ? $punch->start : null;
  }

  public function last_punch_out() {
    $punch= $this->punches()->where_not_null('end')->order_by_desc('id')->find_one();
    return $punch ? $punch->end : null;
  }

  public function punch() {
    $punch= $this->punches()->where_null('end')->find_one();
    if ($punch) {
      $punch->set_expr('end', 'NOW()');
      $punch->save();
    } else {
      $punch= $this->punches()->create();
      $punch->person_id= $this->id();
      $punch->set_expr('start', 'NOW()');
      $punch->save();
    }
    return $punch;
  }

  public function subscriptions($update= null) {
    if ($update) {
      $this->subscriptions= json_encode($update);
    }

    return json_decode($this->subscriptions);
  }

  public function syncToMailerlite() {
    // XXX There must be a better way to get this.
    $config= $GLOBALS['container']->get(\Scat\Service\Config::class);

    try {
      $client= new \GuzzleHttp\Client();

      $url= "https://api.mailerlite.com/api/v2" .
            "/subscribers/{$this->email}/groups";

      $res= $client->request('GET', $url, [
                              //'debug' => true,
                              'headers' => [
                                'X-MailerLite-ApiKey' =>
                                  $config->get("mailerlite.key")
                              ],
                            ]);

      $data= json_decode($res->getBody());

      $groups= array_map(function($group) {
        return [ 'id' => $group->id, 'name' => $group->name ];
      }, $data);

      $this->subscriptions($groups);
    } catch (\Exception $e) {
      // log and go on with our life
      error_log("Exception: " . $e->getMessage());
    }
  }

  public function store_credit() {
    return $this->giftcard_id ? $this->belongs_to('Giftcard')->find_one() : null;
  }

  public function xnotes() {
    return
      $this->has_many('Note', 'attach_id')
        ->where('parent_id', 0)
        ->where('kind', 'person');
  }

  public function can_check_stock() {
    // TODO hardcoded for now
    return in_array($this->id, [ 7, 3757, 30803, 31536, 44466 ]);
  }

}
