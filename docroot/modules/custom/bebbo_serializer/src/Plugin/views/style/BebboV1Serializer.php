<?php

namespace Drupal\bebbo_serializer\Plugin\views\style;

use Drupal\bebbo_serializer\Cache\ApiCacheTags;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RendererInterface;
use Drupal\bebbo_serializer\Cache\RowFragmentCache;
use Drupal\group\Entity\Group;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\language_visibility_control\LanguageVisibilityService;
use Drupal\rest\Plugin\views\style\Serializer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Views style plugin for the V1 API performance port.
 *
 * Produces the same response envelope as the legacy custom_serialization
 * style, but reuses the V2 performance machinery (pre-computed fields, batch
 * media resolution, shared transform helpers). Unlike BebboSerializer (V2),
 * the output format is core "json" — NOT "bebbo_json" — so slashes and
 * unicode stay escaped, matching the byte-for-byte output the mobile app
 * already receives from the V1 endpoints.
 *
 * Response shape:
 * @code
 * {
 *   "status":   200,
 *   "total":    <count of view results>,
 *   "langcode": "<language from URL arg or current language>",
 *   "datetime": "<Y-m-d H:i>",
 *   "data":     [ ...rows transformed per display... ]
 * }
 * @endcode
 *
 * Per-display transformation is added incrementally as each V1 endpoint is
 * migrated (see docs/BEBBO_V1_API_AUDIT.md §9, Phases 1-7). Until a display
 * has a case in transformRows(), rows pass through unchanged.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "bebbo_v1_serializer",
 *   title = @Translation("Bebbo V1 serializer"),
 *   help = @Translation("Wraps V1 REST export data in the standard envelope using plain json (escaped slashes/unicode) for byte parity with the legacy V1 APIs."),
 *   display_types = {"data"}
 * )
 */
class BebboV1Serializer extends Serializer {

  use BebboSerializerHelpers;

  /**
   * Displays whose rows are 1:1 with a node and are safe to fragment-cache.
   */
  private const CACHEABLE_DISPLAYS = [
    'v1_articles_rest_export',
  ];

  /**
   * The current path stack.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected CurrentPathStack $currentPath;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

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
   * The language visibility service.
   *
   * @var \Drupal\language_visibility_control\LanguageVisibilityService
   */
  protected LanguageVisibilityService $languageVisibilityService;

