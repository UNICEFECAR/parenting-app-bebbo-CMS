<?php

namespace Drupal\bebbo_custom_general\Commands;

use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Drupal\bebbo_custom_general\Service\WarmerRunner;
use Drupal\Core\Lock\LockBackendInterface;
use Drush\Commands\DrushCommands;

/**
 * Warms the v1 (/api/*) caches of one site, or of every site in turn.
 *
 * Settings, the manual run button and the per-site run log live on
 * /admin/config/parent-buddy/api-warmer. These commands are what an Acquia
 * scheduled job calls.
 */
class WarmCommands extends DrushCommands implements SiteAliasManagerAwareInterface {

  use SiteAliasManagerAwareTrait;

  /**
   * Lock name guarding a full multi-site pass.
   */
  const LOCK_NAME = 'bebbo_warm_all';

  /**
   * Seconds the pass lock stays held.
   *
   * Refreshed at every site boundary, so a pass longer than this is still
   * covered, while a pass killed mid-run blocks the next one for an hour at
   * most instead of for the length of a whole pass.
   */
  const LOCK_EXPIRE = 3600;

  /**
   * The warmer runner.
   *
   * @var \Drupal\bebbo_custom_general\Service\WarmerRunner
   */
  protected WarmerRunner $runner;

  /**
   * The lock backend.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected LockBackendInterface $lock;

  /**
   * Constructs a WarmCommands object.
   *
   * @param \Drupal\bebbo_custom_general\Service\WarmerRunner $runner
   *   The warmer runner.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend.
   */
  public function __construct(WarmerRunner $runner, LockBackendInterface $lock) {
    parent::__construct();
    $this->runner = $runner;
    $this->lock = $lock;
  }

  /**
   * Warms every v1 API URL of the current site.
   *
   * @param array $options
   *   Command options (dry-run).
   *
   * @command bebbo:warm
   * @aliases bw
   * @option dry-run List the URLs that would be requested, then stop.
   * @usage drush bebbo:warm --dry-run
   *   Show this site's warm list without issuing any request.
   * @usage drush --uri=https://tr-dev.bebbo.app bebbo:warm
   *   Warm the Turkey site through its public hostname.
   */
  public function warm(array $options = ['dry-run' => FALSE]): int {
    $urls = $this->runner->buildUrls();
    if (!$urls) {
      $this->logger()->warning(dt('No country group with languages on this site; nothing to warm.'));
      return self::EXIT_SUCCESS;
    }

    if ($options['dry-run']) {
      foreach ($urls as $url) {
        $this->output()->writeln($url);
      }
      $this->logger()->notice(dt('@count URL(s) would be warmed.', ['@count' => count($urls)]));
      return self::EXIT_SUCCESS;
    }

    $started = microtime(TRUE);
    $result = $this->runner->warm($urls);
    $duration = microtime(TRUE) - $started;

    $this->runner->recordRun([
      'total' => count($urls),
      'warmed' => $result['warmed'],
      'failures' => $result['failures'],
      'duration' => $duration,
      'trigger' => 'drush',
    ]);

    $this->output()->writeln(sprintf(
      '%d/%d URL(s) warmed in %s.',
      $result['warmed'],
      count($urls),
      $this->formatDuration($duration)
    ));

    if ($result['failures']) {
      $this->output()->writeln(sprintf('%d failed:', count($result['failures'])));
      foreach ($result['failures'] as $url => $reason) {
        $this->output()->writeln(sprintf('  %s  %s', $url, $reason));
      }
      return self::EXIT_FAILURE;
    }

    return self::EXIT_SUCCESS;
  }

  /**
   * Warms every v1 API URL of every site, one site after another.
   *
   * Runs as a single Acquia scheduled job. Each site is warmed by its own
   * subprocess, because the language list has to come from that site's
   * database and one Drush process can only bootstrap one site.
   *
   * @param array $options
   *   Command options (sites, env).
   *
   * @command bebbo:warm-all
   * @aliases bwa
   * @option sites Comma-separated site directories to warm. Defaults to all.
   * @option env Environment whose hostnames to use: dev, test or prod.
   *   Defaults to AH_SITE_ENVIRONMENT.
   * @usage drush bebbo:warm-all
   *   Warm every site in sequence.
   * @usage drush bebbo:warm-all --sites=turkey,bangladesh --env=test
   *   Warm two sites against their staging hostnames.
   */
  public function warmAll(array $options = ['sites' => '', 'env' => '']): int {
    $hosts = $this->runner->getHostsByEnvironment();
    $environment = $options['env'] ?: (getenv('AH_SITE_ENVIRONMENT') ?: '');
    if (!isset($hosts[$environment])) {
      $this->logger()->error(dt('Unknown environment "@env". Configured: @list.', [
        '@env' => $environment,
        '@list' => implode(', ', array_keys($hosts)) ?: dt('none'),
      ]));
      return self::EXIT_FAILURE;
    }

    $known = array_keys($hosts[$environment]);
    $sites = $options['sites']
      ? array_map('trim', explode(',', $options['sites']))
      : $known;
    $unknown = array_diff($sites, $known);
    if ($unknown) {
      $this->logger()->error(dt('No hostname configured for site(s): @list.', [
        '@list' => implode(', ', $unknown),
      ]));
      return self::EXIT_FAILURE;
    }

    // A full pass takes far longer than the interval between scheduled runs
    // can be set to. Without this guard two passes would fight over the same
    // PHP-FPM workers and neither would finish.
    if (!$this->lock->acquire(self::LOCK_NAME, self::LOCK_EXPIRE)) {
      $this->logger()->warning(dt('Another warm-all pass is still running; skipping this one.'));
      return self::EXIT_SUCCESS;
    }

    $started = microtime(TRUE);
    $failed = [];
    try {
      foreach ($sites as $site) {
        $this->lock->acquire(self::LOCK_NAME, self::LOCK_EXPIRE);
        $uri = 'https://' . $hosts[$environment][$site];
        $this->output()->writeln(sprintf("\n=== %s (%s) ===", $site, $uri));

        $process = $this->processManager()->drush(
          $this->siteAliasManager()->getSelf(),
          'bebbo:warm',
          [],
          ['uri' => $uri]
        );
        // A cold site can take an hour; never let the subprocess be killed.
        $process->setTimeout(NULL);
        $process->run(function ($type, $buffer): void {
          $this->output()->write($buffer);
        });
        if (!$process->isSuccessful()) {
          $failed[] = $site;
        }
      }
    }
    finally {
      $this->lock->release(self::LOCK_NAME);
    }

    $this->output()->writeln(sprintf(
      "\n%d site(s) warmed in %s. %s",
      count($sites),
      $this->formatDuration(microtime(TRUE) - $started),
      $failed ? 'Failed: ' . implode(', ', $failed) : 'No site failed.'
    ));

    return $failed ? self::EXIT_FAILURE : self::EXIT_SUCCESS;
  }

  /**
   * Formats a duration for the summary lines.
   *
   * @param float $seconds
   *   The elapsed seconds.
   *
   * @return string
   *   A human readable duration.
   */
  protected function formatDuration(float $seconds): string {
    if ($seconds < 60) {
      return sprintf('%.1fs', $seconds);
    }
    return sprintf('%dm %ds', (int) ($seconds / 60), (int) $seconds % 60);
  }

}
