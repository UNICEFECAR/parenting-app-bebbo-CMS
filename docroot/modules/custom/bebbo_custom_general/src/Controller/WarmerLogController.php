<?php

namespace Drupal\bebbo_custom_general\Controller;

use Drupal\bebbo_custom_general\Service\WarmerRunner;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists the API cache warmer runs recorded on this site.
 */
class WarmerLogController extends ControllerBase {

  /**
   * Rows per page.
   */
  const PAGE_SIZE = 25;

  /**
   * The warmer runner.
   *
   * @var \Drupal\bebbo_custom_general\Service\WarmerRunner
   */
  protected WarmerRunner $runner;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The pager manager.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected PagerManagerInterface $pagerManager;

  /**
   * Constructs a WarmerLogController.
   *
   * @param \Drupal\bebbo_custom_general\Service\WarmerRunner $runner
   *   The warmer runner.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   * @param \Drupal\Core\Pager\PagerManagerInterface $pager_manager
   *   The pager manager.
   */
  public function __construct(WarmerRunner $runner, DateFormatterInterface $date_formatter, PagerManagerInterface $pager_manager) {
    $this->runner = $runner;
    $this->dateFormatter = $date_formatter;
    $this->pagerManager = $pager_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('bebbo_custom_general.warmer_runner'),
      $container->get('date.formatter'),
      $container->get('pager.manager')
    );
  }

  /**
   * Builds the run log page.
   *
   * @return array
   *   A render array.
   */
  public function overview(): array {
    $total = $this->runner->countRuns();
    $pager = $this->pagerManager->createPager($total, self::PAGE_SIZE);
    $runs = $this->runner->getRuns(self::PAGE_SIZE, $pager->getCurrentPage() * self::PAGE_SIZE);

    $rows = [];
    foreach ($runs as $run) {
      $rows[] = [
        'data' => [
          $this->dateFormatter->format($run['started'], 'custom', 'D, d M Y - H:i:s'),
          $this->dateFormatter->format($run['finished'], 'custom', 'D, d M Y - H:i:s'),
          $this->formatDuration((float) $run['duration']),
          $this->statusLabel($run['status']),
          $run['warmed'] . ' / ' . $run['total'],
          $run['failed'],
          $run['trigger'],
          $run['host'],
          ['data' => $this->failureList($run['failures'])],
        ],
        // Colour the row the way Drupal colours its own status messages.
        'class' => [$run['status'] === WarmerRunner::STATUS_SUCCESS ? 'color-success' : 'color-warning'],
      ];
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Started'),
        $this->t('Finished'),
        $this->t('Duration'),
        $this->t('Status'),
        $this->t('Warmed'),
        $this->t('Failed'),
        $this->t('Started by'),
        $this->t('Host'),
        $this->t('Problems'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('This site has not been warmed yet.'),
      '#attributes' => ['class' => ['bebbo-warmer-log']],
    ];
    $build['pager'] = ['#type' => 'pager'];
    $build['#cache'] = ['max-age' => 0];

    return $build;
  }

  /**
   * Renders a run's failing URLs.
   *
   * @param array $failures
   *   Map of failing URL to reason.
   *
   * @return array
   *   A render array.
   */
  protected function failureList(array $failures): array {
    if (!$failures) {
      return ['#markup' => $this->t('None')];
    }

    $items = [];
    foreach ($failures as $url => $reason) {
      $items[] = $url . ' — ' . $reason;
    }

    return [
      '#type' => 'details',
      '#title' => $this->t('@count failing URL(s)', ['@count' => count($failures)]),
      'list' => [
        '#theme' => 'item_list',
        '#items' => $items,
      ],
    ];
  }

  /**
   * Returns the human readable label of a run status.
   *
   * @param string $status
   *   The stored status.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The label.
   */
  protected function statusLabel(string $status) {
    return match ($status) {
      WarmerRunner::STATUS_SUCCESS => $this->t('Completed, no problems'),
      WarmerRunner::STATUS_ISSUES => $this->t('Completed with problems'),
      WarmerRunner::STATUS_ABORTED => $this->t('Stopped before finishing'),
      default => $this->t('Unknown'),
    };
  }

  /**
   * Formats a duration in words.
   *
   * @param float $seconds
   *   The elapsed seconds.
   *
   * @return string
   *   A human readable duration.
   */
  protected function formatDuration(float $seconds): string {
    if ($seconds < 60) {
      return $this->t('@seconds sec', ['@seconds' => round($seconds, 1)]);
    }
    return $this->dateFormatter->formatInterval((int) round($seconds), 2);
  }

}
