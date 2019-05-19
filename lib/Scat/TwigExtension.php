<?php
namespace Scat;

class TwigExtension
  extends \Twig\Extension\AbstractExtension
  implements \Twig\Extension\GlobalsInterface
{
  public function getGlobals() {
    return [
      'DEBUG' => $GLOBALS['DEBUG'],
      'PUBLIC' => ORDURE,
      'PUBLIC_CATALOG' => ORDURE . '/art-supplies',
      'STATIC' => ORDURE_STATIC
    ];
  }

  public function getFunctions() {
    return [
      new \Twig\TwigFunction('notes', [ $this, 'getNotes' ]),
    ];
  }

  public function getFilters() {
    return [
      new \Twig_SimpleFilter('hexdec', 'hexdec')
    ];
  }

  public function getNotes() {
    return \Model::factory('Note')
             ->where('parent_id', 0)
             ->where('todo', 1)
             ->order_by_asc('id')
             ->find_many();
  }
}
