<?php

namespace Drupal\acquia_search;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheBackendInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Class AcquiaSearchV3ApiClient.
 *
 * @package Drupal\acquia_search\
 */
class AcquiaSearchV3ApiClient {

  /**
   * Search V3 API host.
   *
   * @var string
   */
  protected $searchV3Host;

  /**
   * Search V3 API key.
   *
   * @var string
   */
  protected $searchV3ApiKey;

  /**
   * Http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Headers.
   *
   * @var array
   */
  protected $headers;

  /**
   * Cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The HTTP client to fetch the feed data with.
   *
   * @var \GuzzleHttp\Client
   */
  protected $client;

  /**
   * AcquiaSearchV3ApiClient constructor.
   *
   * @param string $host
   *   Search V3 API host.
   * @param string $api_key
   *   Search V3 API key.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   Http client.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   Cache.
   */
  public function __construct($host, $api_key, ClientInterface $http_client, CacheBackendInterface $cache) {
    $this->searchV3Host = $host;
    $this->searchV3ApiKey = $api_key;
    $this->httpClient = $http_client;
    $this->headers = [
      'Content-Type' => 'application/json',
      'Accept' => 'application/json',
    ];
    $this->cache = $cache;
  }

  /**
   * Helper function to fetch all search v3 indexes for given network_id.
   *
   * @param string $network_id
   *   Subscription network id.
   *
   * @return array|false
   *   Response array or FALSE
   */
  public function getSearchV3Indexes($network_id) {
    $result = [];
    if ($cache = $this->cache->get('acquia_search.v3indexes')) {
      if (is_array($cache->data) && $cache->expire > time()) {
        return $cache->data;
      }
    }
    $indexes = $this->searchRequest('/index/network_id/get_all?network_id=' . $network_id);
    if (is_array($indexes)) {
      if (!empty($indexes)) {
        foreach ($indexes as $index) {
          $result[] = [
            'balancer' => $index['host'],
            'core_id' => $index['name'],
            'version' => 'v3',
          ];
        }
      }
      // Cache will be set in both cases, 1. when search v3 cores are found and
      // 2. when there are no search v3 cores but api is reachable.
      $this->cache->set('acquia_search.v3indexes', $result, time() + (24 * 60 * 60));
      return $result;
    }
    else {
      // When api is not reachable, cache it for 1 minute.
      $this->cache->set('acquia_search.v3keys', $result, time() + (60));
    }

    return FALSE;
  }

  /**
   * Fetch the search v3 index keys for given core_id and network_id.
   *
   * @param string $core_id
   *   Core id.
   * @param string $network_id
   *   Acquia identifier.
   *
   * @return array|bool|false
   *   Search v3 index keys.
   */
  public function getKeys($core_id, $network_id) {
    if ($cache = $this->cache->get('acquia_search.v3keys')) {
      if (!empty($cache->data) && $cache->expire > time()) {
        return $cache->data;
      }
    }

    $keys = $this->searchRequest('/index/key?index_name=' . $core_id . '&network_id=' . $network_id);
    if ($keys) {
      // Cache will be set in both cases, 1. when search v3 cores are found and
      // 2. when there are no search v3 cores but api is reachable.
      $this->cache->set('acquia_search.v3keys', $keys, time() + (24 * 60 * 60));
      return $keys;
    }
    else {
      // When api is not reachable, cache it for 1 minute.
      $this->cache->set('acquia_search.v3keys', $keys, time() + (60));
    }

    return FALSE;

  }

  /**
   * Create and send a request to search controller.
   *
   * @param string $path
   *   Path to call.
   *
   * @return array|false
   *   Response array or FALSE.
   */
  public function searchRequest($path) {
    $data = [
      'host' => $this->searchV3Host,
      'headers' => [
        'x-api-key' => $this->searchV3ApiKey,
      ],
    ];
    $uri = $data['host'] . $path;
    $options = [
      'headers' => $data['headers'],
      'body' => Json::encode($data),
    ];

    try {
      $response = $this->httpClient->get($uri, $options);
      if (!$response) {
        throw new \Exception('Empty Response');
      }
      $stream_size = $response->getBody()->getSize();
      $data = Json::decode($response->getBody()->read($stream_size));
      $status_code = $response->getStatusCode();

      if ($status_code < 200 || $status_code > 299) {
        \Drupal::logger('acquia search')->error("Couldn't connect to search v3 API: @message",
          ['@message' => $response->getReasonPhrase()]);
        return FALSE;
      }
      return $data;
    }
    catch (RequestException $e) {
      if ($e->getCode() == 401) {
        \Drupal::logger('acquia search')->error("Couldn't connect to search v3 API:
          Received a 401 response from the API indicating that credentials are incorrect.
          Please validate your credentials. @message", ['@message' => $e->getMessage()]);
      }
      elseif ($e->getCode() == 404) {
        \Drupal::logger('acquia search')->error("Couldn't connect to search v3 API:
          Received a 404 response from the API indicating that the api host is incorrect.
          Please validate your host. @message", ['@message' => $e->getMessage()]);
      }
      else {
        \Drupal::logger('acquia search')->error("Couldn't connect to search v3 API: Please
        validate your api host and credentials. @message", ['@message' => $e->getMessage()]);
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('acquia search')->error("Couldn't connect to search v3 API: @message",
        ['@message' => $e->getMessage()]);
    }

    return FALSE;
  }

}
