<?php

namespace Drupal\pb_content_analytics\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for Content Analytics settings.
 */
class ContentAnalyticsSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['pb_content_analytics.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'pb_content_analytics_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('pb_content_analytics.settings');

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Content Analytics'),
      '#description' => $this->t('When enabled, analytics data will be synced from the BigQuery endpoint.'),
      '#default_value' => $config->get('enabled'),
    ];

    $form['api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('BigQuery API URL'),
      '#description' => $this->t('Full endpoint URL for the BigQuery analytics API.'),
      '#default_value' => $config->get('api_url'),
      '#maxlength' => 512,
      '#states' => [
        'visible' => [':input[name="enabled"]' => ['checked' => TRUE]],
      ],
    ];

    $form['api_key'] = [
      '#type' => 'password',
      '#title' => $this->t('X-API-Key'),
      '#description' => $this->t('API key sent as the X-API-Key request header. Leave blank to keep the existing value.'),
      '#default_value' => '',
      '#states' => [
        'visible' => [':input[name="enabled"]' => ['checked' => TRUE]],
      ],
    ];

    $form['auto_sync_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable auto-sync'),
      '#description' => $this->t('Allow cron to trigger analytics sync automatically.'),
      '#default_value' => $config->get('auto_sync_enabled'),
      '#states' => [
        'visible' => [':input[name="enabled"]' => ['checked' => TRUE]],
      ],
    ];

    $form['sync_frequency'] = [
      '#type' => 'radios',
      '#title' => $this->t('Sync frequency'),
      '#options' => [
        'daily' => $this->t('Daily'),
        'weekly' => $this->t('Weekly'),
      ],
      '#default_value' => $config->get('sync_frequency') ?: 'weekly',
      '#states' => [
        'visible' => [
          ':input[name="enabled"]' => ['checked' => TRUE],
          ':input[name="auto_sync_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('pb_content_analytics.settings');
    $config
      ->set('enabled', (bool) $form_state->getValue('enabled'))
      ->set('api_url', $form_state->getValue('api_url'))
      ->set('auto_sync_enabled', (bool) $form_state->getValue('auto_sync_enabled'))
      ->set('sync_frequency', $form_state->getValue('sync_frequency'));

    // Only overwrite the API key if a new value was provided.
    $api_key = $form_state->getValue('api_key');
    if (!empty($api_key)) {
      $config->set('api_key', $api_key);
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
