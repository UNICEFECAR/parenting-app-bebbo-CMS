<?php

namespace Drupal\bebbo_custom_general\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders the mobile app-share landing pages.
 */
class PbMobile extends ControllerBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a PbMobile controller.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * Displays the pb-mobile share page.
   *
   * @return array
   *   The pb-mobile render array.
   */
  public function render($param1, $param2, $param3) {
    return [
      '#theme' => 'pb-mobile',
    ];
  }

  /**
   * Displays the Kosovo mobile share page.
   *
   * @return array
   *   The kosovo-mobile render array.
   */
  public function kosovorender($param1, $param2, $param3) {
    return [
      '#theme' => 'kosovo-mobile',
    ];
  }

  /**
   * Generates the dynamic page title.
   */
  public function getDynamicTitle() {
    $site_name = $this->configFactory->get('system.site')->get('name');
    return $site_name ?: $this->t('Bebbo');
  }

}
