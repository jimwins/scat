<?
namespace Scat\Model;

use OE\Lukas\QueryTree\Item;
use OE\Lukas\QueryTree\Word;
use OE\Lukas\QueryTree\Text;
use OE\Lukas\QueryTree\ExplicitTerm;
use OE\Lukas\QueryTree\SubExpression;
use OE\Lukas\QueryTree\Negation;
use OE\Lukas\QueryTree\DisjunctiveExpressionList;
use OE\Lukas\QueryTree\ConjunctiveExpressionList;

class VendorItem extends \Scat\Model {
  public static
  function findByItemIdForVendor($item_id, $vendor_id, $active= 1) {
    return self::factory('VendorItem')
             ->where('vendor_id', $vendor_id)
             ->where('item_id', $item_id)
             ->where('active', $active)
             ->find_many();
  }

  public static
  function search($vendor_id, $q) {
    $scanner= new \OE\Lukas\Parser\QueryScanner();
    $parser= new \OE\Lukas\Parser\QueryParser($scanner);
    $parser->readString($q);
    $query= $parser->parse();

    if (!$query) {
      $feedback= $parser->getFeedback();
      foreach ($feedback as $msg) {
        error_log($msg);
      }
      throw new \Exception($msg);
    }

    $v= new VendorItemSearchVisitor();
    $query->accept($v);

    $items= self::factory('VendorItem')->select('vendor_item.*')
                                   ->where('vendor_item.vendor_id', $vendor_id)
                                   ->where_raw($v->where_clause())
                                   ->where_gte('vendor_item.active',
                                               $v->force_all ? 0 : 1)
                                   ->group_by('vendor_item.id')
                                   ->order_by_asc('vendor_item.vendor_sku');

    return $items;
  }

  public function item() {
    return $this->belongs_to('Item')->find_one();
  }

  public function vendor() {
    return $this->belongs_to('Person', 'vendor_id')->find_one();
  }

  public function checkVendorStock() {
    // XXX hardcoded stuff
    switch ($this->vendor_id) {
    case 7: // Mac
      return check_mac_stock($this->vendor_sku);
    case 3757: // SLS
      return check_sls_stock($this->vendor_sku);
    case 30803: // PA Dist
      return check_padist_stock($this->vendor_sku);
    case 44466: // Notions
      return check_notions_stock($this->vendor_sku);
    default:
      throw new \Exception("Don't know how to check stock for that vendor.");
      return [];
    }
  }

  public function set($name, $value= null) {
    if ($name == 'promo_price') {
      if (!strlen($value)) $value= null;
    }
    if ($name == 'dimensions') {
      return $this->setDimensions($value);
    }
    if ($name == 'promo_quantity' || $name == 'weight') {
      if ($value == '') $value= null;
    }

    return parent::set($name, $value);
  }

  public function dimensions() {
    if ($this->length && $this->width && $this->height)
      return $this->length . 'x' .
             $this->width . 'x' .
             $this->height;
  }

  public function setDimensions($dimensions) {
    if ($dimensions == '') {
      list($l, $w, $h)= [ null, null, null ];
    } else {
      list($l, $w, $h)= preg_split('/[^\d.]+/', trim($dimensions));
    }
    $this->length= $l;
    $this->width= $w;
    $this->height= $h;
    return $this;
  }

  public function getFields() {
    $fields= parent::getFields();
    $fields[]= 'dimensions';
    return $fields;
  }
}

class VendorItemSearchVisitor implements \OE\Lukas\Visitor\IQueryItemVisitor
{
  private $terms= [];
  private $current;
  public $force_all;

  public function __construct()
  {
  }

  public function where_clause() {
    return $this->current[0] ?: "1=1";
  }

  public function visitWord(Word $word)
  {
    // We just ignore AND, it's noise
    if ($word->getWord() != 'AND') {
      $term= addslashes($word->getWord());

      /* Special case: generated UPC codes */
      if (preg_match('/^(400400|000000)(\d+)\d$/i', $term, $dbt)) {
        $this->current[]= "(vendor_item.item_id = '{$dbt[1]}')";
        return;
      }

      $this->current[]= "(vendor_item.name LIKE '%$term%' OR vendor_item.code LIKE '%$term%' OR vendor_item.vendor_sku LIKE '%$term%' OR (vendor_item.barcode LIKE '%$term%'))";
    }
  }

