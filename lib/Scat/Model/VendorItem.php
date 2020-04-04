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

class VendorItem extends \Model {
  public static
  function findByItemIdForVendor($item_id, $vendor_id, $active= 1) {
    return \Model::factory('VendorItem')
             ->where('vendor', $vendor_id)
             ->where('item', $item_id)
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

    $items= \Model::factory('VendorItem')->select('vendor_item.*')
                                   ->where('vendor_item.vendor', $vendor_id)
                                   ->where_raw($v->where_clause())
                                   ->where_gte('vendor_item.active',
                                               $v->force_all ? 0 : 1)
                                   ->group_by('vendor_item.id')
                                   ->order_by_asc('vendor_item.vendor_sku');

    return $items;
  }

  public function real_item() {
    return $this->belongs_to('Item', 'item')->find_one();
  }

  public function vendor() {
    return $this->belongs_to('Person', 'vendor');
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
      if (preg_match('/^400400(\d+)\d$/i', $term, $dbt)) {
        $this->current[]= "(vendor_item.item = '{$dbt[1]}')";
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
      $this->current[]= "(vendor_item.item = '$value')";
      break;
/*
    case 'brand':
      if (is_numeric($value)) {
        $this->current[]= "(item.brand = '$value')";
      } else {
        // XXX search name and slug?
        $this->current[]= "(brand.slug = '$value')";
      }
      break;
    case 'product':
      if (is_numeric($value)) {
        $this->current[]= "(item.product_id = '$value')";
      } else {
        // XXX search name and slug?
        $this->current[]= "(product.slug = '$value')";
      }
      break;
*/
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
