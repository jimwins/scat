<?php
namespace Scat;

class TwigExtension
  extends \Twig\Extension\AbstractExtension
  implements \Twig\Extension\GlobalsInterface
{
  private $config;

  public function __construct(\Scat\Service\Config $config) {
    $this->config= $config;
  }

  public function getGlobals() : array {
    return [
      'DEBUG' => $GLOBALS['DEBUG'],
      'PUBLIC' => ORDURE,
      'PUBLIC_CATALOG' => ORDURE . '/art-supplies',
      'STATIC' => ORDURE_STATIC,
    ];
  }

  public function getFunctions() : array {
    return [
      new \Twig\TwigFunction('notes', [ $this, 'getNotes' ]),
      new \Twig\TwigFunction('config', [ $this, 'getConfig' ]),
    ];
  }

  public function getFilters() : array {
    return [
      new \Twig\TwigFilter('hexdec', 'hexdec'),
      new \Twig\TwigFilter('phone_number_format',
                           [ $this, 'phone_number_format' ])
    ];
  }

  public function getNotes() {
    return \Titi\Model::factory('Note')
             ->where('parent_id', 0)
             ->where('todo', 1)
             ->order_by_asc('id')
             ->find_many();
  }

  public function getConfig($name) {
    return $this->config->get($name);
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