  public function visitText(Text $text)
  {
    $term= addslashes($text->getText());
      $this->current[]= "(vendor_item.name LIKE '%$term%' OR vendor_item.code LIKE '%$term%' OR vendor_item.vendor_sku LIKE '%$term%' OR (vendor_item.barcode LIKE '%$term%'))";
  }

  public function visitExplicitTerm(ExplicitTerm $term)
  {
    $name= $term->getNominator()->getWord();
    $value= $term->getTerm()->getToken();
    /* TODO handle different terms here */
    switch ($name) {
    case 'active':
      $this->current[]= (bool)$value ? "(vendor_item.active)" : "(NOT vendor_item.active)";
      $this->force_all= true;
      break;
    case 'barcode':
      $this->current[]= "(vendor_item.barcode = '$value')";
      break;
    case 'code':
      $this->current[]= "(vendor_item.code LIKE '$value%')";
      break;
    case 'sku':
    case 'vendor_sku':
      $this->current[]= "(vendor_item.vendor_sku LIKE '$value%')";
      break;
    case 'item':
      $this->current[]= "(vendor_item.item_id = '$value')";
      break;
    case 'name':
      $this->current[]= "(vendor_item.name LIKE '%$value%')";
      break;
    case 'msrp':
      $this->current[]= "(vendor_item.retail_price = '$value')";
      break;
    case 'width':
      $this->current[]= $value ? "(vendor_item.width = '$value')" :
                                 "(NOT vendor_item.width OR vendor_item.width IS NULL)";
      break;
    case 'weight':
      $this->current[]= $value ? "(vendor_item.weight = '$value')" :
                                 "(NOT vendor_item.weight OR vendor_item.weight IS NULL)";
      break;
    case 're':
      $this->current[]= "(vendor_item.code RLIKE '$value')";
      break;
    case 'purchase_quantity':
    case 'prop65':
    case 'hazmat':
    case 'oversized':
    case 'special_order':
      $this->current[]= "(vendor_item.$name = '$value')";
      break;
    default:
      throw new \Exception("Don't know how to handle '$name'");
    }
  }

  public function visitSubExpression(SubExpression $sub)
  {
    $old= $this->current;
    $this->current= [];
    $sub->getSubExpression()->accept($this);
    // XXX can $this->current ever have more than one item?
    $old[]= '(' . join(' AND ', $this->current) . ')';
    $this->current= $old;
  }

  public function visitNegation(Negation $negation)
  {
    $old= $this->current;
    $this->current= [];

    $negation->getSubExpression()->accept($this);

    $old[]= 'NOT ' . join(' AND ', $this->current);
    $this->current= $old;
  }

  public function visitDisjunctiveExpressionList(DisjunctiveExpressionList $list)
  {
    $old= $this->current;
    $this->current= [];

    foreach($list->getExpressions() as $expression)
    {
        $expression->accept($this);
    }

    $old[]= join(' OR ', $this->current);
    $this->current= $old;
  }

  public function visitConjunctiveExpressionList(ConjunctiveExpressionList $list)
  {
    $old= $this->current;
    $this->current= [];

    foreach($list->getExpressions() as $expression)
    {
        $expression->accept($this);
    }

    $old[]= join(' AND ', $this->current);
    $this->current= $old;
  }

  public function dump() {
    echo $this->current[0];
  }
}

function check_mac_stock($code) {
  $url= 'https://www.macphersonart.com/cgi-bin/maclive/wam_tmpl/mac_cart.p';

  $client= new \GuzzleHttp\Client();
  $jar= \GuzzleHttp\Cookie\CookieJar::fromArray(['liveWAMSession' => MAC_KEY],
                                                parse_url($url, PHP_URL_HOST));

  $res= $client->request('GET', $url,
                         [
                         //'debug' => true,
                           'cookies' => $jar,
                           'query' => [
                             'site' => 'MAC',
                             'layout' => 'Responsive',
                             'nocache' => 66439,
                             'content' => 'JSON',
                             'page' => 'mac_cart',
                             'action' => 'getItemInfo',
                             'itemNumber' => $code,
                             'dropship' => 'false'
                           ]
                         ]);

  $body= $res->getBody();
  if ($GLOBALS['DEBUG']) {
    error_log($body);
  }

  $data= json_decode($body);
  $avail= [];

  foreach ($data->response->itemInfo as $item) {
    foreach ($item->itemQty as $qty) {
      $avail[$qty->warehouseName]= $qty->qty;
    }
  }

  return $avail;
}

