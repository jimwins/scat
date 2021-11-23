<?php
namespace Scat\Service;

class Txn
{
  private $data;

  public function __construct(\Scat\Service\Data $data) {
    $this->data= $data;
  }

  public function create($type, $data= null) {
    $txn= $this->data->factory('Txn')->create();
    if ($data) {
      $txn->hydrate($data);
    }
    $txn->type= $type;

    // Generate number based on transaction type
    $number= $this->data->factory('Txn')
                  ->where('type', $txn->type)
                  ->max('number');
    $txn->number= $number + 1;

    // Could use real UUID() but this is shorter. Hardcoded '0' could be
    // replaced with a server-id to further avoid collisions
    $txn->uuid= sprintf("%08x%02x%s", time(), 0, bin2hex(random_bytes(8)));

    $txn->save();
    return $txn;
  }

  public function fetchById($id) {
    return $this->data->factory('Txn')->find_one($id);
  }

  public function find($type, $page= 0, $limit= 25, $q= null) {
    $res= $this->data->factory('Txn')
                ->select('txn.*')
                ->select_expr('COUNT(*) OVER()', 'records')
                ->order_by_desc('txn.created')
                ->where('type', $type)
                ->left_outer_join('person',
                                  array('person.id', '=', 'txn.person_id'))
                ->limit($limit)->offset($page * $limit);

    if ($q) {
      $parser= new \Scat\Search\Parser();

      $terms= $parser->parse($q);

      foreach ($terms as $term) {
        if ($term instanceof \Scat\Search\Comparison) {
          $name= $term->name;
          if ($name == 'online') $name= 'online_sale_id';
          if ($name == 'tax_captured' && $term->value == 0) {
            $res= $res->where_null('tax_captured');
          } else {
            $res= $res->where($name, $term->value);
          }
        } elseif ($term instanceof \Scat\Search\Term) {
          $res= $res->where_raw("(txn.number = ? OR txn.uuid = ? OR txn.id = ? OR txn.online_sale_id = ? OR CONCAT_WS(' ',person.name, person.email, person.phone, person.company) RLIKE ?)",
            [ $term->value, $term->value, $term->value, $term->value, $term->value ]);
        }
      }
    }

    return $res;
  }

  /* Till needs to get payments directly. */
  public function getPayments() {
    return $this->data->factory('Payment');
  }

  public function getShipments() {
    return $this->data->factory('Shipment');
  }

  public function fetchShipmentByTracker($tracker_id) {
    return $this->getShipments()
      ->where('tracker_id', $tracker_id)
      ->find_one();
  }

}
