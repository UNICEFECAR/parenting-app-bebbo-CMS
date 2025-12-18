<?php

namespace Drupal\custom_serialization\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

/**
 * Helper service for CustomSerializer with caching and batch loading.
 *
 * This service provides:
 * - Batch entity loading to reduce database queries
 * - Request-level static caching for entities
 * - Persistent caching for external API calls (Vimeo)
 * - Cached lookups for frequently accessed data (taxonomy terms)
 *
 * Performance improvements:
 * - Reduces N+1 query problems by batch loading entities
 * - Caches Vimeo API responses for 24 hours
 * - Caches taxonomy term IDs permanently (invalidated by cache tags)
 * - Uses Drupal's HTTP client instead of raw cURL for better error handling
 */
class CustomSerializerHelper {

  /**
   * Cache TTL constants.
   */
  // 24 hours.
  private const VIMEO_CACHE_TTL = 86400;
  // 5 minutes for failed requests.
  private const FAILURE_CACHE_TTL = 300;
  // 3 seconds.
  private const HTTP_TIMEOUT = 3;
  // 2 seconds.
  private const HTTP_CONNECT_TIMEOUT = 2;

  /**
   * Request-level cache for media entities.
   *
   * @var array<int, \Drupal\media\MediaInterface|null>
   */
  protected array $mediaCache = [];

  /**
   * Request-level cache for file entities.
   *
   * @var array<int, \Drupal\file\FileInterface|null>
   */
  protected array $fileCache = [];

  /**
   * Cached ImageStyle object (loaded once per request).
   *
   * @var \Drupal\image\Entity\ImageStyle|null
   */
  protected $imageStyle = NULL;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Cached Pregnancy term ID.
   *
   * @var int|null
   */
  protected ?int $pregnancyTermId = NULL;

