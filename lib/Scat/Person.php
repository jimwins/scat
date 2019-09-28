<?php
namespace Scat;

class Person extends \Model implements \JsonSerializable {
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
      } catch (Exception $e) {
        // Punt!
        return $this->phone;
      }
    }
  }

  public function loyalty() {
    return $this->has_many('Loyalty');
  }

  public function jsonSerialize() {
    return $this->asArray();
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
                   OR person.notes LIKE '%$term%')";
      }
    }

    $sql_criteria= join(' AND ', $criteria);

    $people= \Model::factory('Person')->select('person.*')
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
}
