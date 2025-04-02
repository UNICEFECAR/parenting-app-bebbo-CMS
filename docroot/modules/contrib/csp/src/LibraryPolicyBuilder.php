<?php

namespace Drupal\csp;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Utility\Error;
use GuzzleHttp\Psr7\Uri;
use Psr\Log\LoggerInterface;

/**
 * Service to build policy information for libraries.
 */
class LibraryPolicyBuilder {

  /**
   * The Library Discovery service.
   *
   * @var \Drupal\Core\Asset\LibraryDiscovery
   */
  protected $libraryDiscovery;

  /**
   * The cache bin.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The Theme Handler service.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * Static cache of library source information for each extension.
   *
   * This reduces lookup calls to the database when generating information for
   * an extension, or when retrieving data for multiple libraries in an
   * extension.
   *
   * @var array
   */
  protected $librarySourcesCache;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Constructs a new Library Parser.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache bin.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The Module Handler service.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $themeHandler
   *   The Theme Handler service.
   * @param \Drupal\Core\Asset\LibraryDiscoveryInterface $libraryDiscovery
   *   The Library Discovery Collector service.
   * @param \Psr\Log\LoggerInterface|null $logger
   *   The logger channel.
   */
  public function __construct(
    CacheBackendInterface $cache,
    ModuleHandlerInterface $moduleHandler,
    ThemeHandlerInterface $themeHandler,
    LibraryDiscoveryInterface $libraryDiscovery,
    ?LoggerInterface $logger = NULL,
  ) {
    $this->cache = $cache;
    $this->moduleHandler = $moduleHandler;
    $this->themeHandler = $themeHandler;
    $this->libraryDiscovery = $libraryDiscovery;
    if (empty($logger)) {
      @trigger_error("Omitting the LoggerChannel service is deprecated in csp:8.x-1.23 and will be required in csp:2.0.0. See https://www.drupal.org/project/csp/issues/3406513", E_USER_DEPRECATED);
      $logger = \Drupal::logger('csp');
    }
    $this->logger = $logger;
  }

  /**
   * Retrieve all sources required for the active theme.
   *
   * @return array
   *   An array of sources keyed by type.
   */
  public function getSources() {
    $cid = implode(':', [
      'csp',
      'sources',
    ]);

    if (($cacheItem = $this->cache->get($cid))) {
      return $cacheItem->data;
    }

    $extensions = array_merge(
      ['core'],
      array_keys($this->moduleHandler->getModuleList()),
      array_keys($this->themeHandler->listInfo())
    );

    $sources = [];

    foreach ($extensions as $extensionName) {
      $extensionSources = $this->getExtensionSources($extensionName);
      $sources = NestedArray::mergeDeep($sources, $extensionSources);
    }

    foreach (array_keys($sources) as $type) {
      sort($sources[$type]);
      $sources[$type] = array_unique($sources[$type]);
    }

    $this->cache->set($cid, $sources, Cache::PERMANENT, [
      'library_info',
      'config:core.extension',
    ]);

    return $sources;
  }

  /**
   * Get the required sources for an extension.
   *
   * @param string $extension
   *   The name of the extension that registered a library.
   *
   * @return array
   *   An array of sources keyed by type.
   */
  protected function getExtensionSources($extension) {
    $cid = implode(':', ['csp', 'extension', $extension]);

    $cacheItem = $this->cache->get($cid);
    if ($cacheItem) {
      return $cacheItem->data;
    }

    $sources = [];

    try {
      $moduleLibraries = $this->libraryDiscovery->getLibrariesByExtension($extension);
    }
    catch (\Exception $e) {
      // Ignore invalid library definitions.
      // @see \Drupal\Core\Asset\LibraryDiscoveryParser::buildByExtension()
      $this->logger->warning(Error::DEFAULT_ERROR_MESSAGE, Error::decodeException($e));
      $moduleLibraries = [];
    }

    foreach ($moduleLibraries as $libraryName => $libraryInfo) {
      $librarySources = $this->getLibrarySources($extension, $libraryName);
      $sources = NestedArray::mergeDeep($sources, $librarySources);
    }

    $this->cache->set($cid, $sources, Cache::PERMANENT, [
      'library_info',
    ]);

    return $sources;
  }

  /**
   * Get the required sources for a single library.
   *
   * @param string $extension
   *   The name of the extension that registered a library.
   * @param string $name
   *   The name of a registered library to retrieve.
   *
   * @return array
   *   An array of sources keyed by type.
   */
  protected function getLibrarySources($extension, $name) {
    $cid = implode(':', ['csp', 'libraries', $extension]);

    if (!isset($this->librarySourcesCache[$extension])) {
      $cacheItem = $this->cache->get($cid);
      if ($cacheItem) {
        $this->librarySourcesCache[$extension] = $cacheItem->data;
      }
    }

    if (isset($this->librarySourcesCache[$extension][$name])) {
      return $this->librarySourcesCache[$extension][$name];
    }

    $libraryInfo = $this->libraryDiscovery->getLibraryByName($extension, $name);
    $sources = [];

    foreach ($libraryInfo['js'] as $jsInfo) {
      if (
        $jsInfo['type'] == 'external'
        &&
        !empty($jsInfo['data'])
        &&
        ($host = self::getHostFromUri($jsInfo['data']))
      ) {
        $sources['script-src'][] = $host;
        $sources['script-src-elem'][] = $host;
      }
    }
    foreach ($libraryInfo['css'] as $cssInfo) {
      if (
        $cssInfo['type'] == 'external'
        &&
        !empty($cssInfo['data'])
        &&
        ($host = self::getHostFromUri($cssInfo['data']))
      ) {
        $sources['style-src'][] = $host;
        $sources['style-src-elem'][] = $host;
      }
    }

    $this->librarySourcesCache[$extension][$name] = $sources;
    $this->cache->set($cid, $this->librarySourcesCache[$extension], Cache::PERMANENT, [
      'library_info',
    ]);

    return $this->librarySourcesCache[$extension][$name];
  }

  /**
   * Get host info from a URI.
   *
   * @param string $uri
   *   The URI.
   *
   * @return string
   *   The host info.
   */
  public static function getHostFromUri($uri) {
    $uri = new Uri($uri);
    $host = $uri->getHost();

    // Only include scheme if restricted to HTTPS.
    if ($uri->getScheme() === 'https') {
      $host = 'https://' . $host;
    }
    if (($port = $uri->getPort())) {
      $host .= ':' . $port;
    }
    return $host;
  }

}
