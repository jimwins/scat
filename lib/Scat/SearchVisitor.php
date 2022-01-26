<?php
namespace Scat;

use OE\Lukas\QueryTree\Item;
use OE\Lukas\QueryTree\Word;
use OE\Lukas\QueryTree\Text;
use OE\Lukas\QueryTree\ExplicitTerm;
use OE\Lukas\QueryTree\SubExpression;
use OE\Lukas\QueryTree\Negation;
use OE\Lukas\QueryTree\DisjunctiveExpressionList;
use OE\Lukas\QueryTree\ConjunctiveExpressionList;

class SearchVisitor implements \OE\Lukas\Visitor\IQueryItemVisitor
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
        $this->current[]= "(item.id = '{$dbt[2]}')";
        return;
      }

      $this->current[]= "(item.name LIKE '%$term%' OR (brand.name IS NOT NULL AND brand.name LIKE '%$term%') OR item.code LIKE '%$term%' OR (barcode.code IS NOT NULL AND barcode.code LIKE '%$term%'))";
    }
  }

  public function visitText(Text $text)
  {
    $term= addslashes($text->getText());
      $this->current[]= "(item.name LIKE '%$term%' OR (brand.name IS NOT NULL AND brand.name LIKE '%$term%') OR item.code LIKE '%$term%' OR (barcode.code IS NOT NULL AND barcode.code LIKE '%$term%'))";
  }

  public function visitExplicitTerm(ExplicitTerm $term)
  {
    $name= $term->getNominator()->getWord();
    $value= $term->getTerm()->getToken();
    /* TODO handle different terms here */
    switch ($name) {
    case 'active':
      $this->current[]= (bool)$value ? "(item.active)" : "(NOT item.active)";
      $this->force_all= true;
      break;
    case 'barcode':
      $this->current[]= "(barcode.code IS NOT NULL AND barcode.code = '$value')";
      break;
    case 'code':
      $this->current[]= "(item.code LIKE '$value%')";
      break;
    case 'item':
      $this->current[]= "(item.id = '$value')";
      break;
    case 'brand':
      if (is_numeric($value)) {
        $this->current[]= "(item.brand_id = '$value')";
      } else {
        // XXX search name and slug?
        $this->current[]= "(brand.slug = '$value')";
      }
      break;
    case 'category':
    case 'department':
      if (is_numeric($value)) {
        $this->current[]= "(product.department_id = '$value')";
      } else {
        // XXX search name and slug?
        $this->current[]= "(department.slug = '$value')";
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
    case 'name':
      $this->current[]= "(item.name LIKE '%$value%')";
      break;
    case 'retail':
    case 'retail_price':
    case 'msrp':
      $this->current[]= "(item.retail_price = '$value')";
      break;
    case 'sale':
    case 'sale_price':
      $this->current[]= "(sale_price(item.retail_price, item.discount_type, item.discount) = '$value')";
      break;
    case 'discount':
      $this->current[]= "(item.discount = '$value')";
      break;
    case 'discount_type':
      $this->current[]= "(item.discount_type = '$value')";
      break;
    case 'min':
      $this->current[]= "(item.minimum_quantity = '$value')";
      break;
    case 'width':
    case 'height':
    case 'length':
    case 'weight':
      $this->current[]= $value ? "(item.$name = '$value')" :
                                 "(NOT item.$name OR item.$name IS NULL)";
      break;
    case 'stocked':
      $this->current[]= (bool)$value ? "(item.minimum_quantity)" :
                                       "(NOT item.minimum_quantity)";
      break;
    case 'is_kit':
      $this->current[]= (bool)$value ? "(item.is_kit)" :
                                       "(NOT item.is_kit)";
      break;
    case 're':
      $this->current[]= "(item.code RLIKE '$value')";
      break;
    case 'vendor':
      $vendor= (int)$value;
      $this->current[]= $vendor ? "(EXISTS (SELECT id
                                              FROM vendor_item
                                             WHERE item_id = item.id
                                               AND vendor_id = $vendor
                                               AND vendor_item.active))"
                                : "(NOT EXISTS (SELECT id
                                                  FROM vendor_item
                                                 WHERE item_id = item.id
                                                   AND vendor_item.active))";
      break;
    case 'tic':
    case 'variation':
    case 'purchase_quantity':
    case 'reviewed':
    case 'prop65':
    case 'hazmat':
    case 'oversized':
    case 'no_backorder':
      $this->current[]= "(item.$name = '$value')";
      break;
    case 'margin':
      $this->current[]= "((sale_price(item.retail_price, item.discount_type, item.discount) - (SELECT MIN(IF(promo_price, IF(promo_price < net_price,
                                           promo_price, net_price),
                           net_price))
               FROM vendor_item
              WHERE vendor_item.item_id = item.id AND vendor_item.active)) / sale_price(item.retail_price, item.discount_type, item.discount) < $value)";
      break;
    case 'image':
    case 'media':
      if ($value) {
        $this->current[]= "(SELECT COUNT(*) FROM item_to_image WHERE item_id = item.id) > 0";
      } else {
        $this->current[]= "(SELECT COUNT(*) FROM item_to_image WHERE item_id = item.id) = 0";
      }
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
