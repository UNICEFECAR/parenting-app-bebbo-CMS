<?php

namespace Drupal\pb_content_analytics\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\pb_content_analytics\Service\AnalyticsSyncService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for displaying sync status and triggering a manual sync.
 */
class ContentAnalyticsSyncForm extends FormBase {

  /**
   * The analytics sync service.
   *
   * @var \Drupal\pb_content_analytics\Service\AnalyticsSyncService
   */
  protected AnalyticsSyncService $syncService;

  /**
   * Constructs a ContentAnalyticsSyncForm.
   *
   * @param \Drupal\pb_content_analytics\Service\AnalyticsSyncService $sync_service
   *   The analytics sync service.
   */
  public function __construct(AnalyticsSyncService $sync_service) {
    $this->syncService = $sync_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('pb_content_analytics.sync_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'pb_content_analytics_sync_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $enabled = $this->syncService->isSyncEnabled();

    $form['status'] = [
      '#type' => 'item',
      '#title' => $this->t('Feature status'),
      '#markup' => $enabled
        ? '<span style="color:green">' . $this->t('Enabled') . '</span>'
        : '<span style="color:red">' . $this->t('Disabled') . '</span>',
    ];

    $last = $this->syncService->getLastSyncLog();

    if ($last) {
      $form['last_sync_time'] = [
        '#type' => 'item',
        '#title' => $this->t('Last sync'),
        '#markup' => $this->t('@time (triggered by: @by)', [
          '@time' => $last['sync_time'],
          '@by' => $last['triggered_by'],
        ]),
      ];

      $form['last_sync_status'] = [
        '#type' => 'item',
        '#title' => $this->t('Last sync status'),
        '#markup' => $last['status'] === 'success'
          ? '<span style="color:green">' . $this->t('Success') . '</span>'
          : '<span style="color:red">' . $this->t('Failure') . '</span>',
      ];

      if ($last['status'] === 'failure' && !empty($last['error_message'])) {
        $form['last_sync_error'] = [
          '#type' => 'item',
          '#title' => $this->t('Error'),
          '#markup' => '<code>' . $this->t('@error', ['@error' => $last['error_message']]) . '</code>',
        ];
      }

      $counts_markup = $this->t('Processed: @processed, Updated: @updated, Skipped: @skipped', [
        '@processed' => $last['nodes_processed'],
        '@updated' => $last['nodes_updated'],
        '@skipped' => $last['nodes_skipped'],
      ]);

      $skipped_total = (int) $last['nodes_skipped'];
      if ($skipped_total > 0) {
        $counts_markup .= '<br/>&nbsp;&nbsp;— ' . $this->t('Unknown content type: @ut', [
          '@ut' => $last['skipped_unknown_type'] ?? 0,
        ]);
        $counts_markup .= '<br/>&nbsp;&nbsp;— ' . $this->t('Node not found / bundle mismatch: @nf', [
          '@nf' => $last['skipped_not_found'] ?? 0,
        ]);
        $counts_markup .= '<br/>&nbsp;&nbsp;— ' . $this->t('Already up to date: @ud', [
          '@ud' => $last['skipped_up_to_date'] ?? 0,
        ]);
      }

      $form['last_sync_counts'] = [
        '#type' => 'item',
        '#title' => $this->t('Sync summary'),
        '#markup' => $counts_markup,
      ];
    }
    else {
      $form['no_sync'] = [
        '#type' => 'item',
        '#markup' => $this->t('No sync has been run yet.'),
      ];
    }

    if ($enabled) {
      $form['actions'] = ['#type' => 'actions'];
      $form['actions']['sync_now'] = [
        '#type' => 'submit',
        '#value' => $this->t('Sync Now'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_state->setRedirectUrl(
      Url::fromRoute('pb_content_analytics.sync_now')
    );
  }

}