  /**
   * The row fragment cache.
   *
   * @var \Drupal\bebbo_serializer\Cache\RowFragmentCache
   */
  protected RowFragmentCache $rowFragmentCache;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    SerializerInterface $serializer,
    array $serializer_formats,
    array $serializer_format_providers,
    CurrentPathStack $current_path,
    RequestStack $request_stack,
    LanguageManagerInterface $language_manager,
    EntityTypeManagerInterface $entity_type_manager,
    Connection $database,
    LanguageVisibilityService $language_visibility_service,
    RowFragmentCache $row_fragment_cache,
    FileUrlGeneratorInterface $file_url_generator,
    RendererInterface $renderer,
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $serializer,
      $serializer_formats,
      $serializer_format_providers,
    );
    $this->currentPath = $current_path;
    $this->requestStack = $request_stack;
    $this->languageManager = $language_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
    $this->languageVisibilityService = $language_visibility_service;
    $this->rowFragmentCache = $row_fragment_cache;
    $this->fileUrlGenerator = $file_url_generator;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('serializer'),
      $container->getParameter('serializer.formats'),
      $container->getParameter('serializer.format_providers'),
      $container->get('path.current'),
      $container->get('request_stack'),
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('language_visibility_control.service'),
      $container->get('bebbo_serializer.row_fragment_cache'),
      $container->get('file_url_generator'),
      $container->get('renderer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render(): string {
    // Generate timestamp once — used by both the results and no-results
    // code paths. Matches CustomSerializer's
    // getCurrentTimestamp('Asia/Kolkata').
    $timestamp = (new DrupalDateTime('now', 'Asia/Kolkata'))->format('Y-m-d H:i');

    // Validate language visibility (skip for country-groups listing).
    $displayId = $this->view->current_display;
    if ($displayId !== 'v1_country_listing_rest_export') {
      $error = $this->checkLanguageVisibility();
      if ($error) {
        return $this->serializer->serialize(
          $error + ['datetime' => $timestamp],
          'json',
          ['views_style_plugin' => $this],
        );
      }
    }

    // Collect rows via the parent row plugin, routing 1:1 node displays
    // through the fragment cache so only edited rows are re-rendered.
    $renderRow = function (int $rowIndex, object $row): mixed {
      $this->view->row_index = $rowIndex;
      return $this->normalizeMarkup($this->view->rowPlugin->render($row));
    };

    if (in_array($displayId, self::CACHEABLE_DISPLAYS, TRUE)) {
      $request  = $this->requestStack->getCurrentRequest();
      $host     = $request !== NULL ? $request->getSchemeAndHttpHost() : '';
      $langcode = $this->view->args[0] ?? $this->languageManager->getCurrentLanguage()->getId();
      $bubble   = new BubbleableMetadata();
      $rows     = $this->rowFragmentCache->render($displayId, $langcode, $host, $this->view->result, $renderRow, $bubble);
      // Scope the response to this listing in this language. node_list would
      // expire it on any node save anywhere — an FAQ edit taking every
      // article response with it — and the per-row entity tags are worse
      // still: acquia_purge emits every response tag as a CDN Surrogate-Key,
      // so hundreds of node:ID tags overflow the response header (Apache
      // "premature end of script headers" → HTTP 500) and flood the purge
      // queue. Per-row invalidation lives on the fragments instead, which is
      // what re-renders only the edited row. The payload references node and
      // media entities only — categories and keywords are emitted as IDs, so
      // term edits never change output — so the listing tags plus media_list
      // are complete.
      $bundles = $this->view->display_handler->getOption('filters')['type']['value'] ?? [];
      $this->view->element['#cache']['tags'] = Cache::mergeTags(
        $this->view->element['#cache']['tags'] ?? [],
        $bundles
          ? array_merge(ApiCacheTags::listTags(array_values($bundles), $langcode), ['media_list'])
          : ['node_list', 'media_list'],
      );
    }
    else {
      $rows = [];
      foreach ($this->view->result as $rowIndex => $row) {
        $rows[] = $renderRow($rowIndex, $row);
      }
    }
    unset($this->view->row_index);

    // Early return when there are no results — minimal envelope.
    if (empty($rows)) {
      return $this->serializer->serialize(
        [
          'status'   => 204,
          'message'  => 'No Records Found',
          'datetime' => $timestamp,
        ],
        'json',
        ['views_style_plugin' => $this],
      );
    }

    // Has results path — transform rows then build the envelope. Dispatch by
    // display ID so multiple displays under bebbo_v1_apis each get their own
    // transformation.
    $rows     = $this->transformRows($displayId, $rows);
    $langcode = $this->resolveLangcode();

    // Taxonomy endpoints return {status, langcode, datetime, data} — no total.
    if ($displayId === 'v1_vocabulary_rest_export' || $displayId === 'v1_terms_rest_export') {
      return $this->serializer->serialize(
        [
          'status'   => 200,
          'langcode' => $langcode,
          'datetime' => $timestamp,
          'data'     => $rows,
        ],
        'json',
        ['views_style_plugin' => $this],
      );
    }

    // Standard deviation returns {status, langcode, data} — no total/datetime.
    if ($displayId === 'v1_standard_deviation_rest_export') {
      return $this->serializer->serialize(
        [
          'status'   => 200,
          'langcode' => $langcode,
          'data'     => $rows,
        ],
        'json',
        ['views_style_plugin' => $this],
      );
    }

    // Displays whose transform de-duplicates rows: total must reflect the
    // final (deduped) row count, not the view's pre-dedup total_rows.
    $deduped_displays = [
      'v1_pinned_faq_rest_export',
      'v1_pinned_vaccinations_rest_export',
      'v1_pinned_health_checkup_rest_export',
      'v1_pinned_child_growth_rest_export',
      'v1_related_milestone_rest_export',
      'v1_updated_pinned_faq_rest_export',
      'v1_child_dev_boy_rest_export',
      'v1_child_dev_girl_rest_export',
      'v1_health_checkup_rest_export',
    ];

    // Archive returns a grouped structure — total is the sum of all IDs.
    if ($displayId === 'v1_archive_rest_export') {
      $total = array_sum(array_map('count', $rows));
    }
    elseif (in_array($displayId, $deduped_displays, TRUE)) {
      // Count the transformed (deduplicated) rows to match the legacy API.
      $total = count($rows);
    }
    else {
      // Use the view's own total_rows (set by the pager during query
      // execution) so the count respects every applied filter. Falls back to
      // counting transformed rows when total_rows is unavailable.
      $total = (int) ($this->view->total_rows ?? count($rows));
    }

    return $this->serializer->serialize(
      [
        'status'   => 200,
        'total'    => $total,
        'langcode' => $langcode,
        'datetime' => $timestamp,
        'data'     => $rows,
      ],
      'json',
      ['views_style_plugin' => $this],
    );
  }

  /**
   * Validates that the requested language is visible in at least one group.
   *
   * @return array|null
   *   Error array with 'status' and 'message' keys, or NULL if valid.
   */
  private function checkLanguageVisibility(): ?array {
    $requested_language = $this->view->args[0] ?? $this->languageManager->getCurrentLanguage()->getId();

    $languages = array_keys($this->languageManager->getLanguages());
    if (!in_array($requested_language, $languages)) {
      return ['status' => 400, 'message' => 'Request language is wrong'];
    }

    $groups = $this->entityTypeManager
      ->getStorage('group')
      ->loadByProperties(['type' => 'country']);

    foreach ($groups as $group) {
      $visible = $this->languageVisibilityService->getVisibleLanguages($group);
      if (in_array($requested_language, $visible)) {
        return NULL;
      }
    }

    return ['status' => 403, 'message' => 'Language not available'];
  }

  /**
   * Dispatches row transformation to a display-specific method.
   *
   * A case is added here for each V1 endpoint as it is migrated. Displays
   * without a case pass rows through unchanged.
   *
   * @param string $displayId
   *   The machine name of the active view display.
   * @param array $rows
   *   Raw rows from the view row plugin.
   *
   * @return array
   *   Transformed rows.
   */
  private function transformRows(string $displayId, array $rows): array {
    return match ($displayId) {
      'v1_articles_rest_export' => $this->transformArticles($rows),
      'v1_video_article_rest_export' => $this->transformVideoArticles($rows),
      'v1_faq_rest_export' => $this->transformFaq($rows),
      'v1_basic_page_rest_export' => $this->transformBasicPages($rows),
      'v1_activities_rest_export' => $this->transformActivities($rows),
      'v1_daily_homescreen_rest_export' => $this->transformDailyHomeScreenMessages($rows),
      'v1_milestones_rest_export' => $this->transformMilestones($rows),
      'v1_child_development_rest_export' => $this->transformChildDevelopment($rows),
      'v1_child_growth_rest_export' => $this->transformChildGrowth($rows),
      'v1_vaccinations_rest_export' => $this->transformVaccinations($rows),
      'v1_surveys_rest_export' => $this->transformSurveys($rows),
      'v1_child_dev_boy_rest_export' => $this->transformChildDevPinned($rows),
      'v1_child_dev_girl_rest_export' => $this->transformChildDevPinned($rows),
      'v1_health_checkup_rest_export' => $this->transformHealthCheckUps($rows),
      'v1_archive_rest_export' => $this->transformArchive($rows),
      'v1_pinned_faq_rest_export',
      'v1_pinned_vaccinations_rest_export',
      'v1_pinned_health_checkup_rest_export',
      'v1_pinned_child_growth_rest_export',
      'v1_related_milestone_rest_export',
      'v1_updated_pinned_faq_rest_export' => $this->transformPinnedContent($rows),
      'v1_standard_deviation_rest_export' => $this->transformStandardDeviation($rows),
      'v1_country_listing_rest_export' => $this->transformCountryGroups($rows),
      'v1_vocabulary_rest_export' => $this->transformVocabularies($rows),
      'v1_terms_rest_export' => $this->transformTaxonomies($rows),
      'v1_sponsors_rest_export' => $this->transformSponsors($rows),
      default => $rows,
    };
  }

  /**
   * Transforms rows for the V1 article REST export display.
   *
   * Mirrors the legacy V1 Article API output: casts numeric fields, converts
   * multi-value references to int arrays, normalizes embedded_images to a
   * string array, decodes HTML entities in the title, and assembles
   * cover_image from the view-provided {mid, url, name, alt} fields (with
   * WebP conversion). Unlike V2, it omits love_count/read_count/
   * target_audience and leaves do_not_feature as the raw string the legacy
   * API returned.
   *
   * @param array $rows
   *   Raw rows from the view row plugin.
   *
   * @return array
   *   Transformed rows.
   */
  private function transformArticles(array $rows): array {
    if (empty($rows)) {
      return $rows;
    }

    $request = $this->requestStack->getCurrentRequest();
    $emptyMedia = ['url' => '', 'name' => '', 'alt' => ''];

    foreach ($rows as &$row) {
      $this->castToInt($row, [
        'id', 'field_type_of_article', 'category', 'subcategory',
        'child_gender', 'parent_gender', 'premature',
      ]);
      $this->toIntArray($row, [
        'child_age', 'keywords', 'related_articles', 'related_video_articles',
      ]);
      $this->toStringArray($row, ['embedded_images']);
      $this->decodeHtmlEntities($row, ['title']);

      // Legacy V1 returned summary as plain text (all tags stripped) and an
      // empty meta_keywords as "" rather than a boolean false.
      $this->cleanSummary($row);
      $row['meta_keywords'] = (string) ($row['meta_keywords'] ?? '');

      $mid = (int) ($row['cover_image_mid'] ?? 0);
      $url = (string) ($row['cover_image_url'] ?? '');

      if ($url !== '') {
        $url = preg_replace('/\.(jpe?g|png)(\?.*)?$/i', '.webp$2', $url) ?? $url;
        if (!str_starts_with($url, 'http') && $request !== NULL) {
          $url = $request->getSchemeAndHttpHost() . $url;
        }
      }

      $row['cover_image'] = $mid > 0
        ? [
          'url' => $url,
          'name' => (string) ($row['cover_image_name'] ?? ''),
          'alt' => htmlspecialchars_decode((string) ($row['cover_image_alt'] ?? ''), ENT_QUOTES | ENT_HTML5),
        ]
        : $emptyMedia;

      unset($row['cover_image_mid'], $row['cover_image_url'], $row['cover_image_name'], $row['cover_image_alt']);
    }
    unset($row);

    return $rows;
  }

  /**
   * Transforms rows for the V1 video article display.
   *
   * @param array $rows
   *   Raw rows from the view.
   *
   * @return array
   *   Transformed rows.
   */
  private function transformVideoArticles(array $rows): array {
    if (empty($rows)) {
      return $rows;
    }

    foreach ($rows as &$row) {
      $this->castToInt($row, [
        'id', 'category', 'child_gender', 'parent_gender',
        'licensed', 'premature', 'mandatory',
      ]);
      $this->toIntArray($row, [
        'child_age', 'keywords', 'related_articles', 'related_video_articles',
      ]);
      $this->toStringArray($row, ['embedded_images']);
      $this->decodeHtmlEntities($row, ['title']);
      $this->cleanSummary($row);

      $row['cover_video'] = $this->parseViewVideoMedia($row['cover_video'] ?? NULL);
      $row['cover_image'] = $this->parseViewCoverImage($row['cover_image'] ?? NULL);
    }
    unset($row);

    return $rows;
  }

  /**
   * Transforms rows for the V1 FAQ display.
   *
   * @param array $rows
   *   Raw rows from the view.
   *
   * @return array
   *   Transformed rows.
   */
  private function transformFaq(array $rows): array {
    if (empty($rows)) {
      return $rows;
    }

    foreach ($rows as &$row) {
      $this->castToInt($row, ['id', 'chatbot_subcategory', 'related_article', 'mandatory']);
      $this->decodeHtmlEntities($row, ['question']);
      $this->toIntArray($row, ['child_age']);
    }
    unset($row);

    return $rows;
  }

  /**
   * Transforms rows for the V1 basic pages display.
   *
   * @param array $rows
   *   Raw rows from the view.
   *
   * @return array
   *   Transformed rows.
   */
  private function transformBasicPages(array $rows): array {
    if (empty($rows)) {
      return $rows;
    }

    $englishTitles = $this->getEnglishNodeTitles(array_column($rows, 'id'));

    foreach ($rows as &$row) {
      $this->castToInt($row, ['id', 'mandatory']);
      $this->toStringArray($row, ['embedded_images']);
      $this->decodeHtmlEntities($row, ['title']);

      $row['unique_name'] = !empty($englishTitles[$row['id']])
        ? str_replace(' ', '_', strtolower($englishTitles[$row['id']]))
        : '';
    }
    unset($row);

    return $rows;
  }

  /**
   * Transforms rows for the V1 activities display.
   *
   * @param array $rows
   *   Raw rows from the view.
   *
   * @return array
   *   Transformed rows.
   */
  private function transformActivities(array $rows): array {
    if (empty($rows)) {
      return $rows;
    }

    foreach ($rows as &$row) {
      $this->castToInt($row, [
        'id', 'activity_category', 'equipment', 'type_of_support', 'mandatory',
      ]);
      $this->toIntArray($row, ['child_age', 'related_milestone']);
      $this->toStringArray($row, ['embedded_images']);
      $this->decodeHtmlEntities($row, ['title']);
      $this->cleanSummary($row);

      $row['cover_image'] = $this->parseViewCoverImage($row['cover_image'] ?? NULL);
    }
    unset($row);

    return $rows;
  }

  /**
   * Transforms rows for the V1 daily home screen messages display.
   *
   * @param array $rows
   *   Raw rows from the view.
   *
   * @return array
   *   Transformed rows.
   */
  private function transformDailyHomeScreenMessages(array $rows): array {
    if (empty($rows)) {
      return $rows;
    }

    foreach ($rows as &$row) {
      $this->castToInt($row, ['id']);
      $this->decodeHtmlEntities($row, ['title']);
    }
    unset($row);

    return $rows;
  }

  /**
   * Transforms rows for the V1 milestones display.
   *
   * @param array $rows
   *   Raw rows from the view.
   *
   * @return array
   *   Transformed rows.
   */
  private function transformMilestones(array $rows): array {
    if (empty($rows)) {
      return $rows;
    }

    foreach ($rows as &$row) {
      $this->castToInt($row, ['id', 'mandatory']);
      $this->toIntArray($row, [
        'child_age',
        'related_activities',
        'related_articles',
        'related_video_articles',
      ]);
      $this->decodeHtmlEntities($row, ['title']);
    }
    unset($row);

    return $rows;
  }

  /**
   * Transforms rows for the V1 child development data display.
   *
   * @param array $rows
   *   Raw rows from the view.
   *
   * @return array
   *   Transformed rows.
   */
  private function transformChildDevelopment(array $rows): array {
    if (empty($rows)) {
      return $rows;
    }

    foreach ($rows as &$row) {
      $this->castToInt($row, [
        'id', 'boy_video_article', 'girl_video_article', 'mandatory',
      ]);
      $this->toIntArray($row, ['child_age']);
      $this->decodeHtmlEntities($row, ['title']);
    }
    unset($row);

    return $rows;
  }

  /**
   * Transforms rows for the V1 child growth display.
   *
   * @param array $rows
   *   Raw rows from the view.
   *
   * @return array
   *   Transformed rows.
   */
  private function transformChildGrowth(array $rows): array {
    if (empty($rows)) {
      return $rows;
    }

    foreach ($rows as &$row) {
      $this->castToInt($row, ['id', 'growth_type', 'standard_deviation', 'mandatory']);
      $this->toIntArray($row, ['child_age', 'pinned_articles']);
    }
    unset($row);

    return $rows;
  }

  /**
   * Transforms rows for the V1 vaccinations display.
   *
   * @param array $rows
   *   Raw rows from the view.
   *
   * @return array
   *   Transformed rows.
   */
  private function transformVaccinations(array $rows): array {
    if (empty($rows)) {
      return $rows;
    }

    foreach ($rows as &$row) {
      $this->castToInt($row, ['id', 'growth_period', 'pinned_video_article', 'old_calendar', 'pinned_article']);
      $this->toIntArray($row, ['related_articles']);
      $this->decodeHtmlEntities($row, ['title']);
    }
    unset($row);

    // old_calendar is a translatable field; read it from the entity
    // translation so the response reflects the requested language's toggle.
    $this->overrideIntFromTranslation($rows, 'field_old_calendar', 'old_calendar');

    return $rows;
  }

  /**
   * Transforms rows for the V1 surveys display.
   *
   * @param array $rows
   *   Raw rows from the view.
   *
   * @return array
   *   Transformed rows.
   */
  private function transformSurveys(array $rows): array {
    if (empty($rows)) {
      return $rows;
    }

    foreach ($rows as &$row) {
      $this->castToInt($row, ['id']);
      $this->decodeHtmlEntities($row, ['title']);
    }
    unset($row);

    return $rows;
  }

  /**
   * Transforms rows for the V1 child development pinned-content displays.
   *
   * Used by both boy and girl displays. Deduplicates by article ID,
   * resolves cover media, and applies type-specific field handling.
   *
   * @param array $rows
   *   Raw rows from the view.
   *
   * @return array
   *   Transformed rows.
   */
  private function transformChildDevPinned(array $rows): array {
    if (empty($rows)) {
      return $rows;
    }

    $seen = [];
    $rows = array_values(array_filter($rows, function ($row) use (&$seen) {
      $id = $row['id'] ?? NULL;
      if ($id === NULL || isset($seen[$id])) {
        return FALSE;
      }
      $seen[$id] = TRUE;
      return TRUE;
    }));

    // embedded_images is not a view field on these displays, so batch-load it
    // from the pinned article nodes (the row id is the pinned article nid).
    $embeddedByNid = $this->resolveEmbeddedImagesByNid(array_column($rows, 'id'), $this->resolveLangcode());

    foreach ($rows as &$row) {
      $this->castToInt($row, [
        'id', 'category', 'child_gender', 'parent_gender',
        'licensed', 'premature', 'mandatory',
        'boy_video_article', 'girl_video_article',
      ]);
      $this->toIntArray($row, ['child_age', 'keywords', 'related_articles']);
      $this->decodeHtmlEntities($row, ['title']);

      $row['embedded_images'] = $embeddedByNid[(int) ($row['id'] ?? 0)] ?? [];

      $coverVideo = $this->parseViewVideoMedia($row['cover_video'] ?? NULL);
      $coverImage = $this->parseViewCoverImage($row['cover_image'] ?? NULL);

      $type = $row['type'] ?? '';
      if ($type === 'Video Article') {
        $row['cover_video'] = $coverVideo;
        $row['cover_image'] = $coverImage;
      }
    }
    unset($row);

    return $rows;
  }

  /**
   * Transforms rows for the V1 health check-ups display.
   *
   * Deduplicates by article ID and applies type-specific field handling
   * for Article vs Video Article pinned content.
   *
   * @param array $rows
   *   Raw rows from the view.
   *
   * @return array
   *   Transformed rows.
   */
  private function transformHealthCheckUps(array $rows): array {
    if (empty($rows)) {
      return $rows;
    }

    $seen = [];
    $rows = array_values(array_filter($rows, function ($row) use (&$seen) {
      $id = $row['id'] ?? NULL;
      if ($id === NULL || isset($seen[$id])) {
        return FALSE;
      }
      $seen[$id] = TRUE;
      return TRUE;
    }));

    foreach ($rows as &$row) {
      $this->castToInt($row, [
        'id', 'category', 'child_gender', 'parent_gender',
        'licensed', 'premature', 'mandatory',
        'growth_period', 'pinned_article', 'pinned_video_article',
      ]);
      $this->toIntArray($row, [
        'child_age', 'related_articles', 'related_video_articles',
      ]);
      $this->decodeHtmlEntities($row, ['title']);

      $type = $row['type'] ?? '';
      if ($type === 'Article') {
        unset($row['cover_video'], $row['cover_video_image']);
      }
      elseif ($type === 'Video Article') {
        $row['cover_image'] = $row['cover_video_image'] ?? NULL;
        unset($row['cover_video_image']);
      }
    }
    unset($row);

    return $rows;
  }

  /**
   * Transforms rows for the V1 archive display.
   *
   * Groups rows by content type, returning {type: [nid, ...]} instead
   * of a flat row array.
   *
   * @param array $rows
   *   Raw rows from the view.
   *
   * @return array
   *   Grouped array: {type: [nid, ...]}.
   */
  private function transformArchive(array $rows): array {
    $grouped = [];
    foreach ($rows as $row) {
      $type = (string) ($row['type'] ?? '');
      $nid  = (int) ($row['nid'] ?? 0);
      if ($type !== '' && $nid > 0) {
        $grouped[$type][] = $nid;
      }
    }
    return $grouped;
  }

  /**
   * Transforms rows for pinned-content, related-article, and updated-pinned.
   *
   * These displays share a generic article-like field set fetched through a
   * relationship (field_pinned_article or field_related_articles). The
   * transform deduplicates by ID, casts types, and resolves embedded media.
   *
   * @param array $rows
   *   Raw rows from the view.
   *
   * @return array
   *   Transformed rows.
   */
  private function transformPinnedContent(array $rows): array {
    if (empty($rows)) {
      return $rows;
    }

    $seen = [];
    $rows = array_values(array_filter($rows, function ($row) use (&$seen) {
      $id = $row['id'] ?? NULL;
      if ($id === NULL || isset($seen[$id])) {
        return FALSE;
      }
      $seen[$id] = TRUE;
      return TRUE;
    }));

    // The pinned displays expose cover media as raw media IDs (through the
    // field_pinned_article relationship), so batch-resolve those IDs to
    // {url, name, alt} (with WebP) instead of parsing embedded view HTML.
    // embedded_images is not a view field here, so batch-load it from the
    // pinned article nodes (the row id is the pinned article nid).
    $imageMediaIds = [];
    $nids = [];
    foreach ($rows as $row) {
      if (!empty($row['id']) && is_numeric($row['id'])) {
        $nids[] = (int) $row['id'];
      }
      foreach (['cover_image', 'cover_video_image'] as $key) {
        if (!empty($row[$key]) && is_numeric($row[$key])) {
          $imageMediaIds[] = (int) $row[$key];
        }
      }
    }
    $resolvedMedia = $this->resolveMediaIds($imageMediaIds);
    $embeddedByNid = $this->resolveEmbeddedImagesByNid($nids, $this->resolveLangcode());
    $emptyMedia = ['url' => '', 'name' => '', 'alt' => ''];

    foreach ($rows as &$row) {
      $this->castToInt($row, [
        'id', 'category', 'child_gender', 'parent_gender',
        'licensed', 'premature', 'mandatory',
      ]);
      $this->toIntArray($row, [
        'child_age', 'keywords', 'related_articles', 'related_video_articles',
      ]);
      $this->decodeHtmlEntities($row, ['title']);
      $this->cleanSummary($row);

      $row['embedded_images'] = $embeddedByNid[(int) ($row['id'] ?? 0)] ?? [];

      // Type-specific cover media, matching the legacy API: Article pins
      // expose only cover_image; Video Article pins only the video fields.
      if (($row['type'] ?? '') === 'Video Article') {
        if (array_key_exists('cover_video', $row)) {
          $row['cover_video'] = $this->parseViewVideoMedia($row['cover_video'] ?? NULL);
        }
        if (array_key_exists('cover_video_image', $row)) {
          $mid = (int) ($row['cover_video_image'] ?? 0);
          $row['cover_video_image'] = $resolvedMedia[$mid] ?? $emptyMedia;
        }
        unset($row['cover_image']);
      }
      else {
        if (array_key_exists('cover_image', $row)) {
          $mid = (int) ($row['cover_image'] ?? 0);
          $row['cover_image'] = $resolvedMedia[$mid] ?? $emptyMedia;
        }
        unset($row['cover_video'], $row['cover_video_image']);
      }
    }
    unset($row);

    return $rows;
  }

  /**
   * Transforms rows for the V1 standard deviation display.
   *
   * Groups child_growth nodes by growth type, buckets them by child age
   * ranges, and maps SD labels to the nested output structure expected by
   * the mobile app.
   *
   * @param array $rows
   *   Raw rows from the view.
   *
   * @return array
   *   Nested structure: {growth_type: [{child_age, goodText, ...}, ...]}.
   */
  private function transformStandardDeviation(array $rows): array {
    if (empty($rows)) {
      return $rows;
    }

    $termIds = [];
    $sdTermIds = [];
    foreach ($rows as $row) {
      if (!empty($row['growth_type']) && is_numeric($row['growth_type'])) {
        $termIds[] = (int) $row['growth_type'];
      }
      foreach ($this->splitNumericIds($row['child_age'] ?? '') as $id) {
        $termIds[] = $id;
      }
      if (!empty($row['standard_deviation']) && is_numeric($row['standard_deviation'])) {
        $sdTermIds[] = (int) $row['standard_deviation'];
      }
    }
    $termNameMap = $this->resolveTermUniqueNames(array_unique($termIds));
    $sdNameMap = $this->resolveTermNames(array_unique($sdTermIds));

    $sdLabelMaps = [
      'height_for_age' => [
        'between -2SD to +3SD' => 'goodText',
        'below -2SD'           => 'warrningSmallLengthText',
        'below -3SD'           => 'emergencySmallLengthText',
        'above +3SD'           => 'warrningBigLengthText',
      ],
      'height_for_weight' => [
        'between -2SD to +2SD' => 'goodText',
        'between -2 and -3SD'  => 'warrningSmallHeightText',
        'below -3SD'           => 'emergencySmallHeightText',
        'between +2 and +3SD'  => 'warrningBigHeightText',
        'above +3SD'           => 'emergencyBigHeightText',
      ],
    ];

    $buckets = [
      '1st_month,2nd_month,3_4_months,5_6_months',
      '7_9_months',
      '10_12_months',
      '13_18_months,19_24_months',
      '25_36_months,37_48_months,49_60_months,61_72_months',
    ];

    $itemsByGrowth = [
      'height_for_age'    => [],
      'height_for_weight' => [],
    ];

    foreach ($rows as $row) {
      $growthTid = (int) ($row['growth_type'] ?? 0);
      $growth = $termNameMap[$growthTid] ?? NULL;
      if (!$growth || !isset($sdLabelMaps[$growth])) {
        continue;
      }

      $ageIds = $this->splitNumericIds($row['child_age'] ?? '');
      sort($ageIds, SORT_NUMERIC);
      $ageNames = array_filter(array_map(
        fn($id) => $termNameMap[$id] ?? NULL,
        $ageIds
      ));
      $bucketKey = implode(',', $ageNames);

      $itemsByGrowth[$growth][] = [
        'child_age'      => $row['child_age'] ?? '',
        'pinned_article' => $row['pinned_article'] ?? '',
        'title'          => htmlspecialchars_decode((string) ($row['title'] ?? ''), ENT_QUOTES | ENT_HTML5),
        'body'           => str_replace(["\r", "\n"], '', (string) ($row['body'] ?? '')),
        'sd_label'       => $sdNameMap[(int) ($row['standard_deviation'] ?? 0)] ?? '',
        'bucket_key'     => $bucketKey,
      ];
    }

    $final = [];
    foreach (['height_for_weight', 'height_for_age'] as $growth) {
      $items = $itemsByGrowth[$growth] ?? [];
      if (empty($items)) {
        continue;
      }

      $grouped = array_fill_keys($buckets, []);
      foreach ($items as $item) {
        if (!empty($item['bucket_key']) && isset($grouped[$item['bucket_key']])) {
          $grouped[$item['bucket_key']][] = $item;
        }
      }

      $sdArr = [];
      foreach ($grouped as $groupItems) {
        if (empty($groupItems)) {
          continue;
        }

        $childAgeIds = $this->splitNumericIds($groupItems[0]['child_age']);
        $sdData = [
          'child_age' => array_values(array_filter(array_map('intval', $childAgeIds))),
        ];

        $tmpFields = [];
        foreach ($groupItems as $gi) {
          $fieldKey = $sdLabelMaps[$growth][$gi['sd_label']] ?? NULL;
          if (!$fieldKey) {
            continue;
          }
          $pinned = $this->splitNumericIds($gi['pinned_article']);
          $tmpFields[$fieldKey] = [
            'articleID' => array_values(array_filter(array_map('intval', $pinned))),
            'name'      => $gi['title'],
            'text'      => $gi['body'],
          ];
        }

        $order = $growth === 'height_for_age'
          ? ['goodText', 'warrningSmallLengthText', 'emergencySmallLengthText', 'warrningBigLengthText']
          : [
            'goodText', 'warrningSmallHeightText', 'emergencySmallHeightText',
            'warrningBigHeightText', 'emergencyBigHeightText',
          ];

        foreach ($order as $key) {
          if (isset($tmpFields[$key])) {
            $sdData[$key] = $tmpFields[$key];
          }
        }
        $sdArr[] = $sdData;
      }

      if (!empty($sdArr)) {
        $outputKey = $growth === 'height_for_weight' ? 'weight_for_height' : 'height_for_age';
        $final[$outputKey] = $sdArr;
      }
    }

    return $final;
  }

  /**
   * Splits a comma-separated string of numeric IDs into an int array.
   *
   * @param string $value
   *   Comma-separated IDs or a single ID.
   *
   * @return int[]
   *   Array of integer IDs.
   */
  private function splitNumericIds(string $value): array {
    if ($value === '') {
      return [];
    }
    return array_map('intval', array_filter(
      array_map('trim', explode(',', $value)),
      'is_numeric'
    ));
  }

  /**
   * Resolves taxonomy term IDs to their field_unique_name values.
   *
   * @param int[] $termIds
   *   Term IDs to resolve.
   *
   * @return array<int, string>
   *   Map of term ID to field_unique_name value.
   */
  private function resolveTermUniqueNames(array $termIds): array {
    if (empty($termIds)) {
      return [];
    }
    return $this->database->select('taxonomy_term__field_unique_name', 't')
      ->fields('t', ['entity_id', 'field_unique_name_value'])
      ->condition('entity_id', $termIds, 'IN')
      ->execute()
      ->fetchAllKeyed();
  }

  /**
   * Resolves taxonomy term IDs to their display names.
   *
   * @param int[] $termIds
   *   Term IDs to resolve.
   *
   * @return array<int, string>
   *   Map of term ID to term name.
   */
  private function resolveTermNames(array $termIds): array {
    if (empty($termIds)) {
      return [];
    }
    return $this->database->select('taxonomy_term_field_data', 't')
      ->fields('t', ['tid', 'name'])
      ->condition('tid', $termIds, 'IN')
      ->condition('default_langcode', 1)
      ->execute()
      ->fetchAllKeyed();
  }

  /**
   * Strips tags and decodes entities in summary (legacy V1 plain text).
   *
   * @param array $row
   *   A single row (passed by reference).
   */
  private function cleanSummary(array &$row): void {
    if (array_key_exists('summary', $row)) {
      $row['summary'] = html_entity_decode(
        trim(strip_tags((string) $row['summary'])),
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
      );
    }
  }

  /**
   * Transforms rows for the V1 country-groups listing display.
   *
   * Filters out CountryID 131, deduplicates, resolves media fields, builds
   * languages array from Group entities and custom_language_data, and moves
   * CountryID 126 to the end.
   *
   * @param array $rows
   *   Raw rows from the view.
   *
   * @return array
   *   Transformed rows.
   */
  private function transformCountryGroups(array $rows): array {
    if (empty($rows)) {
      return $rows;
    }

    $rows = array_values(array_filter($rows, fn($row) => ($row['CountryID'] ?? '') !== '131'));

    $seen = [];
    $rows = array_values(array_filter($rows, function ($row) use (&$seen) {
      $id = $row['CountryID'] ?? '';
      if (isset($seen[$id])) {
        return FALSE;
      }
      $seen[$id] = TRUE;
      return TRUE;
    }));

    $mediaKeys = ['country_national_partner', 'country_sponsor_logo', 'unicef_logo'];
    foreach ($rows as &$row) {
      foreach ($mediaKeys as $key) {
        $row[$key] = $this->parseViewCoverImage($row[$key] ?? NULL);
      }
    }
    unset($row);

    $groupIds = array_filter(array_column($rows, 'CountryID'));
    $groupStorage = $this->entityTypeManager->getStorage('group');
    $groups = $groupStorage->loadMultiple($groupIds);

    $entry126Index = NULL;
    foreach ($rows as $index => &$row) {
      $countryId = $row['CountryID'] ?? '';
      $group = $groups[$countryId] ?? NULL;

      if ($countryId === '126') {
        $row['name'] = 'Rest of the world';
        $row['displayName'] = 'Rest of the world';
        $row['languages'] = $this->buildLanguagesForRestOfWorld();
        $entry126Index = $index;
      }
      elseif ($group instanceof Group) {
        $row['languages'] = $this->buildLanguagesForGroup($row['name'] ?? '', $group);
        $defaultToggle = $group->getUntranslated()->get('field_content_toggle')->getValue();
        $row['content_toggle'] = implode(', ', array_column($defaultToggle, 'value'));
      }
      else {
        $row['languages'] = [];
      }

      unset($row['langcode']);
    }
    unset($row);

    if ($entry126Index !== NULL) {
      $entry = array_splice($rows, $entry126Index, 1);
      $rows[] = $entry[0];
    }

    return $rows;
  }

  /**
   * Builds languages array for a regular country group.
   *
   * @param string $countryName
   *   The country display name.
   * @param \Drupal\group\Entity\Group $group
   *   The group entity.
   *
   * @return array
   *   Array of language objects.
   */
  private function buildLanguagesForGroup(string $countryName, Group $group): array {
    $fieldLanguages = $group->get('field_language')->getValue();
    $langcodes = array_filter(array_column($fieldLanguages, 'value'));

    if (empty($langcodes)) {
      return [];
    }

    $langData = $this->database->select('custom_language_data', 'cld')
      ->fields('cld')
      ->condition('langcode', $langcodes, 'IN')
      ->execute()
      ->fetchAllAssoc('langcode', \PDO::FETCH_ASSOC);

    $configLanguages = $this->entityTypeManager
      ->getStorage('configurable_language')
      ->loadMultiple($langcodes);

    $languages = [];
    foreach ($langcodes as $langcode) {
      if (!$this->languageManager->getLanguage($langcode)) {
        continue;
      }
      $configLang = $configLanguages[$langcode] ?? NULL;
      if (!$configLang) {
        continue;
      }

      $data = $langData[$langcode] ?? [];

      if ($group->hasTranslation($langcode)) {
        $toggleValues = $group->getTranslation($langcode)->get('field_content_toggle')->getValue();
        $contentToggle = implode(', ', array_column($toggleValues, 'value'));
      }
      else {
        $contentToggle = '';
      }

      $languages[] = [
        'name' => $countryName,
        'displayName' => $data['custom_language_name_local'] ?? '',
        'languageCode' => $langcode,
        'locale' => $data['custom_locale'] ?? '',
        'luxonLocale' => $data['custom_luxon'] ?? '',
        'pluralShow' => $data['custom_plural'] ?? '',
        'content_toggle' => $contentToggle,
        'view_weight' => $configLang->get('weight') ?? 0,
      ];
    }

    $languages = $this->languageVisibilityService->filterLanguageDataForApi($languages, $group);

    usort($languages, fn($a, $b) => ($a['view_weight'] ?? 0) <=> ($b['view_weight'] ?? 0));
    foreach ($languages as &$lang) {
      unset($lang['view_weight']);
    }
    unset($lang);

    return $languages;
  }

  /**
   * Builds languages array for CountryID 126 (Rest of the World).
   *
   * @return array
   *   Array of language objects for en and ru.
   */
  private function buildLanguagesForRestOfWorld(): array {
    $langcodes = ['en', 'ru'];
    $langData = $this->database->select('custom_language_data', 'cld')
      ->fields('cld')
      ->condition('langcode', $langcodes, 'IN')
      ->execute()
      ->fetchAllAssoc('langcode', \PDO::FETCH_ASSOC);

    $configLanguages = $this->entityTypeManager
      ->getStorage('configurable_language')
      ->loadMultiple($langcodes);

    $languages = [];
    foreach ($langcodes as $langcode) {
      $configLang = $configLanguages[$langcode] ?? NULL;
      $displayName = $configLang ? $configLang->label() : '';
      $data = $langData[$langcode] ?? [];

      $name = ($langcode === 'en') ? 'English' : 'Russian';
      $languages[] = [
        'name' => $name,
        'displayName' => $displayName,
        'languageCode' => $langcode,
        'locale' => $data['custom_locale'] ?? '',
        'luxonLocale' => $data['custom_luxon'] ?? '',
        'pluralShow' => $data['custom_plural'] ?? '',
      ];
    }

    return $languages;
  }

  /**
   * Transforms vocabulary list rows into a keyed object.
   *
   * @param array $rows
   *   Raw rows from the view.
   *
   * @return array
   *   Keyed object: {machine_name: {name: "Label"}, ...}.
   */
  private function transformVocabularies(array $rows): array {
    $langcode = $this->resolveLangcode();
    $vocabStorage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');
    $result = [];

    foreach ($this->view->result as $row) {
      $term = $row->_entity ?? NULL;
      if ($term === NULL) {
        continue;
      }
      $machineName = $term->bundle();
      if ($machineName === '' || $machineName === 'keywords' || isset($result[$machineName])) {
        continue;
      }

      $label = NULL;
      if ($this->languageManager instanceof ConfigurableLanguageManagerInterface) {
        $label = $this->languageManager
          ->getLanguageConfigOverride($langcode, 'taxonomy.vocabulary.' . $machineName)
          ->get('name');
      }
      if (!$label) {
        $vocab = $vocabStorage->load($machineName);
        $label = $vocab !== NULL ? $vocab->label() : $machineName;
      }

      $result[$machineName] = [
        'name' => htmlspecialchars_decode((string) $label, ENT_QUOTES | ENT_HTML5),
      ];
    }

    // Return vocabularies in alphabetical order by machine name.
    ksort($result);

    return $result;
  }

  /**
   * Transforms taxonomy term rows for one or all vocabularies.
   *
   * @param array $rows
   *   Raw rows from the view (one row per vocabulary).
   *
   * @return array
   *   Keyed by vocabulary machine name: {vocab: [terms]}.
   */
  private function transformTaxonomies(array $rows): array {
    if (empty($rows)) {
      return $rows;
    }

    $langcode = $this->resolveLangcode();

    $specialtyVocabs = [
      'growth_period',
      'child_age',
      'growth_introductory',
      'chatbot_subcategory',
      'category',
    ];
    $uniqueNameVocabs = [
      'growth_type',
      'activity_category',
      'child_gender',
      'parent_gender',
      'relationship_to_parent',
      'chatbot_category',
      'subcategory',
      'target_audience',
      'course_category',
    ];

    $toSpecialty = [];
    $toUniqueName = [];
    $toBasic = [];

    // Derive vocabulary machine names from the view's result rows via the vid
    // field handler rather than the rendered "vid" row output. The display's
    // vid field uses an alter_text template ({{ vid__target_id }},{{ vid }})
    // whose tokens resolve under Drupal 10 but return an empty string under
    // Drupal 11, which would otherwise skip every vocabulary here. getValue()
    // reads the raw bundle value directly and is token-independent.
    $vidField = $this->view->field['vid'] ?? NULL;
    $seen = [];
    foreach ($this->view->result as $resultRow) {
      $vocabMachine = $vidField !== NULL
        ? trim((string) $vidField->getValue($resultRow))
        : '';
      if ($vocabMachine === '' || $vocabMachine === 'keywords' || isset($seen[$vocabMachine])) {
        continue;
      }
      $seen[$vocabMachine] = TRUE;

      if (in_array($vocabMachine, $specialtyVocabs, TRUE)) {
        $toSpecialty[] = $vocabMachine;
      }
      elseif (in_array($vocabMachine, $uniqueNameVocabs, TRUE)) {
        $toUniqueName[] = $vocabMachine;
      }
      else {
        $toBasic[] = $vocabMachine;
      }
    }

    $result = [];

    foreach ($toSpecialty as $vocabMachine) {
      $result[$vocabMachine] = match ($vocabMachine) {
        'growth_period'       => $this->queryGrowthPeriodTerms($langcode),
        'child_age'           => $this->queryChildAgeTerms($langcode),
        'growth_introductory' => $this->queryGrowthIntroductoryTerms($langcode),
        'chatbot_subcategory' => $this->queryChatbotSubcategoryTerms($langcode),
        'category'            => $this->queryCategoryTerms($langcode),
      };
    }

    if (!empty($toUniqueName)) {
      foreach ($this->queryUniqueNameTermsBatch($toUniqueName, $langcode) as $vid => $terms) {
        $result[$vid] = $terms;
      }
    }

    if (!empty($toBasic)) {
      foreach ($this->queryBasicTermsBatch($toBasic, $langcode) as $vid => $terms) {
        $result[$vid] = $terms;
      }
    }

    // Return vocabularies in alphabetical order by machine name.
    ksort($result);

    return $result;
  }

  /**
   * Builds the base taxonomy term query for a vocabulary and language.
   *
   * @param string $vocabMachine
   *   The vocabulary machine name.
   * @param string $langcode
   *   The language code.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   The base select query.
   */
  private function buildTermBaseQuery(string $vocabMachine, string $langcode): SelectInterface {
    $query = $this->database->select('taxonomy_term_field_data', 'td');
    $query->condition('td.vid', $vocabMachine);
    $query->condition('td.langcode', $langcode);
    $query->condition('td.status', 1);
    $query->fields('td', ['tid', 'name']);
    return $query;
  }

  /**
   * Queries growth_period vocabulary terms.
   *
   * @param string $langcode
   *   The language code.
   *
   * @return array
   *   Array of term data.
   */
  private function queryGrowthPeriodTerms(string $langcode): array {
    $query = $this->buildTermBaseQuery('growth_period', $langcode);
    $query->leftJoin('taxonomy_term__field_vaccination_opens', 'vo', 'vo.entity_id = td.tid');
    $query->addField('vo', 'field_vaccination_opens_value', 'vaccination_opens');

    $results = $query->execute()->fetchAll();
    $terms = [];
    foreach ($results as $row) {
      $terms[] = [
        'id' => (int) $row->tid,
        'name' => $row->name,
        'vaccination_opens' => (int) ($row->vaccination_opens ?? 0),
      ];
    }

    return $terms;
  }

  /**
   * Queries child_age vocabulary terms.
   *
   * @param string $langcode
   *   The language code.
   *
   * @return array
   *   Array of term data.
   */
  private function queryChildAgeTerms(string $langcode): array {
    $query = $this->buildTermBaseQuery('child_age', $langcode);
    $query->leftJoin('taxonomy_term__field_days_from', 'df', 'df.entity_id = td.tid');
    $query->leftJoin('taxonomy_term__field_days_to', 'dt', 'dt.entity_id = td.tid');
    $query->leftJoin('taxonomy_term__field_buffers_days', 'bd', 'bd.entity_id = td.tid');
    $query->addField('df', 'field_days_from_value', 'days_from');
    $query->addField('dt', 'field_days_to_value', 'days_to');
    $query->addField('bd', 'field_buffers_days_value', 'buffers_days');
    $query->addField('td', 'weight', 'weight');
    $query->orderBy('td.weight', 'ASC');

    $request = $this->requestStack->getCurrentRequest();
    $pregnancyParam = $request ? $request->query->get('pregnancy') : NULL;
    if ($pregnancyParam !== 'true') {
      $query->condition('td.name', 'Pregnancy', '<>');
    }

    $results = $query->execute()->fetchAll();
    $tids = array_column($results, 'tid');

    $ageBracketMap = [];
    if (!empty($tids)) {
      $abQuery = $this->database->select('taxonomy_term__field_age_bracket', 'ab');
      $abQuery->fields('ab', ['entity_id', 'field_age_bracket_target_id']);
      $abQuery->condition('ab.entity_id', $tids, 'IN');
      $abQuery->orderBy('ab.delta', 'ASC');
      $abResults = $abQuery->execute()->fetchAll();
      foreach ($abResults as $abRow) {
        $ageBracketMap[(int) $abRow->entity_id][] = (int) $abRow->field_age_bracket_target_id;
      }
    }

    $terms = [];
    foreach ($results as $row) {
      $tid = (int) $row->tid;
      $terms[] = [
        'id' => $tid,
        'name' => $row->name,
        'days_from' => (int) ($row->days_from ?? 0),
        'days_to' => (int) ($row->days_to ?? 0),
        'buffers_days' => (int) ($row->buffers_days ?? 0),
        'age_bracket' => $ageBracketMap[$tid] ?? [],
      ];
    }
    return $terms;
  }

  /**
   * Queries growth_introductory vocabulary terms.
   *
   * @param string $langcode
   *   The language code.
   *
   * @return array
   *   Array of term data.
   */
  private function queryGrowthIntroductoryTerms(string $langcode): array {
    $query = $this->buildTermBaseQuery('growth_introductory', $langcode);
    $query->addField('td', 'description__value', 'body');
    $query->leftJoin('taxonomy_term__field_days_from', 'df', 'df.entity_id = td.tid');
    $query->leftJoin('taxonomy_term__field_days_to', 'dt', 'dt.entity_id = td.tid');
    $query->addField('df', 'field_days_from_value', 'days_from');
    $query->addField('dt', 'field_days_to_value', 'days_to');

    $results = $query->execute()->fetchAll();
    $terms = [];
    foreach ($results as $row) {
      $terms[] = [
        'id' => (int) $row->tid,
        'name' => $row->name,
        'body' => $row->body ?? '',
        'days_from' => (int) ($row->days_from ?? 0),
        'days_to' => (int) ($row->days_to ?? 0),
      ];
    }
    return $terms;
  }

  /**
   * Queries chatbot_subcategory vocabulary terms.
   *
   * @param string $langcode
   *   The language code.
   *
   * @return array
   *   Array of term data.
   */
  private function queryChatbotSubcategoryTerms(string $langcode): array {
    $query = $this->buildTermBaseQuery('chatbot_subcategory', $langcode);
    $query->leftJoin('taxonomy_term__field_chatbot_category', 'cc', 'cc.entity_id = td.tid');
    $query->leftJoin('taxonomy_term__field_unique_name', 'un', "un.entity_id = td.tid AND un.langcode = 'en'");
    $query->addField('cc', 'field_chatbot_category_target_id', 'parent_category_id');
    $query->addField('un', 'field_unique_name_value', 'unique_name');

    $results = $query->execute()->fetchAll();
    $terms = [];
    $needsMachineName = [];
    foreach ($results as $idx => $row) {
      $terms[] = [
        'id' => (int) $row->tid,
        'name' => $row->name,
        'parent_category_id' => (int) ($row->parent_category_id ?? 0),
        'unique_name' => $row->unique_name ?? '',
      ];
      if (empty($row->unique_name)) {
        $needsMachineName[$idx] = (int) $row->tid;
      }
    }

    $this->batchResolveMachineNames($terms, $needsMachineName);

    return $terms;
  }

  /**
   * Queries category vocabulary terms.
   *
   * @param string $langcode
   *   The language code.
   *
   * @return array
   *   Array of term data.
   */
  private function queryCategoryTerms(string $langcode): array {
    $query = $this->buildTermBaseQuery('category', $langcode);
    $query->leftJoin('taxonomy_term__field_unique_name', 'un', "un.entity_id = td.tid AND un.langcode = 'en'");
    $query->leftJoin('taxonomy_term__field_type_of_article', 'toa', 'toa.entity_id = td.tid');
    $query->leftJoin('taxonomy_term_field_data', 'toa_td_lang', "toa_td_lang.tid = toa.field_type_of_article_target_id AND toa_td_lang.langcode = td.langcode");
    $query->leftJoin('taxonomy_term_field_data', 'toa_td_en', "toa_td_en.tid = toa.field_type_of_article_target_id AND toa_td_en.langcode = 'en'");
    $query->addField('un', 'field_unique_name_value', 'unique_name');
    $query->addExpression("COALESCE(toa_td_lang.name, toa_td_en.name)", 'type_of_article');

    $results = $query->execute()->fetchAll();
    $terms = [];
    $needsMachineName = [];
    foreach ($results as $idx => $row) {
      $terms[] = [
        'id' => (int) $row->tid,
        'name' => $row->name,
        'unique_name' => $row->unique_name ?? '',
        'field_type_of_article' => $row->type_of_article ?? '',
      ];
      if (empty($row->unique_name)) {
        $needsMachineName[$idx] = (int) $row->tid;
      }
    }

    $this->batchResolveMachineNames($terms, $needsMachineName);

    return $terms;
  }

  /**
   * Queries multiple unique-name vocabularies in a single DB round-trip.
   *
   * @param array $vocabs
   *   List of vocabulary machine names to fetch.
   * @param string $langcode
   *   The language code.
   *
   * @return array
   *   Keyed by vocabulary machine name.
   */
  private function queryUniqueNameTermsBatch(array $vocabs, string $langcode): array {
    if (empty($vocabs)) {
      return [];
    }

    $query = $this->database->select('taxonomy_term_field_data', 'td');
    $query->condition('td.vid', $vocabs, 'IN');
    $query->condition('td.langcode', $langcode);
    $query->condition('td.status', 1);
    $query->fields('td', ['tid', 'name', 'vid']);
    $query->leftJoin('taxonomy_term__field_unique_name', 'un', "un.entity_id = td.tid AND un.langcode = 'en'");
    $query->addField('un', 'field_unique_name_value', 'unique_name');

    $results = $query->execute()->fetchAll();

    $grouped = [];
    $needsMachineNameByVocab = [];
    foreach ($results as $row) {
      $vid = $row->vid;
      $idx = count($grouped[$vid] ?? []);
      $grouped[$vid][] = [
        'id'          => (int) $row->tid,
        'name'        => $row->name,
        'unique_name' => $row->unique_name ?? '',
      ];
      if (empty($row->unique_name)) {
        $needsMachineNameByVocab[$vid][$idx] = (int) $row->tid;
      }
    }

    foreach ($needsMachineNameByVocab as $vid => $needsMap) {
      $this->batchResolveMachineNames($grouped[$vid], $needsMap);
    }

    foreach ($vocabs as $vocab) {
      if (!isset($grouped[$vocab])) {
        $grouped[$vocab] = [];
      }
    }

    return $grouped;
  }

  /**
   * Queries multiple basic vocabularies in a single DB round-trip.
   *
   * @param array $vocabs
   *   List of vocabulary machine names to fetch.
   * @param string $langcode
   *   The language code.
   *
   * @return array
   *   Keyed by vocabulary machine name.
   */
  private function queryBasicTermsBatch(array $vocabs, string $langcode): array {
    if (empty($vocabs)) {
      return [];
    }

    $query = $this->database->select('taxonomy_term_field_data', 'td');
    $query->condition('td.vid', $vocabs, 'IN');
    $query->condition('td.langcode', $langcode);
    $query->condition('td.status', 1);
    $query->fields('td', ['tid', 'name', 'vid']);

    $results = $query->execute()->fetchAll();

    $grouped = [];
    foreach ($results as $row) {
      $grouped[$row->vid][] = [
        'id'   => (int) $row->tid,
        'name' => $row->name,
      ];
    }

    foreach ($vocabs as $vocab) {
      if (!isset($grouped[$vocab])) {
        $grouped[$vocab] = [];
      }
    }

    return $grouped;
  }

  /**
   * Batch-resolves machine names for terms missing field_unique_name.
   *
   * @param array &$terms
   *   The terms array to modify.
   * @param array $needsMachineName
   *   Map of term array index => tid.
   */
  private function batchResolveMachineNames(array &$terms, array $needsMachineName): void {
    if (empty($needsMachineName)) {
      return;
    }

    $tids = array_values($needsMachineName);
    $enNames = $this->database->select('taxonomy_term_field_data', 'td')
      ->fields('td', ['tid', 'name'])
      ->condition('td.tid', $tids, 'IN')
      ->condition('td.langcode', 'en')
      ->execute()
      ->fetchAllKeyed();

    foreach ($needsMachineName as $idx => $tid) {
      $source = $enNames[$tid] ?? $terms[$idx]['name'];
      $machine = strtolower($source);
      $machine = preg_replace('/[^a-z0-9]/', '_', $machine);
      $machine = preg_replace('/_+/', '_', $machine);
      $terms[$idx]['unique_name'] = trim($machine, '_');
    }
  }

  /**
   * Transforms rows for the V1 sponsors listing display.
   *
   * Parses media fields and casts boolean fields.
   *
   * @param array $rows
   *   Raw rows from the view.
   *
   * @return array
   *   Transformed rows.
   */
  private function transformSponsors(array $rows): array {
    if (empty($rows)) {
      return $rows;
    }

    $mediaKeys = ['country_flag', 'country_sponsor_logo', 'country_national_partner'];
    foreach ($rows as &$row) {
      foreach ($mediaKeys as $key) {
        if (array_key_exists($key, $row)) {
          $row[$key] = $this->parseViewCoverImage($row[$key] ?? NULL);
        }
      }
      $this->decodeHtmlEntities($row, ['title']);
    }
    unset($row);

    return $rows;
  }

}
