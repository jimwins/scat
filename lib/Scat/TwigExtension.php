<?php
namespace Scat;

class TwigExtension
  extends \Twig\Extension\AbstractExtension
  implements \Twig\Extension\GlobalsInterface
{
  private $config;

  public function __construct(\Scat\Service\Config $config= null) {
    $this->config= $config;
  }

  public function getGlobals() : array {
    $public= $this->config ? $this->config->get('ordure.url') : 'no-public';
    return [
      'DEBUG' => $GLOBALS['DEBUG'],
      'PUBLIC' => $public,
      'PUBLIC_CATALOG' => $public . '/art-supplies',
      'TIME' => $_SERVER['REQUEST_TIME_FLOAT'],
      'STATIC' => $this->config ? $this->config->get('ordure.static_url') : 'no',
    ];
  }

  public function getFunctions() : array {
    return [
      new \Twig\TwigFunction('current_release', [ $this, 'getCurrentRelease' ]),
      new \Twig\TwigFunction('notes', [ $this, 'getNotes' ]),
      new \Twig\TwigFunction('config', [ $this, 'getConfig' ]),
      new \Twig\TwigFunction('item', [ $this, 'getItem' ]),
      new \Twig\TwigFunction('ad', [ $this, 'showInternalAd' ], [
        'is_safe' => [ 'html' ],
        'needs_environment' => true,
      ]),
      new \Twig\TwigFunction('kit', [ $this, 'showKit' ], [
        'is_safe' => [ 'html' ],
        'needs_environment' => true,
      ]),
      new \Twig\TwigFunction('topDepartments', [ $this, 'topDepartments' ]),
    ];
  }

  public function getFilters() : array {
    return [
      new \Twig\TwigFilter('hexdec', 'hexdec'),
      new \Twig\TwigFilter('phone_number_format',
                           [ $this, 'phone_number_format' ])
    ];
  }

  public function getCurrentRelease() {
    $link= @readlink('/app/current');
    if ($link) {
      return basename($link);
    }
    return '';
  }

  public function getNotes() {
    return \Titi\Model::factory('Note')
             ->where('parent_id', 0)
             ->where('todo', 1)
             ->order_by_asc('id')
             ->find_many();
  }

  public function getConfig($name) {
    if (!$this->config) {
      throw new \Exception("Unable to access configuration.");
    }
    return $this->config->get($name);
  }

  /* XXX really should be using Catalog service or something */
  public function topDepartments() {
    return \Titi\Model::factory('Department')
             ->where('parent_id', 0)
             ->order_by_asc('name')
             ->find_many();
  }

  public function getItem($code) {
    return \Titi\Model::factory('Item')->where('code', $code)->find_one();
  }

  public function showKit($env, $code) {
    $kit= \Titi\Model::factory('Item')->where('code', $code)->find_one();
    if (!$kit) return;
    return $env->render('catalog/kit.twig', [ 'kit' => $kit ]);
  }

  public function showInternalAd($env, $tag, $start) {
    $ad= \Titi\Model::factory('InternalAd')
          ->where('tag', $tag)
          ->limit(1)->offset($start)
          ->order_by_expr('RAND(TO_DAYS(NOW()))')
          ->where('active', 1)
          ->find_one();
    if (!$ad) return;
    return $env->render('ad.twig', [ 'ad' => $ad ]);
  }

  public function phone_number_format($phone, $country_code= 'US') {
    try {
      $phoneUtil= \libphonenumber\PhoneNumberUtil::getInstance();
      $num= $phoneUtil->parse($phone, $country_code);
      return $phoneUtil->format($num,
                                \libphonenumber\PhoneNumberFormat::NATIONAL);
    } catch (\Exception $e) {
      // Punt!
      return $phone;
    }
  }
}
