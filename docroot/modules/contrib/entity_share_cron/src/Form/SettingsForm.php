<?php

declare(strict_types = 1);

namespace Drupal\entity_share_cron\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_share_client\Service\RemoteManagerInterface;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Module settings form.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Default page limit.
   */
  public const DEFAULT_PAGE_LIMIT = 5;

  /**
   * CRON interval minimum of 1 minute.
   */
  public const CRON_INTERVAL_MINIMUM = 60;

  /**
   * Granularity in minute for the CRON interval.
   */
  public const CRON_INTERVAL_STEP = 60;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Remote manager service.
   *
   * @var \Drupal\entity_share_client\Service\RemoteManagerInterface
   */
  protected RemoteManagerInterface $remoteManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->remoteManager = $container->get('entity_share_client.remote_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'entity_share_cron_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return ['entity_share_cron.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('entity_share_cron.settings');
    $form['cron_interval'] = [
      '#type' => 'number',
      '#title' => $this->t('Execution interval'),
      '#description' => $this->t('Minimum interval between consecutive executions in seconds.'),
      '#min' => $this::CRON_INTERVAL_MINIMUM,
      '#step' => $this::CRON_INTERVAL_STEP,
      '#default_value' => $config->get('cron_interval'),
    ];
    $form['page_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Page limit'),
      '#description' => $this->t('Maximum number of pages to import for each channels on each cron run. 0 to import all the pages in one cron execution.'),
      '#min' => 0,
      '#default_value' => $config->get('page_limit'),
    ];
    $form['remotes'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Enabled remotes and channels'),
      '#description' => $this->t('Only selected remotes and channels will be synchronized on Cron executions. For each channel you may select which operations can be performed on synchronization.'),
      '#tree' => TRUE,
    ];
    /** @var array $remotes_config */
    $remotes_config = $config->get('remotes');
    /** @var \Drupal\entity_share_client\Entity\RemoteInterface[] $remotes */
    $remotes = $this->entityTypeManager->getStorage('remote')
      ->loadMultiple();
    foreach ($remotes as $remote_id => $remote) {
      $remote_config = $remotes_config[$remote_id] ?? [];
      // Adds a checkbox to enable/disable remote synchronization.
      $form['remotes'][$remote_id] = [
        '#type' => 'container',
      ];
      $remote_enabled_default = !empty($remote_config['enabled']);
      $form['remotes'][$remote_id]['enabled'] = [
        '#type' => 'checkbox',
        '#title' => $remote->label(),
        '#default_value' => $remote_enabled_default,
        '#ajax' => [
          'callback' => [$this, 'remoteCheckboxCallback'],
          'wrapper' => "channels-{$remote_id}",
        ],
      ];

      $form['remotes'][$remote_id]['channels'] = [
        '#type' => 'container',
        '#prefix' => "<div id='channels-{$remote_id}' class='channels-container'>",
        '#suffix' => '</div>',
      ];
      $remote_enabled = $form_state->getValue([
        'remotes',
        $remote_id,
        'enabled',
      ]);
      if (!isset($remote_enabled) && $remote_enabled_default || $remote_enabled) {
        try {
          $channels = $this->remoteManager->getChannelsInfos($remote);
        }
        catch (ClientException $exception) {
          $channels = [];
          \watchdog_exception('entity_share_cron', $exception);
          $this->messenger()->addError($this->t('Could not get channels from remote %remote.', [
            '%remote' => $remote->label(),
          ]));
        }
        $import_config_default_value = NULL;
        $import_config_options = $this->getImportConfigOptions();
        if (\count($import_config_options) == 1) {
          $import_config_default_value = \key($import_config_options);
        }

        foreach ($channels as $channel_id => $channel_info) {
          // Channel settings.
          $channel_config = $remote_config['channels'][$channel_id] ?? [];
          $form['remotes'][$remote_id]['channels'][$channel_id] = [
            '#type' => 'container',
          ];
          $form['remotes'][$remote_id]['channels'][$channel_id]['enabled'] = [
            '#type' => 'checkbox',
            '#title' => $channel_info['label'],
            '#default_value' => !empty($channel_config['enabled']),
          ];
          $form['remotes'][$remote_id]['channels'][$channel_id]['import_config'] = [
            '#type' => 'select',
            '#title' => $this->t('Import configuration'),
            '#options' => $import_config_options,
            '#default_value' => $channel_config['import_config'] ?? $import_config_default_value,
            '#states' => [
              'visible' => [
                ':input[name="remotes[' . $remote_id . '][channels][' . $channel_id . '][enabled]"]' => ['checked' => TRUE],
              ],
            ],
          ];

          $form['remotes'][$remote_id]['channels'][$channel_id]['operations'] = [
            '#type' => 'details',
            '#title' => $this->t('Operations'),
            '#open' => TRUE,
            '#attributes' => [
              'class' => ['channel-operations'],
            ],
            '#states' => [
              'visible' => [
                ':input[name="remotes[' . $remote_id . '][channels][' . $channel_id . '][enabled]"]' => ['checked' => TRUE],
              ],
            ],
          ];
          $form['remotes'][$remote_id]['channels'][$channel_id]['operations']['create'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Create'),
            '#default_value' => $channel_config['operations']['create'] ?? TRUE,
          ];
          $form['remotes'][$remote_id]['channels'][$channel_id]['operations']['update'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Update'),
            '#default_value' => $channel_config['operations']['update'] ?? TRUE,
          ];
        }
      }
    }
    $form['#attached']['library'][] = 'entity_share_cron/settings_form';
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Checks if every enabled remote has at least one channel enabled.
    $remotes = $form_state->getValue('remotes');
    if (\is_array($remotes)) {
      foreach ($remotes as $remote_id => $remote_config) {
        if (!empty($remote_config['enabled'])) {
          // Searches for an enabled channel.
          $channel_enabled = FALSE;
          $channels = $remote_config['channels'] ?? [];
          foreach ($channels as $channel_id => $channel_config) {
            if (!empty($channel_config['enabled'])) {
              $channel_enabled = TRUE;
              $element = &$form['remotes'][$remote_id]['channels'][$channel_id];

              // Checks if an import config had been selected.
              if (empty($channel_config['import_config'])) {
                $form_state->setError($element, $this->t('No import config selected for channel %channel of remote %remote.', [
                  '%channel' => $element['enabled']['#title'],
                  '%remote' => $form['remotes'][$remote_id]['enabled']['#title'],
                ]));
              }

              // Checks if at least one operation is enabled.
              if (!\array_filter($channel_config['operations'])) {
                $form_state->setError($element, $this->t('No operations enabled for channel %channel of remote %remote.', [
                  '%channel' => $element['enabled']['#title'],
                  '%remote' => $form['remotes'][$remote_id]['enabled']['#title'],
                ]));
              }
            }
          }

          // Shows an error if no enabled channel could be found.
          if (!$channel_enabled) {
            $element = &$form['remotes'][$remote_id];
            $form_state->setError($element, $this->t('No channels enabled for remote %remote.', [
              '%remote' => $element['enabled']['#title'],
            ]));
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('entity_share_cron.settings')
      ->set('cron_interval', $form_state->getValue('cron_interval'))
      ->set('page_limit', $form_state->getValue('page_limit'))
      ->set('remotes', $form_state->getValue('remotes'))
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Ajax callback for the remotes' checkboxes.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   A form element.
   */
  public function remoteCheckboxCallback(array &$form, FormStateInterface $form_state): array {
    /** @var array $triggering_element */
    $triggering_element = $form_state->getTriggeringElement();
    $remote_id = $triggering_element['#parents'][1];
    return $form['remotes'][$remote_id]['channels'];
  }

  /**
   * Helper function.
   *
   * @return array
   *   An array of import configs.
   */
  protected function getImportConfigOptions(): array {
    $import_configs = $this->entityTypeManager->getStorage('import_config')
      ->loadMultiple();
    $options = [];
    foreach ($import_configs as $import_config) {
      $options[$import_config->id()] = $import_config->label();
    }
    return $options;
  }

}
