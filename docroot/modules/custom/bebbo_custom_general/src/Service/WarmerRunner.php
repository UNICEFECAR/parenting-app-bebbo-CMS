<?php

namespace Drupal\bebbo_custom_general\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\language_visibility_control\LanguageVisibilityService;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Builds and issues the v1 API warm-up requests for one site.
 *
 * The URL list is derived, never stored: every configured v1 path that takes a
 * langcode, crossed with the languages this site actually serves in the app
 * (field_language_visibility_in_app on its country groups). Adding a language
 * in the CMS therefore warms it on the next run with no configuration change.
 */
class WarmerRunner {

  /**
   * Name of the settings object.
   */
  const CONFIG_NAME = 'bebbo_custom_general.warmer';

  /**
   * Table holding this site's run log.
   */
  const LOG_TABLE = 'bebbo_warmer_log';

  /**
   * How many runs to keep in the log.
   */
  const RUNS_KEPT = 100;

  /**
   * How many failing URLs to keep per logged run.
   */
  const FAILURES_KEPT = 100;

  /**
   * Envelope statuses that count as a warmed response.
   *
   * The v1 API answers HTTP 200 even when it refuses the request; the real
   * status is in the JSON envelope. 204 is its "No Records Found", which is a
   * legitimate warm answer for an endpoint with no content.
   */
  const GOOD_STATUSES = [200, 204];

  /**
   * Run status: every URL answered warm.
   */
  const STATUS_SUCCESS = 'success';

  /**
   * Run status: the run finished but some URLs did not answer warm.
   */
  const STATUS_ISSUES = 'issues';

  /**
   * Run status: the run stopped before it reached the end of its list.
   */
  const STATUS_ABORTED = 'aborted';

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The language visibility service.
   *
   * @var \Drupal\language_visibility_control\LanguageVisibilityService
   */
  protected LanguageVisibilityService $languageVisibility;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * The site path, for instance 'sites/turkey'.
   *
   * @var string
   */
  protected string $sitePath;

  /**
   * Constructs a WarmerRunner.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\language_visibility_control\LanguageVisibilityService $language_visibility
   *   The language visibility service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param string $site_path
   *   The site path, which names the site being warmed.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ClientInterface $http_client, EntityTypeManagerInterface $entity_type_manager, LanguageVisibilityService $language_visibility, RequestStack $request_stack, Connection $database, TimeInterface $time, LoggerChannelFactoryInterface $logger_factory, string $site_path) {
    $this->configFactory = $config_factory;
    $this->httpClient = $http_client;
    $this->entityTypeManager = $entity_type_manager;
    $this->languageVisibility = $language_visibility;
    $this->requestStack = $request_stack;
    $this->database = $database;
    $this->time = $time;
    $this->loggerFactory = $logger_factory;
    $this->sitePath = $site_path;
  }

  /**
   * Returns the number of requests to keep in flight at once.
   *
   * @return int
   *   The configured concurrency, never below one.
   */
  public function getConcurrency(): int {
    return max(1, (int) $this->settings()->get('concurrency'));
  }

  /**
   * Returns the seconds to wait for a single response.
   *
   * @return int
   *   The configured request timeout.
   */
  public function getRequestTimeout(): int {
    return max(1, (int) $this->settings()->get('request_timeout'));
  }

  /**
   * Returns the per-site settings.
   *
   * @return array
   *   Hostnames and languages keyed by site directory.
   */
  public function getSites(): array {
    return $this->settings()->get('sites') ?: [];
  }

  /**
   * Returns the directory name of the site being warmed.
   *
   * @return string
   *   The directory under docroot/sites, for instance 'turkey'.
   */
  public function getSiteKey(): string {
    return basename($this->sitePath);
  }

  /**
   * Returns the public hostname of every site, keyed by environment.
   *
   * @return array
   *   Hostnames keyed by environment, then by site directory.
   */
  public function getHostsByEnvironment(): array {
    $hosts = [];
    foreach ($this->getSites() as $site => $settings) {
      foreach ($settings['hosts'] ?? [] as $environment => $host) {
        $hosts[$environment][$site] = $host;
      }
    }
    return $hosts;
  }

  /**
   * Returns the languages to warm on a site.
   *
   * Configured languages win. A site with none configured falls back to the
   * languages its country groups serve in the app, so a site added later warms
   * something sensible before anyone touches the settings.
   *
   * @param string|null $site
   *   The site directory, or NULL for the site being warmed.
   *
   * @return string[]
   *   Langcodes, in the configured order.
   */
  public function getLanguages(?string $site = NULL): array {
    $site = $site ?? $this->getSiteKey();
    $configured = $this->getSites()[$site]['languages'] ?? [];
    if ($configured) {
      return array_values(array_filter($configured));
    }
    return $this->derivedLanguages();
  }

