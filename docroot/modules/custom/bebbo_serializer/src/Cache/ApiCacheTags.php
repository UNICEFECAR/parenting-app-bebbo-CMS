<?php

declare(strict_types=1);

namespace Drupal\bebbo_serializer\Cache;

/**
 * Cache tags scoping the API responses to one listing and one language.
 *
 * Core gives a listing only node_list, which every node save invalidates
 * whatever its bundle or language — one FAQ edit expiring every article
 * response on the site. These tags narrow that to the listings the saved
 * content can actually appear in.
 */
final class ApiCacheTags {

  /**
   * Tag for one bundle's listing in one language.
   *
   * @param string $bundle
   *   The node bundle the listing shows.
   * @param string $langcode
   *   The language the listing was built for.
   *
   * @return string
   *   The cache tag.
   */
  public static function listTag(string $bundle, string $langcode): string {
    return 'bebbo_api_list:' . $bundle . ':' . $langcode;
  }

  /**
   * Tags for several bundles in one language.
   *
   * @param string[] $bundles
   *   The node bundles the listing shows.
   * @param string $langcode
   *   The language the listing was built for.
   *
   * @return string[]
   *   One cache tag per bundle.
   */
  public static function listTags(array $bundles, string $langcode): array {
    return array_map(static fn(string $bundle): string => static::listTag($bundle, $langcode), $bundles);
  }

}
