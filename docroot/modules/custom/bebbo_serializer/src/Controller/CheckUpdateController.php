<?php

namespace Drupal\bebbo_serializer\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Force-update / check-update API for the v1 and v2 app surfaces.
 *
 * Serves the same JSON the legacy CustomRestResource produced
 * (/api/check-update/{country}), but:
 * - Fetches both update types in a single query instead of two.
 * - Returns a cacheable response (tag-invalidated; the admin save in
 *   ForceUpdateCheckForm flushes caches, so flags never go stale).
 * - Carries no authentication provider. The v1 path is public; the v2 path
 *   is protected by the bebbo_api_security JWT subscriber via its /v2/api/*
 *   pattern match — no auth wiring lives here.
 *
 * Both routes (bebbo_serializer.v1_check_update and .v2_check_update) point
 * at checkUpdate(); the only difference is the path prefix, which decides
 * whether bebbo_api_security covers the request.
 */
class CheckUpdateController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Constructs a CheckUpdateController.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
    );
  }

  /**
   * Responds to GET requests for the check-update endpoint.
   *
   * Returns the force-update flag status for both the content_update and
   * app_update record types for the given country ID. Top-level 'flag' and
   * 'updated_at' keys mirror the content_update record for backward
   * compatibility with existing app clients.
   *
   * @param int|string|null $country
   *   The country (group entity) ID from the URL.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   The JSON response, cacheable and invalidated by the force-update table.
   */
  public function checkUpdate($country = NULL): CacheableJsonResponse {
    $country_id = (int) $country;

    $records = $this->fetchLatestRecords($country_id);
    $content_update = $records['content_update'] ?? NULL;
    $app_update = $records['app_update'] ?? NULL;

    // Cache metadata: vary by URL, invalidate on any force-update write.
    $cacheability = (new CacheableMetadata())
      ->setCacheContexts(['url'])
      ->setCacheTags(['bebbo_force_update:' . $country_id]);

    if ($content_update === NULL && $app_update === NULL) {
      $response = new CacheableJsonResponse([
        'status' => 204,
        'message' => 'No Records Found',
      ]);
      $response->addCacheableDependency($cacheability);
      return $response;
    }

    $data = ['status' => 200];

    // Backward-compatible top-level keys always reflect content_update.
    if ($content_update !== NULL) {
      $data['flag'] = (int) $content_update->flag_status;
      $data['updated_at'] = $content_update->created_at;
    }
    else {
      $data['flag'] = NULL;
      $data['updated_at'] = NULL;
    }

    // Structured content_update block.
    $data['content_update'] = $content_update !== NULL ? [
      'flag' => (int) $content_update->flag_status,
      'updated_at' => $content_update->created_at,
    ] : NULL;

    // Structured app_update block including optional store URLs.
    $data['app_update'] = $app_update !== NULL ? [
      'flag' => (int) $app_update->flag_status,
      'updated_at' => $app_update->created_at,
      'google_play_url' => $app_update->google_play_url ?: NULL,
      'app_store_url' => $app_update->app_store_url ?: NULL,
    ] : NULL;

    $response = new CacheableJsonResponse($data);
    $response->addCacheableDependency($cacheability);
    return $response;
  }

  /**
   * Fetches the latest record for each update type in a single query.
   *
   * Pulls every row for the country (both update types) ordered newest-first,
   * then keeps the first row seen per type. One DB round-trip instead of two.
   *
   * @param int $country_id
   *   The country (group entity) ID.
   *
   * @return array<string, object>
   *   Map of update type to its latest database row (only types present).
   */
  protected function fetchLatestRecords(int $country_id): array {
    $rows = $this->database->select('forcefull_check_update_api', 'f')
      ->fields('f')
      ->condition('f.countries_id', $country_id)
      ->condition('f.update_type', ['content_update', 'app_update'], 'IN')
      ->orderBy('f.id', 'DESC')
      ->execute();

    $latest = [];
    foreach ($rows as $row) {
      // Rows are newest-first, so the first occurrence per type is the latest.
      if (!isset($latest[$row->update_type])) {
        $latest[$row->update_type] = $row;
      }
    }

    return $latest;
  }

}
