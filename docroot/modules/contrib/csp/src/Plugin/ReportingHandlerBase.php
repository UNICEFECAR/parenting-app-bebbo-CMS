<?php

namespace Drupal\csp\Plugin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\csp\Csp;

/**
 * Base Reporting Handler implementation.
 */
class ReportingHandlerBase implements ReportingHandlerInterface {

  /**
   * The Plugin Configuration.
   *
   * @var array
   */
  protected $configuration;

  /**
   * The Plugin ID.
   *
   * @var string
   */
  protected $pluginId;

  /**
   * The Plugin Definition.
   *
   * @var array
   */
  protected $pluginDefinition;

  /**
   * Reporting Handler plugin constructor.
   *
   * @param array $configuration
   *   The Plugin configuration.
   * @param string $plugin_id
   *   The Plugin ID.
   * @param mixed $plugin_definition
   *   The Plugin Definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    $this->configuration = $configuration;
    $this->pluginId = $plugin_id;
    $this->pluginDefinition = $plugin_definition;
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(array $form) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function alterPolicy(Csp $policy) {

  }

}
