<?php

namespace Drupal\pb_custom_form\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines PbMobile class.
 */
class PbMobile extends ControllerBase {

  /**
   * {@inheritDoc}
   */
  protected $configFactory;

  /**
   * {@inheritDoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * Display the pb-mobile.
   *
   * @return array
   *   Return pb-mobile array.
   */
  public function render($param1, $param2, $param3) {
    return [
      '#theme' => 'pb-mobile',
    ];
  }

  /**
   * Display the Kosovo-mobile.
   *
   * @return array
   *   Return kosovo-mobile array.
   */
  public function kosovorender($param1, $param2, $param3) {
    return [
      '#theme' => 'kosovo-mobile',
    ];
  }

  /**
   * Function to generate dynamic title.
   */
  public function getDynamicTitle() {
    $site_name = $this->configFactory->get('system.site')->get('name');
    return $site_name ?: $this->t('Bebbo');
  }

}
