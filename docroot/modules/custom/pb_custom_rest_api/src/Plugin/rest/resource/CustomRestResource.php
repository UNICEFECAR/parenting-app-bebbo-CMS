<?php

namespace Drupal\pb_custom_rest_api\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Custom Rest Resource.
 *
 * @RestResource(
 *   id = "custom_rest_resource",
 *   label = @Translation("Custom Rest Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/check-update/{country}"
 *   }
 * )
 */
class CustomRestResource extends ResourceBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->database = $container->get('database');
    return $instance;
  }

  /**
   * Responds to GET requests.
   *
   * Returns force-update flag status for both content_update and app_update
   * record types for the given country ID.
   *
   * Top-level 'flag' and 'updated_at' keys reflect the content_update record
   * for backward compatibility with existing app clients.
   *
   * @param int|string $country
   *   The country (group entity) ID from the URL.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   */
  public function get($country = NULL) {
    $content_update = $this->fetchLatestRecord((int) $country, 'content_update');
    $app_update = $this->fetchLatestRecord((int) $country, 'app_update');

    if ($content_update === NULL && $app_update === NULL) {
      return new ResourceResponse([
        'status' => 204,
        'message' => 'No Records Found',
      ]);
    }

    $response = ['status' => 200];

    // Backward-compatible top-level keys always reflect content_update.
    if ($content_update !== NULL) {
      $response['flag'] = (int) $content_update->flag_status;
      $response['updated_at'] = $content_update->created_at;
    }
    else {
      $response['flag'] = NULL;
      $response['updated_at'] = NULL;
    }

    // Structured content_update block.
    $response['content_update'] = $content_update !== NULL ? [
      'flag' => (int) $content_update->flag_status,
      'updated_at' => $content_update->created_at,
    ] : NULL;

    // Structured app_update block including optional store URLs.
    $response['app_update'] = $app_update !== NULL ? [
      'flag' => (int) $app_update->flag_status,
      'updated_at' => $app_update->created_at,
      'google_play_url' => $app_update->google_play_url ?: NULL,
      'app_store_url' => $app_update->app_store_url ?: NULL,
    ] : NULL;

    return new ResourceResponse($response);
  }

  /**
   * Fetches the most recent force-update record for a country and update type.
   *
   * @param int $country_id
   *   The country (group entity) ID.
   * @param string $update_type
   *   The update type: 'content_update' or 'app_update'.
   *
   * @return object|null
   *   The database row object, or NULL if no record exists.
   */
  protected function fetchLatestRecord(int $country_id, string $update_type): ?object {
    $result = $this->database->select('forcefull_check_update_api', 'f')
      ->fields('f')
      ->condition('f.countries_id', $country_id)
      ->condition('f.update_type', $update_type)
      ->orderBy('f.id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchObject();

    return $result ?: NULL;
  }

}
