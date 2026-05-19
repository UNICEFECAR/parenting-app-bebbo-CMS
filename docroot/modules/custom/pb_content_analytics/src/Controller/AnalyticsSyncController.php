<?php

namespace Drupal\pb_content_analytics\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\pb_content_analytics\Service\AnalyticsSyncService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for triggering a manual content analytics sync via Batch API.
 */
class AnalyticsSyncController extends ControllerBase {

  /**
   * The analytics sync service.
   *
   * @var \Drupal\pb_content_analytics\Service\AnalyticsSyncService
   */
  protected AnalyticsSyncService $syncService;

  /**
   * Constructs an AnalyticsSyncController.
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
   * Fetches analytics data and runs a Batch API sync.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect to sync form after batch is set up.
   */
  public function syncNow(): RedirectResponse {
    if (!$this->syncService->isSyncEnabled()) {
      $this->messenger()->addWarning($this->t('Content Analytics is disabled. Enable it in settings before syncing.'));
      return $this->redirect('pb_content_analytics.sync');
    }

    try {
      $data = $this->syncService->fetchFromBigQuery();
    }
    catch (\RuntimeException $e) {
      $this->syncService->logSyncResult(
        ['processed' => 0, 'updated' => 0, 'skipped' => 0],
        'manual',
        $e->getMessage()
      );
      $this->messenger()->addError($this->t('Sync failed: @message', ['@message' => $e->getMessage()]));
      return $this->redirect('pb_content_analytics.sync');
    }

    $chunks = array_chunk($data, 100, TRUE);
    $total = count($data);

    $operations = [];
    foreach ($chunks as $chunk) {
      $operations[] = [
        [static::class, 'batchProcessChunk'],
        [$chunk, $total],
      ];
    }

    $batch = [
      'title' => $this->t('Syncing content analytics…'),
      'operations' => $operations,
      'finished' => [static::class, 'batchFinished'],
      'init_message' => $this->t('Starting sync of @total nodes…', ['@total' => $total]),
      'progress_message' => $this->t('Processing batch @current of @total…'),
      'error_message' => $this->t('An error occurred during the sync.'),
    ];

    batch_set($batch);

    return batch_process($this->redirect('pb_content_analytics.sync')->getTargetUrl());
  }

  /**
   * Batch operation: process one chunk of node analytics.
   *
   * @param array<string, array<string, mixed>> $chunk
   *   A slice of the BigQuery response.
   * @param int $total
   *   Total number of nodes in the full sync run.
   * @param array<string, mixed> $context
   *   Batch API context array passed by reference.
   */
  public static function batchProcessChunk(array $chunk, int $total, array &$context): void {
    if (empty($context['results'])) {
      $context['results'] = [
        'processed' => 0,
        'updated' => 0,
        'skipped' => 0,
        'skipped_unknown_type' => 0,
        'skipped_not_found' => 0,
        'skipped_up_to_date' => 0,
      ];
    }

    $sync_service = \Drupal::service('pb_content_analytics.sync_service');
    $stats = $sync_service->processBatch($chunk);

    $context['results']['processed'] += $stats['processed'];
    $context['results']['updated'] += $stats['updated'];
    $context['results']['skipped'] += $stats['skipped'];
    $context['results']['skipped_unknown_type'] += $stats['skipped_unknown_type'];
    $context['results']['skipped_not_found'] += $stats['skipped_not_found'];
    $context['results']['skipped_up_to_date'] += $stats['skipped_up_to_date'];

    $context['message'] = t('Processing nodes… @processed / @total — updated: @updated, skipped: @skipped', [
      '@processed' => $context['results']['processed'],
      '@total' => $total,
      '@updated' => $context['results']['updated'],
      '@skipped' => $context['results']['skipped'],
    ]);
  }

  /**
   * Batch finished callback: logs result and displays status message.
   *
   * @param bool $success
   *   Whether the batch completed without PHP errors.
   * @param array<string, int> $results
   *   Aggregated processed/updated/skipped counts.
   * @param array<int, mixed> $operations
   *   Any unprocessed operations (non-empty on failure).
   */
  public static function batchFinished(bool $success, array $results, array $operations): void {
    $sync_service = \Drupal::service('pb_content_analytics.sync_service');

    if ($success) {
      $sync_service->logSyncResult($results, 'manual');
      \Drupal::messenger()->addStatus(t('Sync complete. Processed: @p, Updated: @u, Skipped: @s (unknown type: @ut, not found: @nf, up to date: @ud).', [
        '@p' => $results['processed'] ?? 0,
        '@u' => $results['updated'] ?? 0,
        '@s' => $results['skipped'] ?? 0,
        '@ut' => $results['skipped_unknown_type'] ?? 0,
        '@nf' => $results['skipped_not_found'] ?? 0,
        '@ud' => $results['skipped_up_to_date'] ?? 0,
      ]));
    }
    else {
      $error = t('Batch sync did not complete successfully.');
      $sync_service->logSyncResult(
        $results + ['processed' => 0, 'updated' => 0, 'skipped' => 0],
        'manual',
        (string) $error
      );
      \Drupal::messenger()->addError($error);
    }
  }

}
