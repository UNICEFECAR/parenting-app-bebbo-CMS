<?php

namespace Drupal\csp\Plugin\CspReportingHandler;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\csp\Csp;
use Drupal\csp\Plugin\ReportingHandlerBase;

/**
 * CSP Reporting Plugin for a URI endpoint.
 *
 * @CspReportingHandler(
 *   id = "uri",
 *   label = "URI",
 *   description = @Translation("Reports will be sent to a URI."),
 * )
 */
class Uri extends ReportingHandlerBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getForm(array $form) {

    $form['uri'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URI'),
      '#description' => $this->t('The URI to send reports to.'),
      '#default_value' => $this->configuration['uri'] ?? '',
      '#states' => [
        'required' => [
          ':input[name="' . $this->configuration['type'] . '[enable]"]' => ['checked' => TRUE],
          ':input[name="' . $this->configuration['type'] . '[reporting][handler]"]' => ['value' => $this->pluginId],
        ],
      ],
    ];

    unset($form['#description']);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $uri = $form_state->getValue($form['uri']['#parents']);
    if (!(UrlHelper::isValid($uri, TRUE) && preg_match('/^https?:/', $uri))) {
      $form_state->setError($form['uri'], 'Must be a valid http or https URL.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function alterPolicy(Csp $policy) {
    $policy->setDirective('report-uri', $this->configuration['uri']);
  }

}
