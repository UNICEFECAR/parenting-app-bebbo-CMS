<?php

namespace Drupal\custom_serialization\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\Cache;

/**
 * Service for caching media formatting results.
 *
 * This service eliminates repeated entity loads and database queries
 * by caching the formatted media data. Cache entries are automatically
 * invalidated when the media entity is updated using Drupal's cache tags.
 */
class MediaCacheService {

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Constructs a MediaCacheService object.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_default
   *   The default cache backend.
   */
  public function __construct(CacheBackendInterface $cache_default) {
    $this->cache = $cache_default;
  }

  /**
   * Get media data from cache or execute callable and cache result.
   *
   * @param int $media_id
   *   The media entity ID.
   * @param string $langcode
   *   The language code.
   * @param callable $callable
   *   A callable that returns the media data if not cached.
   *
   * @return array
   *   The media data array.
   */
  public function getMediaData($media_id, $langcode, callable $callable) {
    $cid = "media_formatter:{$media_id}:{$langcode}";
    $cached = $this->cache->get($cid);

    if ($cached) {
      return $cached->data;
    }

    // Execute the callable to get fresh data.
    $data = $callable();

    // Cache with media entity tags for auto-invalidation.
    $this->cache->set(
      $cid,
      $data,
      Cache::PERMANENT,
      ["media:{$media_id}"]
    );

    return $data;
  }

}