  /**
   * Returns the languages this site's country groups serve in the app.
   *
   * @return string[]
   *   Langcodes, sorted.
   */
  public function derivedLanguages(): array {
    $languages = [];
    foreach ($this->countryGroups() as $group) {
      $visible = $this->languageVisibility->getVisibleLanguages($group)
        ?: $this->languageVisibility->getAllGroupLanguages($group);
      $languages = array_merge($languages, $visible);
    }
    $languages = array_unique(array_filter($languages));
    sort($languages);

    return array_values($languages);
  }

  /**
   * Builds this site's full warm list.
   *
   * @return string[]
   *   Absolute URLs, in a deterministic order. They are absolute because the
   *   host comes from the current request, which on the command line is the
   *   --uri handed to the process and is the only thing saying which site is
   *   being warmed.
   */
  public function buildUrls(): array {
    $languages = $this->getLanguages();
    // Always https: a drush --uri without a scheme defaults to http, and the
    // CDN caches http and https under different keys — warming http would
    // leave the https entries the app requests cold.
    $base = 'https://' . $this->requestStack->getCurrentRequest()->getHttpHost();
    $paths = $this->settings()->get('paths') ?: [];

    $urls = [];
    foreach ($languages as $language) {
      foreach ($paths as $path) {
        $urls[] = $base . str_replace('{lang}', $language, $path);
      }
    }
    // check-update takes a country group ID, not a langcode.
    foreach (array_keys($this->countryGroups()) as $group_id) {
      $urls[] = $base . '/api/check-update/' . $group_id;
    }

    return $urls;
  }

  /**
   * Loads this site's country groups.
   *
   * @return \Drupal\group\Entity\GroupInterface[]
   *   The country groups, keyed by ID.
   */
  protected function countryGroups(): array {
    return $this->entityTypeManager->getStorage('group')
      ->loadByProperties(['type' => 'country']);
  }

  /**
   * Requests a set of URLs and reports which of them came back warm.
   *
   * @param string[] $urls
   *   Absolute URLs to request.
   *
   * @return array
   *   An array with a 'warmed' count and a 'failures' map of URL to reason.
   */
  public function warm(array $urls): array {
    if (!$urls) {
      return ['warmed' => 0, 'failures' => []];
    }

    $this->purgeEdge($urls);

    $warmed = 0;
    $failures = [];
    $timeout = $this->getRequestTimeout();

    $requests = function () use ($urls): \Generator {
      foreach ($urls as $url) {
        yield new Request('GET', $url);
      }
    };

    $pool = new Pool($this->httpClient, $requests(), [
      'concurrency' => $this->getConcurrency(),
      'options' => [
        // A cold listing renders far past the 30s http_client default, and
        // aborting early leaves the cache cold for the next visitor too.
        'timeout' => $timeout,
        'connect_timeout' => 10,
        'headers' => ['X-Bebbo-Warmer' => '1'],
        'http_errors' => FALSE,
      ],
      'fulfilled' => function (ResponseInterface $response, int $index) use ($urls, &$warmed, &$failures): void {
        $status = $this->envelopeStatus($response);
        if (in_array($status, self::GOOD_STATUSES, TRUE)) {
          $warmed++;
          return;
        }
        $failures[$urls[$index]] = 'status ' . $status;
      },
      'rejected' => function (\Throwable $reason, int $index) use ($urls, &$failures): void {
        $failures[$urls[$index]] = $reason->getMessage();
      },
    ]);
    $pool->promise()->wait();

    return ['warmed' => $warmed, 'failures' => $failures];
  }

  /**
   * Purges the URLs from the Cloudflare edge before they are re-fetched.
   *
   * The warm requests that follow travel through Cloudflare, so purging
   * first means the fresh origin response is what the edge stores for the
   * next TTL window. Without credentials (local environments) the purge is
   * skipped and the warm-up still refreshes the origin caches.
   *
   * @param string[] $urls
   *   Absolute URLs about to be warmed.
   */
  protected function purgeEdge(array $urls): void {
    $cloudflare = Settings::get('bebbo_warmer_cloudflare', []);
    $token = $cloudflare['api_token'] ?? '';
    $zone = $cloudflare['zone_id'] ?? '';
    $logger = $this->loggerFactory->get('bebbo_warmer');
    if (!$token || !$zone) {
      $logger->notice('Cloudflare purge skipped: bebbo_warmer_cloudflare settings are not configured.');
      return;
    }

    // The purge_cache endpoint accepts at most 30 URLs per request.
    foreach (array_chunk($urls, 30) as $chunk) {
      try {
        $response = $this->httpClient->request('POST', 'https://api.cloudflare.com/client/v4/zones/' . $zone . '/purge_cache', [
          'headers' => ['Authorization' => 'Bearer ' . $token],
          'json' => ['files' => $chunk],
          'timeout' => 30,
        ]);
        $body = json_decode((string) $response->getBody(), TRUE);
        if (empty($body['success'])) {
          $logger->warning('Cloudflare purge refused: @body', ['@body' => (string) $response->getBody()]);
        }
      }
      catch (\Throwable $e) {
        $logger->warning('Cloudflare purge failed: @message', ['@message' => $e->getMessage()]);
      }
    }
  }

