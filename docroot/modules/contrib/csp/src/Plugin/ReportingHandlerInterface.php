<?php

namespace Drupal\csp\Plugin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\csp\Csp;

/**
 * CSP Reporting Handler interface.
 */
interface ReportingHandlerInterface {

  /**
   * Get the form fields for configuring this reporting handler.
   *
   * @param array $form
   *   The plugin parent form element.
   *
   * @return array
   *   A Form array.
   */
  public function getForm(array $form);

  /**
   * Validate the form fields of this report handler.
   *
   * @param array $form
   *   The form fields for this plugin.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The submitted form state.
   */
  public function validateForm(array &$form, FormStateInterface $form_state);

  /**
   * Alter the provided policy according to the plugin settings.
   *
   * @param \Drupal\csp\Csp $policy
   *   The policy to alter.
   */
  public function alterPolicy(Csp $policy);

}