  /**
   * Flag to track if pregnancy term ID was looked up.
   *
   * @var bool
   */
  protected bool $pregnancyTermIdLoaded = FALSE;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $cache;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * Constructs a CustomSerializerHelper object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    Connection $database,
    CacheBackendInterface $cache,
    ClientInterface $http_client,
    LoggerInterface $logger,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
    $this->cache = $cache;
    $this->httpClient = $http_client;
    $this->logger = $logger;
  }

  /**
   * Batch load media entities with request-level caching.
   *
   * This method loads multiple media entities in a single query
   * and caches them for the duration of the request to prevent
   * repeated database queries.
   *
   * @param array $media_ids
   *   Array of media entity IDs to load.
   *
   * @return array<int, \Drupal\media\MediaInterface>
   *   Array of loaded media entities keyed by ID.
   */
  public function loadMediaBatch(array $media_ids): array {
    // Filter out empty values and already cached IDs.
    $media_ids = array_filter($media_ids);
    $to_load = array_diff($media_ids, array_keys($this->mediaCache));

    // Load only uncached entities.
    if (!empty($to_load)) {
      $entities = $this->entityTypeManager
        ->getStorage('media')
        ->loadMultiple($to_load);
      foreach ($entities as $id => $entity) {
        $this->mediaCache[$id] = $entity;
      }
      // Mark missing IDs as NULL to prevent repeated lookups.
      foreach ($to_load as $id) {
        if (!isset($this->mediaCache[$id])) {
          $this->mediaCache[$id] = NULL;
        }
      }
    }

    // Return requested entities (excluding NULLs).
    $result = [];
    foreach ($media_ids as $id) {
      if (isset($this->mediaCache[$id]) && $this->mediaCache[$id] !== NULL) {
        $result[$id] = $this->mediaCache[$id];
      }
    }

    return $result;
  }

  /**
   * Get a single media entity with caching.
   *
   * @param int|string $media_id
   *   The media entity ID.
   *
   * @return \Drupal\media\MediaInterface|null
   *   The media entity or NULL if not found.
   */
  public function getMediaEntity($media_id): ?MediaInterface {
    if (empty($media_id)) {
      return NULL;
    }

    $media_id = (int) $media_id;

    if (!isset($this->mediaCache[$media_id])) {
      $this->mediaCache[$media_id] = $this->entityTypeManager
        ->getStorage('media')
        ->load($media_id);
    }

    return $this->mediaCache[$media_id];
  }

  /**
   * Batch load file entities with request-level caching.
   *
   * @param array $file_ids
   *   Array of file entity IDs to load.
   *
   * @return array<int, \Drupal\file\FileInterface>
   *   Array of loaded file entities keyed by ID.
   */
  public function loadFileBatch(array $file_ids): array {
    $file_ids = array_filter($file_ids);
    $to_load = array_diff($file_ids, array_keys($this->fileCache));

    if (!empty($to_load)) {
      $entities = $this->entityTypeManager
        ->getStorage('file')
        ->loadMultiple($to_load);
      foreach ($entities as $id => $entity) {
        $this->fileCache[$id] = $entity;
      }
      foreach ($to_load as $id) {
        if (!isset($this->fileCache[$id])) {
          $this->fileCache[$id] = NULL;
        }
      }
    }

    $result = [];
    foreach ($file_ids as $id) {
      if (isset($this->fileCache[$id]) && $this->fileCache[$id] !== NULL) {
        $result[$id] = $this->fileCache[$id];
      }
    }

    return $result;
  }

  /**
   * Get a single file entity with caching.
   *
   * @param int|string $file_id
   *   The file entity ID.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file entity or NULL if not found.
   */
  public function getFileEntity($file_id): ?FileInterface {
    if (empty($file_id)) {
      return NULL;
    }

    $file_id = (int) $file_id;

    if (!isset($this->fileCache[$file_id])) {
      $this->fileCache[$file_id] = $this->entityTypeManager
        ->getStorage('file')
        ->load($file_id);
    }

    return $this->fileCache[$file_id];
  }

  /**
   * Get file URIs in batch using a single database query.
   *
   * This is more efficient than loading full file entities
   * when only the URI is needed.
   *
   * @param array $file_ids
   *   Array of file IDs.
   *
   * @return array<int, string>
   *   Array of file URIs keyed by file ID.
   */
  public function getFileUrisBatch(array $file_ids): array {
    if (empty($file_ids)) {
      return [];
    }

    $file_ids = array_filter(array_unique($file_ids));

    return $this->database->select('file_managed', 'f')
      ->fields('f', ['fid', 'uri'])
      ->condition('fid', $file_ids, 'IN')
      ->execute()
      ->fetchAllKeyed(0, 1);
  }

  /**
   * Get media image alt text in batch using a single database query.
   *
   * @param array $media_ids
   *   Array of media entity IDs.
   * @param string $langcode
   *   The language code.
   *
   * @return array<int, string>
   *   Array of alt texts keyed by media entity ID.
   */
  public function getMediaAltTextBatch(array $media_ids, string $langcode): array {
    if (empty($media_ids)) {
      return [];
    }

    $media_ids = array_filter(array_unique($media_ids));

    return $this->database->select('media__field_media_image', 'm')
      ->fields('m', ['entity_id', 'field_media_image_alt'])
      ->condition('entity_id', $media_ids, 'IN')
      ->condition('langcode', $langcode)
      ->execute()
      ->fetchAllKeyed(0, 1);
  }

  /**
   * Get ImageStyle object with request-level caching.
   *
   * The ImageStyle is loaded once per request and reused
   * for all image URL generation.
   *
   * @param string $style_name
   *   The image style machine name.
   *
   * @return \Drupal\image\Entity\ImageStyle|null
   *   The loaded ImageStyle or NULL if not found.
   */
  public function getImageStyle(string $style_name = 'content_1200xh_') {
    if ($this->imageStyle === NULL) {
      $this->imageStyle = $this->entityTypeManager
        ->getStorage('image_style')
        ->load($style_name);
    }
    return $this->imageStyle;
  }

  /**
   * Get Vimeo video thumbnail URL with persistent caching.
   *
   * This method caches Vimeo API responses for 24 hours to avoid
   * external API calls on every request. Failed requests are also
   * cached for 5 minutes to prevent repeated failed calls.
   *
   * Uses Drupal's HTTP client instead of raw cURL for:
   * - Better error handling
   * - Automatic timeouts
   * - PSR-7 compliance
   *
   * @param string $video_id
   *   The Vimeo video ID.
   *
   * @return string|null
   *   The thumbnail URL or NULL if not available.
   */
  public function getVimeoThumbnail(string $video_id): ?string {
    if (empty($video_id)) {
      return NULL;
    }

    $cid = 'custom_serialization:vimeo:' . $video_id;

    // Check persistent cache first.
    if ($cache = $this->cache->get($cid)) {
      return $cache->data;
    }

    try {
      // Use Drupal's HTTP client with proper timeouts.
      $response = $this->httpClient->request('GET',
        "https://vimeo.com/api/oembed.json?url=https://vimeo.com/{$video_id}",
        [
          'timeout' => self::HTTP_TIMEOUT,
          'connect_timeout' => self::HTTP_CONNECT_TIMEOUT,
        ]
      );

      $data = json_decode($response->getBody()->getContents(), TRUE);
      $thumbnail = $data['thumbnail_url'] ?? NULL;

      // Cache successful response for 24 hours.
      $this->cache->set(
        $cid,
        $thumbnail,
        time() + self::VIMEO_CACHE_TTL,
        ['vimeo_thumbnails']
      );

      return $thumbnail;
    }
    catch (RequestException $e) {
      // Log the error for debugging.
      $this->logger->warning(
        'Failed to fetch Vimeo thumbnail for video @id: @error',
        ['@id' => $video_id, '@error' => $e->getMessage()]
      );

      // Cache failure for 5 minutes to prevent repeated failed requests.
      $this->cache->set(
        $cid,
        NULL,
        time() + self::FAILURE_CACHE_TTL,
        ['vimeo_thumbnails']
      );

      return NULL;
    }
  }

  /**
   * Extract Vimeo video ID from an oEmbed URL.
   *
   * @param string $oembed_url
   *   The Vimeo oEmbed URL.
   *
   * @return string|null
   *   The extracted video ID or NULL if not found.
   */
  public function extractVimeoId(string $oembed_url): ?string {
    $parsed_url = parse_url($oembed_url);
    if (isset($parsed_url['path'])) {
      $path_segments = explode('/', trim($parsed_url['path'], '/'));
      return end($path_segments) ?: NULL;
    }
    return NULL;
  }

  /**
   * Get the Pregnancy taxonomy term ID with persistent caching.
   *
   * This term ID is used frequently for filtering and is cached
   * permanently with cache tags for automatic invalidation.
   *
   * @return int|null
   *   The Pregnancy term ID or NULL if not found.
   */
  public function getPregnancyTermId(): ?int {
    // Return cached value if already looked up in this request.
    if ($this->pregnancyTermIdLoaded) {
      return $this->pregnancyTermId;
    }

    $cid = 'custom_serialization:pregnancy_tid';

    // Check persistent cache.
    if ($cache = $this->cache->get($cid)) {
      $this->pregnancyTermId = $cache->data;
      $this->pregnancyTermIdLoaded = TRUE;
      return $this->pregnancyTermId;
    }

    // Query for the term.
    $term_ids = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->getQuery()
      ->condition('vid', 'child_age')
      ->condition('name', 'Pregnancy')
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();

    $this->pregnancyTermId = $term_ids ? (int) reset($term_ids) : NULL;
    $this->pregnancyTermIdLoaded = TRUE;

    // Cache permanently with cache tags for invalidation.
    $this->cache->set(
      $cid,
      $this->pregnancyTermId,
      CacheBackendInterface::CACHE_PERMANENT,
      ['taxonomy_term_list:child_age']
    );

    return $this->pregnancyTermId;
  }

  /**
   * Get language configuration data in batch.
   *
   * @param array $langcodes
   *   Array of language codes.
   *
   * @return array
   *   Array of language data keyed by langcode.
   */
  public function getLanguageDataBatch(array $langcodes): array {
    if (empty($langcodes)) {
      return [];
    }

    $langcodes = array_filter(array_unique($langcodes));

    return $this->database->select('custom_language_data', 'cld')
      ->fields('cld')
      ->condition('langcode', $langcodes, 'IN')
      ->execute()
      ->fetchAllAssoc('langcode', \PDO::FETCH_ASSOC);
  }

  /**
   * Get current timestamp in the specified timezone.
   *
   * Uses Drupal's DrupalDateTime class instead of global
   * date_default_timezone_set() to avoid side effects.
   *
   * @param string $timezone
   *   The timezone identifier.
   * @param string $format
   *   The date format.
   *
   * @return string
   *   The formatted timestamp.
   */
  public function getCurrentTimestamp(string $timezone = 'Asia/Kolkata', string $format = 'Y-m-d H:i'): string {
    $date = new DrupalDateTime('now', $timezone);
    return $date->format($format);
  }

  /**
   * Convert an image URL to WebP format.
   *
   * @param string $url
   *   The original image URL.
   *
   * @return string
   *   The URL with WebP extension.
   */
  public function convertToWebp(string $url): string {
    if (empty($url)) {
      return $url;
    }
    return preg_replace('/\.(jpg|jpeg|png)(\?.*)?$/i', '.webp$2', $url);
  }

  /**
   * Clear request-level caches.
   *
   * Call this at the start of processing if you need fresh data.
   */
  public function clearRequestCache(): void {
    $this->mediaCache = [];
    $this->fileCache = [];
    $this->imageStyle = NULL;
    $this->pregnancyTermId = NULL;
    $this->pregnancyTermIdLoaded = FALSE;
    $this->countryGroupsCache = NULL;
    $this->taxonomyTermsCache = [];
    $this->configurableLanguageCache = [];
    $this->groupCache = [];
  }

  /**
   * Request-level cache for country groups.
   *
   * @var array|null
   */
  protected ?array $countryGroupsCache = NULL;

  /**
   * Request-level cache for taxonomy terms.
   *
   * @var array
   */
  protected array $taxonomyTermsCache = [];

  /**
   * Get all country groups with caching (loaded once per request).
   *
   * This replaces Group::loadMultiple() without parameters which
   * loads ALL groups. Instead, we load and cache for the request.
   *
   * @return array
   *   Array of group entities.
   */
  public function getCountryGroups(): array {
    if ($this->countryGroupsCache !== NULL) {
      return $this->countryGroupsCache;
    }

    // Load groups from entity type manager.
    $group_storage = $this->entityTypeManager->getStorage('group');
    $group_ids = $group_storage->getQuery()
      ->condition('type', 'country')
      ->accessCheck(FALSE)
      ->execute();

    $this->countryGroupsCache = $group_storage->loadMultiple($group_ids);
    return $this->countryGroupsCache;
  }

  /**
   * Get all group IDs (for validation).
   *
   * @return array
   *   Array of group IDs as strings.
   */
  public function getCountryGroupIds(): array {
    $groups = $this->getCountryGroups();
    $ids = [];
    foreach ($groups as $group) {
      $ids[] = $group->get('id')->getString();
    }
    return $ids;
  }

  /**
   * Get node title by ID with batch support.
   *
   * @param array $nids
   *   Array of node IDs.
   * @param string $langcode
   *   Language code.
   *
   * @return array
   *   Array of titles keyed by node ID.
   */
  public function getNodeTitlesBatch(array $nids, string $langcode = 'en'): array {
    if (empty($nids)) {
      return [];
    }

    $nids = array_filter(array_unique($nids));

    return $this->database->select('node_field_data', 'n')
      ->fields('n', ['nid', 'title'])
      ->condition('nid', $nids, 'IN')
      ->condition('langcode', $langcode)
      ->execute()
      ->fetchAllKeyed(0, 1);
  }

  /**
   * Batch load taxonomy terms by vocabulary with caching.
   *
   * @param string $vocabulary
   *   The vocabulary machine name.
   * @param string $langcode
   *   The language code.
   *
   * @return array
   *   Array of term data.
   */
  public function getTaxonomyTermsBatch(string $vocabulary, string $langcode): array {
    $cache_key = $vocabulary . ':' . $langcode;

    if (isset($this->taxonomyTermsCache[$cache_key])) {
      return $this->taxonomyTermsCache[$cache_key];
    }

    $query = $this->database->select('taxonomy_term_field_data', 't')
      ->fields('t')
      ->condition('vid', $vocabulary)
      ->condition('langcode', $langcode)
      ->condition('status', 1);

    // Special sorting for child_age vocabulary.
    if ($vocabulary === 'child_age') {
      $query->addExpression("CASE WHEN vid = 'child_age' THEN weight ELSE 999999 END", 'sorted_weight');
      $query->orderBy('sorted_weight', 'ASC');
    }
    else {
      $query->orderBy('vid', 'ASC');
    }

    $this->taxonomyTermsCache[$cache_key] = $query->execute()->fetchAll();
    return $this->taxonomyTermsCache[$cache_key];
  }

  /**
   * Batch load taxonomy term entities with caching.
   *
   * @param array $tids
   *   Array of term IDs.
   *
   * @return array
   *   Array of term entities keyed by tid.
   */
  public function loadTaxonomyTermsBatch(array $tids): array {
    if (empty($tids)) {
      return [];
    }

    $tids = array_filter(array_unique($tids));
    return $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->loadMultiple($tids);
  }

  /**
   * Get term IDs by names (cached lookup).
   *
   * @param array $term_names
   *   Array of term names.
   *
   * @return array
   *   Array of ['tid' => int, 'vid' => string] keyed by term name.
   */
  public function getTermIdsByNames(array $term_names): array {
    if (empty($term_names)) {
      return [];
    }

    $result = [];
    $terms = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->loadByProperties(['name' => $term_names]);

    foreach ($terms as $term) {
      $result[$term->getName()][] = [
        'tid' => $term->id(),
        'vid' => $term->bundle(),
      ];
    }

    return $result;
  }

  /**
   * Request-level cache for ConfigurableLanguage entities.
   *
   * @var array
   */
  protected array $configurableLanguageCache = [];

  /**
   * Request-level cache for Group entities by ID.
   *
   * @var array
   */
  protected array $groupCache = [];

  /**
   * Batch load ConfigurableLanguage entities with request-level caching.
   *
   * @param array $langcodes
   *   Array of language codes.
   *
   * @return array
   *   Array of ConfigurableLanguage entities keyed by langcode.
   */
  public function loadConfigurableLanguagesBatch(array $langcodes): array {
    if (empty($langcodes)) {
      return [];
    }

    $langcodes = array_filter(array_unique($langcodes));
    $to_load = array_diff($langcodes, array_keys($this->configurableLanguageCache));

    if (!empty($to_load)) {
      $entities = $this->entityTypeManager
        ->getStorage('configurable_language')
        ->loadMultiple($to_load);
      foreach ($entities as $langcode => $entity) {
        $this->configurableLanguageCache[$langcode] = $entity;
      }
      // Mark missing langcodes as NULL to prevent repeated lookups.
      foreach ($to_load as $langcode) {
        if (!isset($this->configurableLanguageCache[$langcode])) {
          $this->configurableLanguageCache[$langcode] = NULL;
        }
      }
    }

    // Return requested entities (excluding NULLs).
    $result = [];
    foreach ($langcodes as $langcode) {
      if (isset($this->configurableLanguageCache[$langcode]) && $this->configurableLanguageCache[$langcode] !== NULL) {
        $result[$langcode] = $this->configurableLanguageCache[$langcode];
      }
    }

    return $result;
  }

  /**
   * Get a single ConfigurableLanguage entity with caching.
   *
   * @param string $langcode
   *   The language code.
   *
   * @return \Drupal\language\Entity\ConfigurableLanguage|null
   *   The ConfigurableLanguage entity or NULL if not found.
   */
  public function getConfigurableLanguage(string $langcode) {
    if (empty($langcode)) {
      return NULL;
    }

    if (!isset($this->configurableLanguageCache[$langcode])) {
      $this->configurableLanguageCache[$langcode] = $this->entityTypeManager
        ->getStorage('configurable_language')
        ->load($langcode);
    }

    return $this->configurableLanguageCache[$langcode];
  }

  /**
   * Get a Group entity by ID with request-level caching.
   *
   * @param int|string $group_id
   *   The group ID.
   *
   * @return \Drupal\group\Entity\Group|null
   *   The Group entity or NULL if not found.
   */
  public function getGroupEntity($group_id) {
    if (empty($group_id)) {
      return NULL;
    }

    $group_id = (string) $group_id;

    if (!isset($this->groupCache[$group_id])) {
      $this->groupCache[$group_id] = $this->entityTypeManager
        ->getStorage('group')
        ->load($group_id);
    }

    return $this->groupCache[$group_id];
  }

  /**
   * Batch load Group entities with request-level caching.
   *
   * @param array $group_ids
   *   Array of group IDs.
   *
   * @return array
   *   Array of Group entities keyed by ID.
   */
  public function loadGroupsBatch(array $group_ids): array {
    if (empty($group_ids)) {
      return [];
    }

    $group_ids = array_filter(array_unique($group_ids));
    $to_load = array_diff($group_ids, array_keys($this->groupCache));

    if (!empty($to_load)) {
      $entities = $this->entityTypeManager
        ->getStorage('group')
        ->loadMultiple($to_load);
      foreach ($entities as $id => $entity) {
        $this->groupCache[$id] = $entity;
      }
      // Mark missing IDs as NULL.
      foreach ($to_load as $id) {
        if (!isset($this->groupCache[$id])) {
          $this->groupCache[$id] = NULL;
        }
      }
    }

    // Return requested entities (excluding NULLs).
    $result = [];
    foreach ($group_ids as $id) {
      if (isset($this->groupCache[$id]) && $this->groupCache[$id] !== NULL) {
        $result[$id] = $this->groupCache[$id];
      }
    }

    return $result;
  }

}