  /**
   * Writes one finished run into this site's log.
   *
   * @param array $run
   *   The run summary: total, warmed, failures, duration, trigger and, when
   *   the run did not reach its end, a status of 'aborted'.
   *
   * @return int
   *   The ID of the logged row.
   */
  public function recordRun(array $run): int {
    $run += [
      'total' => 0,
      'warmed' => 0,
      'failures' => [],
      'duration' => 0,
      'trigger' => 'unknown',
      'status' => '',
    ];
    $failed = count($run['failures']);
    $finished = $this->time->getRequestTime();
    $status = $run['status'] ?: ($failed ? self::STATUS_ISSUES : self::STATUS_SUCCESS);

    $id = (int) $this->database->insert(self::LOG_TABLE)
      ->fields([
        'started' => $finished - (int) round($run['duration']),
        'finished' => $finished,
        'duration' => round($run['duration'], 2),
        'total' => $run['total'],
        'warmed' => $run['warmed'],
        'failed' => $failed,
        'status' => $status,
        'trigger' => $run['trigger'],
        'host' => $this->currentHost(),
        'failures' => json_encode(array_slice($run['failures'], 0, self::FAILURES_KEPT, TRUE)),
      ])
      ->execute();

    $this->pruneRuns();

    $message = '@warmed/@total URL(s) warmed in @duration s (@trigger). @failed failed.';
    $context = [
      '@warmed' => $run['warmed'],
      '@total' => $run['total'],
      '@duration' => round($run['duration'], 1),
      '@trigger' => $run['trigger'],
      '@failed' => $failed,
    ];
    $logger = $this->loggerFactory->get('bebbo_warmer');
    $status === self::STATUS_SUCCESS ? $logger->info($message, $context) : $logger->warning($message, $context);

    return $id;
  }

  /**
   * Returns this site's logged runs, newest first.
   *
   * @param int $limit
   *   How many rows to return.
   * @param int $offset
   *   How many rows to skip.
   *
   * @return array
   *   The logged runs, each with its failures decoded.
   */
  public function getRuns(int $limit = 50, int $offset = 0): array {
    $rows = $this->database->select(self::LOG_TABLE, 'l')
      ->fields('l')
      ->orderBy('id', 'DESC')
      ->range($offset, $limit)
      ->execute()
      ->fetchAllAssoc('id', \PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
      $row['failures'] = json_decode($row['failures'] ?? '', TRUE) ?: [];
    }

    return $rows;
  }

  /**
   * Counts this site's logged runs.
   *
   * @return int
   *   The number of rows in the log.
   */
  public function countRuns(): int {
    return (int) $this->database->select(self::LOG_TABLE, 'l')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * Clears this site's run log.
   */
  public function clearRuns(): void {
    $this->database->truncate(self::LOG_TABLE)->execute();
  }

  /**
   * Drops the oldest rows once the log grows past what is kept.
   */
  protected function pruneRuns(): void {
    $oldest = $this->database->select(self::LOG_TABLE, 'l')
      ->fields('l', ['id'])
      ->orderBy('id', 'DESC')
      ->range(self::RUNS_KEPT, 1)
      ->execute()
      ->fetchField();

    if ($oldest) {
      $this->database->delete(self::LOG_TABLE)
        ->condition('id', $oldest, '<=')
        ->execute();
    }
  }

  /**
   * Returns the host the current process is warming.
   *
   * @return string
   *   The hostname, empty when there is no request.
   */
  protected function currentHost(): string {
    $request = $this->requestStack->getCurrentRequest();
    return $request ? $request->getHost() : '';
  }

  /**
   * Reads the effective status of a v1 response.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The response.
   *
   * @return int
   *   The envelope status where the body carries one, the HTTP status
   *   otherwise.
   */
  protected function envelopeStatus(ResponseInterface $response): int {
    $body = json_decode((string) $response->getBody(), TRUE);
    if (is_array($body) && isset($body['status'])) {
      return (int) $body['status'];
    }
    return $response->getStatusCode();
  }

  /**
   * Returns the warmer settings.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The settings object.
   */
  protected function settings() {
    return $this->configFactory->get(self::CONFIG_NAME);
  }

}
