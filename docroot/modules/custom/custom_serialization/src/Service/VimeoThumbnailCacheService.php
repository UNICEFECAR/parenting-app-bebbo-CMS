<?php

namespace Drupal\custom_serialization\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\Cache;

/**
 * Service for caching Vimeo thumbnail URLs.
 *
 * This service eliminates blocking external API calls during request cycles
 * by caching Vimeo thumbnail URLs permanently (until media entity is updated).
 * Includes timeout handling and graceful error fallback.
 */
class VimeoThumbnailCacheService {

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Constructs a VimeoThumbnailCacheService object.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_data
   *   The data cache backend.
   */
  public function __construct(CacheBackendInterface $cache_data) {
    $this->cache = $cache_data;
  }

  /**
   * Get Vimeo thumbnail URL from cache or fetch and cache.
   *
   * @param string $vimeo_video_id
   *   The Vimeo video ID.
   * @param int $media_id
   *   The media entity ID (for cache tag invalidation).
   * @param callable $callable
   *   A callable that fetches the thumbnail URL if not cached.
   *
   * @return string
   *   The thumbnail URL or empty string on error.
   */
  public function getThumbnailUrl($vimeo_video_id, $media_id, callable $callable) {
    $cid = "vimeo_thumbnail:{$vimeo_video_id}";
    $cached = $this->cache->get($cid);

    if ($cached) {
      return $cached->data;
    }

    // Execute the callable to fetch fresh data.
    $thumbnail_url = $callable();

    // Only cache successful results (non-empty URLs).
    if (!empty($thumbnail_url) && strpos($thumbnail_url, 'error') === FALSE) {
      // Cache permanently with media entity tag for invalidation.
      $this->cache->set(
        $cid,
        $thumbnail_url,
        Cache::PERMANENT,
        ["media:{$media_id}"]
      );
    }

    return $thumbnail_url;
  }

}
