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
      'STATIC' => ORDURE_STATIC,
      'MEDIA' => PUBLITIO_BASE,
    ];
  }

  public function getFunctions() {
    return [
      new \Twig\TwigFunction('notes', [ $this, 'getNotes' ]),
    ];
  }

  public function getFilters() {
    return [
      new \Twig_SimpleFilter('hexdec', 'hexdec'),
      new \Twig_SimpleFilter('phone_number_format',
                             [ $this, 'phone_number_format' ])
    ];
  }

  public function getNotes() {
    return \Model::factory('Note')
             ->where('parent_id', 0)
             ->where('todo', 1)
             ->order_by_asc('id')
             ->find_many();
  }

  public function phone_number_format($phone, $country_code= 'US') {
    try {
      $phoneUtil= \libphonenumber\PhoneNumberUtil::getInstance();
      $num= $phoneUtil->parse($phone, $country_code);
      return $phoneUtil->format($num,
                                \libphonenumber\PhoneNumberFormat::NATIONAL);
    } catch (Exception $e) {
      // Punt!
      return $phone;
    }
  }
}