function check_sls_stock($code) {
  $client= new \GuzzleHttp\Client();
  $jar= new \GuzzleHttp\Cookie\CookieJar();

  $url= 'https://www.slsarts.com/loginpage.asp';

  $res= $client->request('POST', $url,
                         [
                         //'debug' => true,
                           'cookies' => $jar,
                           'form_params' => [
                             'level1' => '',
                             'level2' => '',
                             'level3' => '',
                             'level4' => '',
                             'level5' => '',
                             'skuonly' => '',
                             'txtfind' => '',
                             'snum' => '',
                             'skey' => '',
                             'username' => SLS_USER,
                             'password' => SLS_PASSWORD,
                             'btnlogin' => 'Login'
                           ]
                         ]);

  $url= 'https://www.slsarts.com/viewcarttop.asp';
  $res= $client->request('POST', $url,
                         [
                         //'debug' => true,
                           'cookies' => $jar,
                           'form_params' => [
                             'defwh' => 2,
                             'imaacr' => '',
                             'forcebottom' => 'N',
                             'slssku' => $code,
                             'slsskuorig' => '',
                             'slsqty' => '',
                             'WH' => 2,
                             'oldwh' => '',
                             'goclear' => '',
                             'vmwvnm' => '',
                             'imadsc' => '',
                             'msrp' => '',
                             'minqty' => '',
                             'qoh1' => '',
                             'price' => '',
                             'disc' => 0,
                             'qoh2' => '',
                             'borderitem' => 'Order Item',
                             'verror' => '',
                             'vonblur' => 'Y',
                             'vedititem' => '',
                             'veditwh' => '',
                             'vdelitem' => '',
                             'vdropship' => 'E',
                             'deletelist' => '',
                             'thepos' => '',
                           ]
                         ]);

  $body= $res->getBody();

  $dom= new \DOMDocument();
  libxml_use_internal_errors(true);
  $dom->loadHTML($body);

  $xp= new \DOMXpath($dom);
  $no= $xp->query('//input[@name="qoh1"]')->item(0)->getAttribute('value');
  $vg= $xp->query('//input[@name="qoh2"]')->item(0)->getAttribute('value');

  return [ 'Vegas' => $vg, 'New Orleans' => $no ];
}

function check_padist_stock($code) {
  $lookup_url= 'https://www.pa-dist.com/ajax/search/suggestions';

  $client= new \GuzzleHttp\Client();
  $jar= \GuzzleHttp\Cookie\CookieJar::fromArray(
    [ 'WebLogin' => PADIST_KEY ],
    parse_url($lookup_url, PHP_URL_HOST)
  );

  $res= $client->request('GET', $lookup_url,
                         [
                           'cookies' => $jar,
                           'query' => [
                             'q' => $code
                           ]
                         ]);

  $body= $res->getBody();
  if ($GLOBALS['DEBUG']) {
    error_log($body);
  }

  // gross, but we're parsing html with a regex. ¯\_(ツ)_/¯
  if (preg_match("!/Item/(\d+)[^']+'><item-number><i>" . preg_quote($code) . '</i></item-number>!', $body, $m)) {
    $id= $m[1];
  } else {
    throw new \Exception("Unable to find ID for $code");
  }

  $details_url= 'https://www.pa-dist.com/ajax/item/detail/' . $id;

  $res= $client->request('GET', $details_url,
                         [
                           'cookies' => $jar,
                         ]);

  $body= $res->getBody();
  if ($GLOBALS['DEBUG']) {
    error_log($body);
  }

  $dom= new \DOMDocument();
  libxml_use_internal_errors(true);
  $dom->loadHTML($body);

  $xp= new \DOMXpath($dom);
  $stock= $xp->query('//span[@class="StockLevel"]')->item(0)->textContent;

  return [ 'stock' => $stock ];
}

function check_notions_stock($code) {
  $lookup_url= 'https://my.notionsmarketing.com/integration-web-services/services_rest/erpservice/qasInquiry';

  $client= new \GuzzleHttp\Client();

  $res= $client->request('POST', $lookup_url, [
    'headers' => [
      'Content-type' => 'application/json',
      'Accept' => 'application/json'
    ],
    'json' => [
      'skulist' => sprintf('%06d', $code)
    ]
  ]);

  $body= $res->getBody();

  $data= json_decode($body);

  return [ 'stock' => $data->qas ];
}
