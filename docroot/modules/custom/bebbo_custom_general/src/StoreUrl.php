<?php

declare(strict_types=1);

namespace Drupal\bebbo_custom_general;

/**
 * Recognises Google Play and Apple App Store listing URLs.
 *
 * Several admin screens hand a store link to the mobile apps, which can only
 * act on a real listing. Format validation is not enough — it accepts any
 * host — so the rule lives here once rather than per form.
 */
final class StoreUrl {

  /**
   * Host serving Google Play listings.
   */
  private const GOOGLE_PLAY_HOST = 'play.google.com';

  /**
   * Path of a Google Play listing.
   */
  private const GOOGLE_PLAY_PATH = '/store/apps/details';

  /**
   * Hosts serving App Store listings. itunes.apple.com is the legacy form.
   */
  private const APP_STORE_HOSTS = ['apps.apple.com', 'itunes.apple.com'];

  /**
   * Checks whether a URL is a Google Play app listing.
   *
   * @param string $url
   *   The URL to check.
   *
   * @return bool
   *   TRUE if the URL points at a Google Play listing.
   */
  public static function isGooglePlay(string $url): bool {
    $parts = self::parseHttps($url);
    if (!$parts || $parts['host'] !== self::GOOGLE_PLAY_HOST) {
      return FALSE;
    }

    // A listing is /store/apps/details?id=<package>. Anything else on the host
    // — a developer page, a search, the store front — is not an app.
    if (rtrim($parts['path'], '/') !== self::GOOGLE_PLAY_PATH) {
      return FALSE;
    }

    parse_str($parts['query'], $query);
    return !empty($query['id']);
  }

  /**
   * Checks whether a URL is an App Store app listing.
   *
   * @param string $url
   *   The URL to check.
   *
   * @return bool
   *   TRUE if the URL points at an App Store listing.
   */
  public static function isAppStore(string $url): bool {
    $parts = self::parseHttps($url);
    if (!$parts || !in_array($parts['host'], self::APP_STORE_HOSTS, TRUE)) {
      return FALSE;
    }

    // Every listing carries the numeric app id as a path segment, whatever
    // region or app-name slug precedes it.
    return (bool) preg_match('#/id\d+#', $parts['path']);
  }

  /**
   * Splits a URL, rejecting anything that is not a well-formed https URL.
   *
   * @param string $url
   *   The URL to parse.
   *
   * @return array{host: string, path: string, query: string}|null
   *   The lower-cased host with the path and query, or NULL if the URL is
   *   malformed or not https.
   */
  private static function parseHttps(string $url): ?array {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
      return NULL;
    }

    $parts = parse_url($url);
    if (empty($parts['host']) || strtolower($parts['scheme'] ?? '') !== 'https') {
      return NULL;
    }

    return [
      'host' => strtolower($parts['host']),
      'path' => $parts['path'] ?? '',
      'query' => $parts['query'] ?? '',
    ];
  }

}
