<?php

namespace Drupal\bebbo_serializer\Plugin\views\style;

use Drupal\bebbo_serializer\Cache\ApiCacheTags;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Connection;
use Drupal\file\FileInterface;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\group\Entity\Group;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\language_visibility_control\LanguageVisibilityService;
use Drupal\rest\Plugin\views\style\Serializer;
use Drupal\rest\Plugin\views\display\RestExport;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Views style plugin that wraps Bebbo REST export rows in a standard envelope.
 *
 * Response shape:
 * @code
 * {
 *   "status":   200,
 *   "total":    <count of view results>,
 *   "langcode": "<language from URL arg or current language>",
 *   "datetime": "<Y-m-d H:i in UTC>",
 *   "data":     [ ...rows transformed by BebboEncoder... ]
 * }
 * @endcode
 *
 * Row transformation (media resolution, type casting) is delegated to
 * BebboEncoder via Symfony's Serializer component, keeping the two
 * responsibilities cleanly separated.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "bebbo_serializer",
 *   title = @Translation("Bebbo serializer"),
 *   help = @Translation("Wraps Bebbo REST export data in a standard envelope with status, total, langcode, and datetime."),
 *   display_types = {"data"}
 * )
 */
class BebboSerializer extends Serializer {

  use BebboSerializerHelpers;

  /**
   * Displays whose response cache tags are scoped to the listing.
   *
   * Rows on these displays render in an isolated context so their per-entity
   * tags never reach the response; the listing tags emitted below (and by
   * the bebbo_api_tag views cache plugin) cover invalidation instead.
   */
  private const TAG_SCOPED_DISPLAYS = [
    'articles_rest_export',
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
    FileUrlGeneratorInterface $file_url_generator,
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
    $this->fileUrlGenerator = $file_url_generator;
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
      $container->get('file_url_generator'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render(): string {
    // Generate timestamp once — used by both the results and
    // no-results code paths. Matches CustomSerializer's
    // helper->getCurrentTimestamp('Asia/Kolkata').
    $timestamp = (new DrupalDateTime('now', 'Asia/Kolkata'))->format('Y-m-d H:i');

    // Validate language visibility (skip for country-groups listing).
    $displayId = $this->view->current_display;
    if ($displayId !== 'country_listing_export') {
      $error = $this->checkLanguageVisibility();
      if ($error) {
        return $this->serializer->serialize(
          $error + ['datetime' => $timestamp],
          'json',
          ['views_style_plugin' => $this],
        );
      }
    }

    // ETag pre-check: skip expensive rendering when data is unchanged.
    $etagResponse = $this->checkEtag($displayId);
    if ($etagResponse !== NULL) {
      return $etagResponse;
    }

    // Collect rows via the parent row plugin.
    // Hand the row entity over already switched to the requested language.
    // Entities load with their original language active, and core's
    // EntityRepository::getTranslationFromContext() runs the full
    // language-fallback negotiation (content_translation walks every
    // translation with access checks) whenever the active language differs
    // from the requested one — even when that translation exists.
    $renderLangcode = $this->view->args[0] ?? $this->languageManager->getCurrentLanguage()->getId();
    $renderRow = function (int $rowIndex, object $row) use ($renderLangcode): mixed {
      $this->view->row_index = $rowIndex;
      if (isset($row->_entity) && $row->_entity instanceof TranslatableInterface && $row->_entity->hasTranslation($renderLangcode)) {
        $row->_entity = $row->_entity->getTranslation($renderLangcode);
      }
      return $this->normalizeMarkup($this->view->rowPlugin->render($row));
    };

    if (in_array($displayId, self::TAG_SCOPED_DISPLAYS, TRUE)) {
      $langcode = $this->view->args[0] ?? $this->languageManager->getCurrentLanguage()->getId();
      // Render in an isolated context and discard what bubbles. acquia_purge
      // emits every response tag as a CDN Surrogate-Key, so hundreds of
      // node:ID tags overflow the response header (Apache "premature end of
      // script headers" → HTTP 500) and flood the purge queue.
      $rows = $this->getRenderer()->executeInRenderContext(new RenderContext(), function () use ($renderRow): array {
        $rows = [];
        foreach ($this->view->result as $rowIndex => $row) {
          $rows[] = $renderRow($rowIndex, $row);
        }
        return $rows;
      });
      // Scope the response to this listing in this language. node_list would
      // expire it on any node save anywhere — an FAQ edit taking every
      // article response with it. The payload references node and media
      // entities only — categories and keywords are emitted as IDs, so term
      // edits never change output — so the listing tags plus media_list are
      // complete.
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

    // Early return when there are no results.
    // Skips expensive work (media batch load).
    // Returns a minimal envelope — no total, langcode, or data to report.
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

    // Has results path — transform rows then build the full envelope.
    // Dispatch by display ID so multiple displays under the same view
    // (bebbo_v2_apis) each get their own transformation.
    $displayId = $this->view->current_display;
    $rows      = $this->transformRows($displayId, $rows);
    $langcode  = $this->resolveLangcode();

    // Taxonomy endpoints return {status, langcode, datetime, data} — no total.
    if ($displayId === 'vocabulary_rest_export' || $displayId === 'terms_rest_export') {
      return $this->serializer->serialize(
        [
          'status'   => 200,
          'langcode' => $langcode,
          'datetime' => $timestamp,
          'data'     => $rows,
        ],
        $this->getOutputFormat(),
        ['views_style_plugin' => $this],
      );
    }

    // Standard deviation returns a non-standard envelope: {status, langcode,
    // data} — no total, no datetime.
    if ($displayId === 'standard_deviation_rest_export') {
      return $this->serializer->serialize(
        [
          'status'   => 200,
          'langcode' => $langcode,
          'data'     => $rows,
        ],
        $this->getOutputFormat(),
        ['views_style_plugin' => $this],
      );
    }

    // Archive returns a grouped structure — total is the sum of all IDs
    // across content types, not a single-bundle entity count.
    if ($displayId === 'archive_rest_export') {
      $total = array_sum(array_map('count', $rows));
    }
    else {
      // Use the view's own total_rows (set by the pager during query
      // execution). This respects every filter the view applies — language,
      // child_age, status, distinct — so it matches the actual data count.
      // Falls back to counting transformed rows if total_rows is unavailable.
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
      $this->getOutputFormat(),
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
   * Uses display ID (not view ID) so multiple REST export displays under
   * the same view (bebbo_v2_apis) can each have their own transformation.
   * Add a new case here for each new REST export display.
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
      'weekly_overview_export'   => $this->transformPregnancyWeekly($rows),
      'guide_rest_export'        => $this->transformGuide($rows),
      'vaccination_rest_export'  => $this->transformVaccinations($rows),
      'country_listing_export'   => $this->transformCountryGroups($rows),
      'archive_rest_export'      => $this->transformArchive($rows),
      'activities_rest_export'   => $this->transformActivities($rows),
      'articles_rest_export'     => $this->transformArticles($rows),
      'faq_rest_export'          => $this->transformFaq($rows),
      'basic_page_rest_export'   => $this->transformBasicPages($rows),
      'home_screen_rest_export'  => $this->transformDailyHomeScreenMessages($rows),
      'standard_deviation_rest_export' => $this->transformStandardDeviation($rows),
      'milestones_rest_export' => $this->transformMilestones($rows),
      'child_dev_boy_rest_export'  => $this->transformChildDevPinned($rows),
      'child_dev_girl_rest_export' => $this->transformChildDevPinned($rows),
      'child_development_rest_export' => $this->transformChildDevelopment($rows),
      'child_growth_rest_export'   => $this->transformChildGrowth($rows),
      'health_checkup_rest_export' => $this->transformHealthCheckUps($rows),
      'survey_rest_export'         => $this->transformSurveys($rows),
      'vocabulary_rest_export'     => $this->transformVocabularies($rows),
      'terms_rest_export'          => $this->transformTaxonomies($rows),
      'video_article_rest_export'  => $this->transformVideoArticles($rows),
      'course_rest_export'         => $this->transformCourse($rows),
      'quiz_rest_export'           => $this->transformQuiz($rows),
      default => $rows,
    };
  }

  /**
   * Transforms rows for the pregnancy weekly overview display.
   *
   * Resolves media fields to WebP URLs, casts numeric fields, and converts
   * related_articles to a deduplicated int array.
   *
   * @param array $rows
   *   Raw rows from the view.
   *
   * @return array
   *   Transformed rows.
   */
  private function transformPregnancyWeekly(array $rows): array {
    if (empty($rows)) {
      return $rows;
    }

    foreach ($rows as &$row) {
      $this->castToInt($row, ['id', 'prental_age', 'licensed']);
      $this->castToNumber($row, ['average_height', 'average_weight']);
      $this->toIntArray($row, ['related_articles']);

      // Parse view → featured_image_1 and view_1 → featured_image_2
      // from the embedded media_details view JSON output {url, name, alt}.
      $row['featured_image_1'] = $this->parseViewCoverImage($row['featured_image_1'] ?? NULL);
      $row['featured_image_2'] = $this->parseViewCoverImage($row['featured_image_2'] ?? NULL);
    }
    unset($row);

    return $rows;
  }

  /**
   * Transforms rows for the guide REST export display.
   *
   * Casts id to int and converts multi-value reference fields to int arrays.
   * Removes related_articles when empty.
   *
   * @param array $rows
   *   Raw rows from the view.
   *
   * @return array
   *   Transformed rows.
   */
  private function transformGuide(array $rows): array {
    if (empty($rows)) {
      return $rows;
    }

    foreach ($rows as &$row) {
      $this->castToInt($row, ['id', 'child_age', 'licensed']);
      $this->toIntArray($row, ['related_articles', 'related_games']);

      // Remove related_articles when empty (display-specific rule).
      if (empty($row['related_articles'])) {
        unset($row['related_articles']);
      }
    }
    unset($row);

    return $rows;
  }

  /**
   * Transforms rows for the vaccination REST export display.
   *
   * Casts numeric fields to int, converts pinned_article to a deduplicated
   * int array, and decodes HTML entities in title.
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
   * Transforms rows for the activities REST export display.
   *
   * Casts numeric fields, converts multi-value references to int arrays,
   * resolves cover_image to {url, name, alt} with WebP, and decodes title.
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
        'id', 'activity_category', 'equipment',
        'type_of_support', 'mandatory', 'read_count', 'love_count',
      ]);
      $this->toIntArray($row, ['child_age', 'related_milestone']);
      $this->toStringArray($row, ['embedded_images']);
      $this->decodeHtmlEntities($row, ['title']);

      // Parse view → cover_image ({url, name, alt}) from the embedded
      // media_details view (media_image_rest_export display) JSON output.
      $row['cover_image'] = $this->parseViewCoverImage($row['cover_image'] ?? NULL);
    }
    unset($row);

    return $rows;
  }

  /**
   * Transforms rows for the articles REST export display.
   *
   * Collects cover_image media IDs from all rows and batch-resolves them
   * via resolveMediaIds() (single loadMultiple + image style URL generation)
   * instead of per-row sub-view execution. Casts numeric fields, converts
   * multi-value references to int arrays, normalizes field_embedded_images
   * to a string array, and decodes HTML entities in titles.
   *
   * @param array $rows
   *   Raw rows from the view.
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
        'child_gender', 'parent_gender', 'premature', 'read_count', 'love_count',
      ]);
      $this->toIntArray($row, [
        'child_age', 'keywords', 'related_articles', 'related_video_articles', 'target_audience',
      ]);
      $this->toStringArray($row, ['embedded_images']);
      $this->decodeHtmlEntities($row, ['title']);

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
          'alt' => (string) ($row['cover_image_alt'] ?? ''),
        ]
        : $emptyMedia;

      unset($row['cover_image_mid'], $row['cover_image_url'], $row['cover_image_name'], $row['cover_image_alt']);
    }
    unset($row);

    return $rows;
  }

  /**
   * Transforms rows for the video article display.
   *
   * Resolves cover_video to a video object {url, name, site} and
   * cover_video_image to a thumbnail {url, name, alt} (renamed to
   * cover_image to match v1 output). Casts numeric fields and converts
   * multi-value references to int arrays.
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
        'licensed', 'premature', 'mandatory', 'read_count', 'love_count',
      ]);
      $this->toIntArray($row, [
        'child_age', 'keywords', 'related_articles', 'related_video_articles', 'target_audience',
      ]);
      $this->toStringArray($row, ['embedded_images']);
      $this->decodeHtmlEntities($row, ['title']);

      $row['cover_video'] = $this->parseViewVideoMedia($row['cover_video'] ?? NULL);
      $row['cover_image'] = $this->parseViewCoverImage($row['cover_image'] ?? NULL);
    }
    unset($row);

    return $rows;
  }

  /**
   * Transforms rows for the FAQ display.
   *
   * Casts numeric fields to int and decodes HTML entities in the question
   * field. FAQs have no media, multi-value, or embedded image fields.
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
   * Transforms rows for the basic pages display.
   *
   * Casts numeric fields to int, converts embedded_images to a string array,
   * and decodes HTML entities in the title. No media fields or multi-value
   * int fields — embedded images are pre-computed in field_embedded_images.
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

    $nids = array_column($rows, 'id');
    $englishTitles = $this->getEnglishNodeTitles($nids);

    foreach ($rows as &$row) {
      $this->castToInt($row, ['id', 'mandatory']);
      $this->toStringArray($row, ['embedded_images']);
      $this->decodeHtmlEntities($row, ['title']);

      if (!empty($englishTitles[$row['id']])) {
        $row['unique_name'] = str_replace(' ', '_', strtolower($englishTitles[$row['id']]));
      }
      else {
        $row['unique_name'] = '';
      }
    }
    unset($row);

    return $rows;
  }

  /**
   * Transforms rows for the daily home screen messages display.
   *
   * Casts id to int and decodes HTML entities in title. This is one of the
   * simplest endpoints — no media fields, no multi-value arrays, no embedded
   * images.
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
   * Transforms rows for the milestones display.
   *
   * Casts numeric fields to int, converts multi-value reference fields to
   * int arrays, and decodes HTML entities in the title.
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
   * Transforms rows for the child development pinned-content displays.
   *
   * Resolves media fields (cover_video handles image/remote_video/video types,
   * cover_video_image handles image only), casts numeric and boolean fields,
   * converts multi-value references to int arrays, and applies type-specific
   * field handling: Articles drop video fields, Video Articles rename
   * cover_video_image to cover_image.
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

    // Deduplicate rows by the related article's nid. Multiple
    // child_development nodes can pin the same article, and multi-value
    // field JOINs can further multiply rows. Keep only the first
    // occurrence of each article ID.
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

      // Pinned-content type-specific field handling (matches v1 logic).
      $type = $row['type'] ?? '';
      if ($type === 'Article') {
        // Articles have no video fields.
      }
      elseif ($type === 'Video Article') {
        $row['cover_video'] = $coverVideo;
        $row['cover_image'] = $coverImage;
      }
    }
    unset($row);

    return $rows;
  }

  /**
   * Transforms rows for the child development data listing display.
   *
   * Unlike the boy/girl pinned-content displays (transformChildDevPinned),
   * this display returns the child_development nodes themselves with
   * boy_video_article, girl_video_article, and milestone fields — no media,
   * no cover_video, no deduplication.
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
   * Transforms rows for the child growth REST export display.
   *
   * Casts numeric fields to int and converts child_age and
   * related_articles to deduplicated int arrays.
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
   * Transforms rows for the health check-ups pinned-content display.
   *
   * Similar to child development but includes a direct cover_image field
   * (image media) alongside cover_video/cover_video_image, and adds
   * related_video_articles as an additional int array field.
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

    // Deduplicate rows by the related article's nid — multiple
    // health_check_ups nodes can pin the same article via the relationship.
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

      // Pinned-content type-specific field handling (matches v1 logic).
      $type = $row['type'] ?? '';
      if ($type === 'Article') {
        unset($row['cover_video'], $row['cover_video_image']);
      }
      elseif ($type === 'Video Article') {
        $row['cover_image'] = $row['cover_video_image'];
        unset($row['cover_video_image']);
      }
    }
    unset($row);

    return $rows;
  }

  /**
   * Transforms rows for the surveys display.
   *
   * Surveys are simple — no relationships, no pinned-content deduplication.
   * Casts numeric fields, resolves featured images, and decodes HTML entities.
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
   * Transforms vocabulary list rows into a keyed object.
   *
   * The view returns rows like [{name: "child_age,Child's Age"}, ...].
   * Splits each into machine_name and label, returning {machine_name:
   * {name: Label}} to match the V1 response shape.
   *
   * @param array $rows
   *   Raw rows from the view.
   *
   * @return array
   *   Keyed object: {machine_name: {name: "Label"}, ...}.
   */
  private function transformVocabularies(array $rows): array {
    $langcode = $this->resolveLangcode();
    $vocab_storage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');
    $result = [];

    // Derive vocabularies from the view's result entities rather than the
    // rendered "name" field. That field uses an access-gated
    // entity_reference_label render, so anonymous API callers (the real app
    // authenticating via JWT) would otherwise get an empty list. Loading the
    // vocabulary and reading its label is not access-gated.
    foreach ($this->view->result as $row) {
      $term = $row->_entity ?? NULL;
      if ($term === NULL) {
        continue;
      }
      $machineName = $term->bundle();
      if ($machineName === '' || $machineName === 'keywords' || isset($result[$machineName])) {
        continue;
      }

      // Prefer the requested language's translated vocabulary label; fall back
      // to the base (default-language) label.
      $label = NULL;
      if ($this->languageManager instanceof ConfigurableLanguageManagerInterface) {
        $label = $this->languageManager
          ->getLanguageConfigOverride($langcode, 'taxonomy.vocabulary.' . $machineName)
          ->get('name');
      }
      if (!$label) {
        $vocab = $vocab_storage->load($machineName);
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
   * When called with the "all" wildcard (multiple rows from the view), each
   * vocabulary is classified into one of three groups and dispatched in the
   * most query-efficient way:
   *
   *  - Specialty vocabs (own JOIN shape, one query each):
   *      growth_period, child_age, growth_introductory,
   *      chatbot_subcategory, category
   *
   *  - Unique-name vocabs (batched into ONE query):
   *      growth_type, activity_category, child_gender, parent_gender,
   *      relationship_to_parent, chatbot_category, subcategory,
   *      target_audience, course_category
   *
   *  - Basic vocabs (batched into ONE query):
   *      everything else (chatbot_child_age, type_of_article, etc.)
   *
   * The "keywords" vocabulary is always skipped (matches V1 behaviour).
   *
   * For a single-vocab call (/v2/api/taxonomies/en/{vocab}) the behaviour
   * is identical to the old single-dispatch path — no regressions.
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

    // Vocabulary classification lists.
    $specialtyVocabs  = [
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

    $toSpecialty  = [];
    $toUniqueName = [];
    $toBasic      = [];

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
        // Skip empty entries, the keywords vocab (matches V1 behaviour), and
        // duplicates.
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

    // Specialty vocabs: one query each (unavoidable — different JOIN shapes).
    foreach ($toSpecialty as $vocabMachine) {
      $result[$vocabMachine] = match ($vocabMachine) {
        'growth_period'       => $this->queryGrowthPeriodTerms($langcode),
        'child_age'           => $this->queryChildAgeTerms($langcode),
        'growth_introductory' => $this->queryGrowthIntroductoryTerms($langcode),
        'chatbot_subcategory' => $this->queryChatbotSubcategoryTerms($langcode),
        'category'            => $this->queryCategoryTerms($langcode),
      };
    }

    // Unique-name vocabs: one batch query for all of them combined.
    if (!empty($toUniqueName)) {
      foreach ($this->queryUniqueNameTermsBatch($toUniqueName, $langcode) as $vid => $terms) {
        $result[$vid] = $terms;
      }
    }

    // Basic vocabs: one batch query for all of them combined.
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
   * Builds the base taxonomy term query shared by all vocabularies.
   *
   * Selects published terms for a specific vocabulary and language.
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
   *   Array of [{id, name, vaccination_opens, short_name, unique_name}].
   */
  private function queryGrowthPeriodTerms(string $langcode): array {
    $query = $this->buildTermBaseQuery('growth_period', $langcode);
    $query->leftJoin('taxonomy_term__field_vaccination_opens', 'vo', 'vo.entity_id = td.tid');
    $query->leftJoin('taxonomy_term__field_short_name', 'sn', 'sn.entity_id = td.tid AND sn.langcode = td.langcode');
    $query->leftJoin('taxonomy_term__field_unique_name', 'un', "un.entity_id = td.tid AND un.langcode = 'en'");
    $query->addField('vo', 'field_vaccination_opens_value', 'vaccination_opens');
    $query->addField('sn', 'field_short_name_value', 'short_name');
    $query->addField('un', 'field_unique_name_value', 'unique_name');

    $results = $query->execute()->fetchAll();
    $terms = [];
    $needsMachineName = [];
    foreach ($results as $idx => $row) {
      $terms[] = [
        'id' => (int) $row->tid,
        'name' => $row->name,
        'vaccination_opens' => (int) ($row->vaccination_opens ?? 0),
        'short_name' => $row->short_name ?? '',
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
   * Queries child_age vocabulary terms.
   *
   * Includes days_from, days_to, buffers_days, and multi-value age_bracket.
   * Sorted by weight (matching V1). Supports ?pregnancy=true filtering.
   *
   * @param string $langcode
   *   The language code.
   *
   * @return array
   *   Array of [{id, name, days_from, days_to, buffers_days, age_bracket}].
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

    // Pregnancy filtering: exclude Pregnancy term unless ?pregnancy=true.
    $request = $this->requestStack->getCurrentRequest();
    $pregnancyParam = $request ? $request->query->get('pregnancy') : NULL;
    if ($pregnancyParam !== 'true') {
      $query->condition('td.name', 'Pregnancy', '<>');
    }

    $results = $query->execute()->fetchAll();
    $tids = array_column($results, 'tid');

    // Batch-load multi-value age_bracket field.
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
   *   Array of [{id, name, body, days_from, days_to}].
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
   *   Array of [{id, name, parent_category_id, unique_name}].
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

    // Batch-resolve machine names for terms missing field_unique_name.
    $this->batchResolveMachineNames($terms, $needsMachineName);

    return $terms;
  }

  /**
   * Queries category vocabulary terms.
   *
   * Resolves the field_type_of_article entity reference via JOINs instead of
   * N+1 entity loads, and returns it as a machine name derived from the
   * English label.
   *
   * @param string $langcode
   *   The language code.
   *
   * @return array
   *   Array of [{id, name, unique_name, field_type_of_article}].
   */
  private function queryCategoryTerms(string $langcode): array {
    $query = $this->buildTermBaseQuery('category', $langcode);
    $query->leftJoin('taxonomy_term__field_unique_name', 'un', "un.entity_id = td.tid AND un.langcode = 'en'");
    $query->leftJoin('taxonomy_term__field_type_of_article', 'toa', 'toa.entity_id = td.tid');
    // Resolve the entity reference via two JOINs. The English label is
    // preferred because it is the source for the machine name, which must not
    // vary by language; the requested language is only a fallback for terms
    // with no English row at all. type_of_article carries no
    // field_unique_name, so there is no stored machine name to read instead.
    $query->leftJoin('taxonomy_term_field_data', 'toa_td_lang', "toa_td_lang.tid = toa.field_type_of_article_target_id AND toa_td_lang.langcode = td.langcode");
    $query->leftJoin('taxonomy_term_field_data', 'toa_td_en', "toa_td_en.tid = toa.field_type_of_article_target_id AND toa_td_en.langcode = 'en'");
    $query->addField('un', 'field_unique_name_value', 'unique_name');
    $query->addExpression("COALESCE(toa_td_en.name, toa_td_lang.name)", 'type_of_article');

    $results = $query->execute()->fetchAll();
    $terms = [];
    $needsMachineName = [];
    foreach ($results as $idx => $row) {
      $terms[] = [
        'id' => (int) $row->tid,
        'name' => $row->name,
        'unique_name' => $row->unique_name ?? '',
        'field_type_of_article' => $row->type_of_article ? $this->slugifyTermName($row->type_of_article) : '',
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
   * Fetches all terms for every vocabulary in $vocabs with a single SELECT
   * that includes a LEFT JOIN on field_unique_name, then groups the flat
   * result set by vocabulary machine name in PHP. Missing machine names are
   * resolved via batchResolveMachineNames() — once per affected vocabulary.
   *
   * Used by transformTaxonomies() when more than one unique-name vocab is
   * requested in the same call (e.g. the "all" wildcard endpoint).
   *
   * @param array $vocabs
   *   List of vocabulary machine names to fetch.
   * @param string $langcode
   *   The language code.
   *
   * @return array
   *   Keyed by vocabulary machine name; each value is
   *   [{id, name, unique_name}].
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

    // Group rows by vocabulary and track indices that need a generated name.
    $grouped                 = [];
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

    // Resolve missing machine names per vocabulary.
    foreach ($needsMachineNameByVocab as $vid => $needsMap) {
      $this->batchResolveMachineNames($grouped[$vid], $needsMap);
    }

    // Guarantee every requested vocab key is present, even when empty.
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
   * Fetches tid + name for every vocabulary in $vocabs with a single SELECT
   * on taxonomy_term_field_data (no extra JOINs), then groups the result by
   * vocabulary machine name in PHP.
   *
   * Used by transformTaxonomies() when more than one basic vocab is requested
   * in the same call (e.g. the "all" wildcard endpoint).
   *
   * @param array $vocabs
   *   List of vocabulary machine names to fetch.
   * @param string $langcode
   *   The language code.
   *
   * @return array
   *   Keyed by vocabulary machine name; each value is [{id, name}].
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

    // Guarantee every requested vocab key is present, even when empty.
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
   * Fetches the English name for each term in a single query, then generates
   * a machine name (lowercased, non-alphanumeric replaced with underscores).
   * Modifies the $terms array in place.
   *
   * @param array &$terms
   *   The terms array to modify (each item has a 'unique_name' key).
   * @param array $needsMachineName
   *   Map of term array index => tid for terms needing a generated name.
   */
  private function batchResolveMachineNames(array &$terms, array $needsMachineName): void {
    if (empty($needsMachineName)) {
      return;
    }

    $tids = array_values($needsMachineName);
    // Fetch English names in a single query for stable machine name generation.
    $enNames = $this->database->select('taxonomy_term_field_data', 'td')
      ->fields('td', ['tid', 'name'])
      ->condition('td.tid', $tids, 'IN')
      ->condition('td.langcode', 'en')
      ->execute()
      ->fetchAllKeyed();

    foreach ($needsMachineName as $idx => $tid) {
      $source = $enNames[$tid] ?? $terms[$idx]['name'];
      $terms[$idx]['unique_name'] = $this->slugifyTermName($source);
    }
  }

  /**
   * Transforms rows for the standard deviation display.
   *
   * Restructures flat view rows into a deeply nested response grouped by
   * growth type and child-age bucket. Each bucket contains SD-keyed objects
   * with article data.
   *
   * Performance: resolves all taxonomy term IDs to field_unique_name values
   * in a single DB query (v1 made ~700+ individual Term::load calls).
   *
   * @param array $rows
   *   Raw rows from the view.
   *
   * @return array
   *   Nested structure: {weight_for_height: [...], height_for_age: [...]}.
   */
  private function transformStandardDeviation(array $rows): array {
    if (empty($rows)) {
      return $rows;
    }

    // 1. Collect all unique term IDs for batch resolution.
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

    // 2. Static maps — SD label to output key per growth type.
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

    // 3. Bucket definitions — child_age unique names grouped into ranges.
    $buckets = [
      '1st_month,2nd_month,3_4_months,5_6_months',
      '7_9_months',
      '10_12_months',
      '13_18_months,19_24_months',
      '25_36_months,37_48_months,49_60_months,61_72_months',
    ];

    // 4. Normalize rows and group by growth type.
    $itemsByGrowth = [
      'height_for_age'    => [],
      'height_for_weight' => [],
    ];

    foreach ($rows as $row) {
      $growthTid = (int) ($row['growth_type'] ?? 0);
      $growth    = $termNameMap[$growthTid] ?? NULL;
      if (!$growth || !isset($sdLabelMaps[$growth])) {
        continue;
      }

      // Resolve child_age TIDs to unique names for bucketing.
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

    // 5. Build nested output per growth type.
    $final = [];
    foreach (['height_for_weight', 'height_for_age'] as $growth) {
      $items = $itemsByGrowth[$growth] ?? [];
      if (empty($items)) {
        continue;
      }

      // Group items by bucket key.
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

        // child_age: array of int IDs from the first item (shared by bucket).
        $childAgeIds = $this->splitNumericIds($groupItems[0]['child_age']);
        $sdData = [
          'child_age' => array_values(array_filter(array_map('intval', $childAgeIds))),
        ];

        // Map SD labels to output keys in explicit order.
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

        // Insert in explicit order.
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
        // Remap: height_for_weight → weight_for_height (matches v1).
        $outputKey = $growth === 'height_for_weight' ? 'weight_for_height' : 'height_for_age';
        $final[$outputKey] = $sdArr;
      }
    }

    return $final;
  }

  /**
   * Splits a comma-separated string of numeric IDs into an int array.
   *
   * Handles both comma-separated strings and single values.
   *
   * @param string $value
   *   Comma-separated IDs (e.g. "43, 44, 45") or a single ID.
   *
   * @return int[]
   *   Array of integer IDs (empty values filtered out).
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
   * Uses a direct DB query for minimal overhead — no entity hydration.
   * Suitable for small reference vocabularies (growth_type, child_age).
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
   * Uses a direct DB query against taxonomy_term_field_data for the term
   * label (name column). Mirrors V1's Term::load($tid)->getName() but
   * batch-friendly and without entity hydration.
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
   * Transforms rows for the country-groups listing display.
   *
   * Resolves media IDs to {url, name, alt} objects, builds a rich languages
   * array from Group entities and custom_language_data, applies language
   * visibility filtering, and moves CountryID 126 to the end.
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

    // 1. Filter out CountryID 131.
    $rows = array_values(array_filter($rows, fn($row) => ($row['CountryID'] ?? '') !== '131'));

    // 1b. Deduplicate rows by CountryID.
    // The Views query joins group__field_language (multi-value), producing
    // one row per language value per group. Keep only the first occurrence.
    $seen = [];
    $rows = array_values(array_filter($rows, function ($row) use (&$seen) {
      $id = $row['CountryID'] ?? '';
      if (isset($seen[$id])) {
        return FALSE;
      }
      $seen[$id] = TRUE;
      return TRUE;
    }));

    // 2. Parse media fields from embedded view HTML to {url, name, alt}.
    $mediaKeys = [
      'country_national_partner',
      'country_sponsor_logo',
      'unicef_logo',
      'all_logos',
      'country_flag',
    ];
    foreach ($rows as &$row) {
      foreach ($mediaKeys as $key) {
        $row[$key] = $this->parseViewCoverImage($row[$key] ?? NULL);
      }
    }
    unset($row);

    // 3. Batch-load all Group entities needed for language building.
    $groupIds = array_filter(array_column($rows, 'CountryID'));
    $groupStorage = $this->entityTypeManager->getStorage('group');
    $groups = $groupStorage->loadMultiple($groupIds);

    // 4. Build languages for each row and clean up fields.
    $entry126Index = NULL;
    foreach ($rows as $index => &$row) {
      $countryId = $row['CountryID'] ?? '';
      $group = $groups[$countryId] ?? NULL;

      if ($countryId === '126') {
        // Special "Rest of the World" entry with hardcoded en + ru.
        $row['name'] = 'Rest of the world';
        $row['displayName'] = 'Rest of the world';
        $row['languages'] = $this->buildLanguagesForRestOfWorld();
        $entry126Index = $index;
      }
      elseif ($group instanceof Group) {
        $row['languages'] = $this->buildLanguagesForGroup($row['name'] ?? '', $group);
        // Override top-level content_toggle with the default language value
        // for backward compatibility. Per-language values live in languages[].
        $defaultToggle = $group->getUntranslated()->get('field_content_toggle')->getValue();
        $row['content_toggle'] = implode(', ', array_column($defaultToggle, 'value'));
      }
      else {
        $row['languages'] = [];
      }

      // Remove raw langcode array — replaced by languages.
      unset($row['langcode']);
    }
    unset($row);

    // 5. Move CountryID 126 to end of array.
    if ($entry126Index !== NULL) {
      $entry = array_splice($rows, $entry126Index, 1);
      $rows[] = $entry[0];
    }

    return $rows;
  }

  /**
   * Transforms rows for the archive (deleted content) display.
   *
   * Groups node IDs by content type, producing a keyed array like:
   * @code
   * { "Article": [123, 456], "FAQ": [789], "Activities": [101] }
   * @endcode
   *
   * @param array $rows
   *   Raw rows from the view, each with at least 'nid' and 'type'.
   *
   * @return array
   *   Node IDs grouped by content type.
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
   * Builds languages array for a regular country group.
   *
   * Loads language data from custom_language_data table and
   * ConfigurableLanguage entities, applies visibility filtering,
   * and sorts by language weight.
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

    // Batch load custom_language_data and ConfigurableLanguage entities.
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
      // Skip disabled languages.
      if (!$this->languageManager->getLanguage($langcode)) {
        continue;
      }
      $configLang = $configLanguages[$langcode] ?? NULL;
      if (!$configLang) {
        continue;
      }

      $data = $langData[$langcode] ?? [];

      // Get per-language content_toggle. Only read from an explicit
      // translation; if no translation exists, default to empty string.
      // The top-level content_toggle field provides backward-compat fallback.
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

    // Apply language visibility filtering.
    $languages = $this->languageVisibilityService->filterLanguageDataForApi($languages, $group);

    // Sort by weight, then remove the weight key.
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
   * Hardcoded to English and Russian.
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
   * Transforms rows for the course REST export display.
   *
   * Casts numeric fields, resolves cover_image, and batch-loads courses_module
   * nodes from field_course_modules without N+1 queries. Each course gets a
   * "module" array with structured module data.
   *
   * @param array $rows
   *   Raw rows from the view.
   *
   * @return array
   *   Transformed rows with nested module data.
   */
  private function transformCourse(array $rows): array {
    if (empty($rows)) {
      return $rows;
    }

    $langcode = $this->resolveLangcode();

    // Collect all course nids from the view rows (aliased as "id").
    $nids = array_filter(array_column($rows, 'id'), 'is_numeric');
    $nids = array_map('intval', $nids);

    // Single DB query: course nid → courses_module node IDs.
    $nidToModuleIds = [];
    if (!empty($nids)) {
      $result = $this->database->select('node__field_course_modules', 'fm')
        ->fields('fm', ['entity_id', 'field_course_modules_target_id'])
        ->condition('fm.entity_id', $nids, 'IN')
        ->condition('fm.deleted', 0)
        ->condition('fm.langcode', $langcode)
        ->orderBy('fm.entity_id')
        ->orderBy('fm.delta')
        ->execute();

      foreach ($result as $record) {
        $nidToModuleIds[(int) $record->entity_id][] = (int) $record->field_course_modules_target_id;
      }
    }

    // Batch-load all courses_module nodes.
    $allModuleIds = array_merge(...array_values($nidToModuleIds ?: [[]]));
    $moduleNodes = [];
    if (!empty($allModuleIds)) {
      /** @var \Drupal\node\NodeInterface[] $moduleNodes */
      $moduleNodes = $this->entityTypeManager
        ->getStorage('node')
        ->loadMultiple($allModuleIds);
    }

    // Collect document media IDs for batch resolution.
    $docMediaIds = [];
    foreach ($moduleNodes as $moduleNode) {
      $translated = $moduleNode->hasTranslation($langcode)
        ? $moduleNode->getTranslation($langcode)
        : $moduleNode;
      if ($translated->hasField('field_resource_file_internal') && !$translated->get('field_resource_file_internal')->isEmpty()) {
        $docMediaIds[] = (int) $translated->get('field_resource_file_internal')->target_id;
      }
    }

    // Batch-load document media → {url, name}.
    $resolvedDocs = $this->resolveDocumentMediaIds(array_unique($docMediaIds));

    // Build module arrays per courses_module node.
    $moduleData = [];
    foreach ($moduleNodes as $mid => $moduleNode) {
      $translated = $moduleNode->hasTranslation($langcode)
        ? $moduleNode->getTranslation($langcode)
        : $moduleNode;

      $moduleTitle = '';
      if ($translated->hasField('field_module_title')) {
        $moduleTitle = (string) ($translated->get('field_module_title')->value ?? '');
      }

      $numberingStyle = '';
      if ($translated->hasField('field_numbering_style')) {
        $numberingStyle = (string) ($translated->get('field_numbering_style')->value ?? '');
      }

      $optionalModule = FALSE;
      if ($translated->hasField('field_optional_module')) {
        $optionalModule = (bool) $translated->get('field_optional_module')->value;
      }

      // Course content as int array.
      $courseContent = [];
      if ($translated->hasField('field_course_content')) {
        foreach ($translated->get('field_course_content') as $item) {
          if (!empty($item->target_id)) {
            $courseContent[] = (int) $item->target_id;
          }
        }
      }

      // Resource files: both fields are independent, not fallbacks.
      $resourceFileInternal = NULL;
      if ($translated->hasField('field_resource_file_internal') && !$translated->get('field_resource_file_internal')->isEmpty()) {
        $mediaId = (int) $translated->get('field_resource_file_internal')->target_id;
        $resourceFileInternal = $resolvedDocs[$mediaId] ?? NULL;
      }

      $resourceFile = NULL;
      if ($translated->hasField('field_resource_file_external') && !$translated->get('field_resource_file_external')->isEmpty()) {
        $link = $translated->get('field_resource_file_external')->first();
        $resourceFile = [
          'url' => (string) ($link->uri ?? ''),
          'name' => (string) ($link->title ?? ''),
        ];
      }

      $moduleData[$mid] = [
        'module_title' => $moduleTitle,
        'content_numbering' => $numberingStyle,
        'optional_module' => $optionalModule,
        'course_content' => $courseContent,
        'resource_file' => $resourceFile,
        'resource_file_internal' => $resourceFileInternal,
      ];
    }

    // Transform each row.
    foreach ($rows as &$row) {
      $nid = (int) ($row['id'] ?? 0);
      $this->castToInt($row, [
        'id', 'course_duration',
        'number_of_modules', 'final_assessment', 'read_count', 'love_count', 'licensed',
      ]);
      $this->toIntArray($row, ['child_age', 'target_audience', 'course_category']);
      $this->castToBool($row, ['feedback_required', 'module_locked']);
      $this->toStringArray($row, ['feedback_question']);
      $this->decodeHtmlEntities($row, ['title']);

      $row['cover_image'] = $this->parseViewCoverImage(
        $row['cover_image'] ?? NULL
      );
      $row['certificate'] = $this->parseViewCoverImage(
        $row['certificate'] ?? NULL
      );

      // Build modules array from pre-loaded courses_module node data.
      $modules = [];
      if (!empty($nidToModuleIds[$nid])) {
        foreach ($nidToModuleIds[$nid] as $mid) {
          if (isset($moduleData[$mid])) {
            $modules[] = $moduleData[$mid];
          }
        }
      }
      $row['module'] = $modules;
    }
    unset($row);

    return $rows;
  }

  /**
   * Transforms rows for the quiz REST export display.
   *
   * Batch-loads quiz_questions nodes from field_quiz_questions and nests
   * them in each quiz row as a "questions" array. Resolves question images
   * via the existing resolveMediaIds() batch loader.
   *
   * @param array $rows
   *   Raw rows from the view.
   *
   * @return array
   *   Transformed rows with nested question data.
   */
  private function transformQuiz(array $rows): array {
    if (empty($rows)) {
      return $rows;
    }

    $langcode = $this->resolveLangcode();

    $nids = array_filter(array_column($rows, 'id'), 'is_numeric');
    $nids = array_map('intval', $nids);

    // Single DB query: quiz nid → question node IDs.
    $nidToQuestionIds = [];
    if (!empty($nids)) {
      $result = $this->database->select('node__field_quiz_questions', 'fq')
        ->fields('fq', ['entity_id', 'field_quiz_questions_target_id'])
        ->condition('fq.entity_id', $nids, 'IN')
        ->condition('fq.deleted', 0)
        ->condition('fq.langcode', $langcode)
        ->orderBy('fq.entity_id')
        ->orderBy('fq.delta')
        ->execute();

      foreach ($result as $record) {
        $nidToQuestionIds[(int) $record->entity_id][] = (int) $record->field_quiz_questions_target_id;
      }
    }

    // Batch-load all question nodes.
    $allQuestionIds = array_merge(
      ...array_values($nidToQuestionIds ?: [[]])
    );
    $questionNodes = [];
    if (!empty($allQuestionIds)) {
      /** @var \Drupal\node\NodeInterface[] $questionNodes */
      $questionNodes = $this->entityTypeManager
        ->getStorage('node')
        ->loadMultiple($allQuestionIds);
    }

    // Collect image media IDs for batch resolution.
    $imageMediaIds = [];
    foreach ($questionNodes as $question) {
      $translated = $question->hasTranslation($langcode)
        ? $question->getTranslation($langcode)
        : $question;
      if ($translated->hasField('field_question_image')
        && !$translated->get('field_question_image')->isEmpty()) {
        $imageMediaIds[] = (int) $translated
          ->get('field_question_image')->target_id;
      }
    }

    $resolvedImages = $this->resolveMediaIds(
      array_unique($imageMediaIds)
    );
    $emptyImage = ['url' => '', 'name' => '', 'alt' => ''];

    // Build question data keyed by node ID.
    $questionData = [];
    foreach ($questionNodes as $qid => $question) {
      $translated = $question->hasTranslation($langcode)
        ? $question->getTranslation($langcode)
        : $question;

      $questionType = '';
      if ($translated->hasField('field_question_type')) {
        $questionType = (string) ($translated
          ->get('field_question_type')->value ?? '');
      }

      $questionText = '';
      if ($translated->hasField('field_question')) {
        $questionText = (string) ($translated
          ->get('field_question')->value ?? '');
      }

      $image = $emptyImage;
      if ($translated->hasField('field_question_image')
        && !$translated->get('field_question_image')->isEmpty()) {
        $mediaId = (int) $translated
          ->get('field_question_image')->target_id;
        $image = $resolvedImages[$mediaId] ?? $emptyImage;
      }

      $answers = [];
      if ($translated->hasField('field_answers')) {
        foreach ($translated->get('field_answers') as $answerItem) {
          $answerText = (string) ($answerItem->value ?? '');
          if ($answerText === '') {
            continue;
          }
          $isCorrect = $answerItem->get('is_correct')->getValue();
          $answers[] = [
            'answer' => $answerText,
            'correct_answer' => (bool) $isCorrect,
          ];
        }
      }

      $explanation = '';
      if ($translated->hasField('field_explanation')) {
        $explanation = (string) ($translated
          ->get('field_explanation')->value ?? '');
      }

      $questionData[$qid] = [
        'type' => $questionType,
        'question' => $questionText,
        'image' => $image,
        'answers' => $answers,
        'explanation' => $explanation,
      ];
    }

    foreach ($rows as &$row) {
      $nid = (int) ($row['id'] ?? 0);
      $this->castToInt($row, [
        'id', 'passing_score',
        'number_of_questions',
        'licensed',
      ]);
      $this->decodeHtmlEntities($row, ['title']);

      $questions = [];
      if (!empty($nidToQuestionIds[$nid])) {
        foreach ($nidToQuestionIds[$nid] as $qid) {
          if (isset($questionData[$qid])) {
            $questions[] = $questionData[$qid];
          }
        }
      }
      $row['questions'] = $questions;
    }
    unset($row);

    return $rows;
  }

  /**
   * Batch-resolves document media IDs to {url, name} arrays.
   *
   * Loads document media entities, extracts the file, and returns the
   * direct file URL and media name.
   *
   * @param array $mediaIds
   *   Array of media entity IDs (document type).
   *
   * @return array
   *   Keyed by media ID, each value has url and name keys.
   */
  private function resolveDocumentMediaIds(array $mediaIds): array {
    $mediaIds = array_filter(array_unique($mediaIds));
    if (empty($mediaIds)) {
      return [];
    }

    $mediaEntities = $this->entityTypeManager
      ->getStorage('media')
      ->loadMultiple($mediaIds);

    $resolved = [];
    $request = $this->requestStack->getCurrentRequest();
    $baseUrl = $request !== NULL ? $request->getSchemeAndHttpHost() : '';

    foreach ($mediaIds as $id) {
      if (!isset($mediaEntities[$id]) || !$mediaEntities[$id]->hasField('field_media_file')) {
        continue;
      }

      $media = $mediaEntities[$id];
      /** @var \Drupal\file\FileInterface|null $file */
      $file = $media->get('field_media_file')->entity;
      if (!$file instanceof FileInterface) {
        continue;
      }

      $url = $file->createFileUrl(FALSE);
      if ($url !== '' && !str_starts_with($url, 'http')) {
        $url = $baseUrl . $url;
      }

      $resolved[$id] = [
        'url' => $url,
        'name' => (string) ($media->get('name')->value ?? ''),
      ];
    }

    return $resolved;
  }

  /**
   * Determines the serialization format for the current display.
   *
   * @return string
   *   Format string, e.g. "bebbo_json" or "json".
   */
  private function getOutputFormat(): string {
    if (!empty($this->view->live_preview)) {
      return 'json';
    }
    if ($this->displayHandler instanceof RestExport) {
      return $this->displayHandler->getContentType();
    }
    return !empty($this->options['formats']) ? reset($this->options['formats']) : 'json';
  }

  /**
   * Checks ETag and returns early if data is unchanged.
   *
   * Runs a lightweight SQL query (MAX changed + COUNT) to build a data
   * fingerprint. If the client sends a matching If-None-Match header,
   * stores a flag on the request for the response subscriber to convert
   * to 304. Returns a minimal empty string to skip all rendering.
   *
   * @param string $displayId
   *   The active view display ID.
   *
   * @return string|null
   *   Empty string if ETag matches (304 will be sent), NULL to proceed.
   */
  private function checkEtag(string $displayId): ?string {
    $bundleMap = [
      'articles_rest_export' => 'article',
      'video_article_rest_export' => 'video_article',
      'activities_rest_export' => 'activity',
      'faq_rest_export' => 'faq',
      'basic_page_rest_export' => 'basic_page',
    ];

    $bundle = $bundleMap[$displayId] ?? NULL;
    if ($bundle === NULL) {
      return NULL;
    }

    $request = $this->requestStack->getCurrentRequest();
    if ($request === NULL) {
      return NULL;
    }

    $langcode = $this->view->args[0]
      ?? $this->languageManager->getCurrentLanguage()->getId();

    $signature = $this->database->query(
      "SELECT CONCAT(MAX(changed), ':', COUNT(*)) FROM {node_field_data} WHERE type = :type AND status = 1 AND langcode = :lang",
      [':type' => $bundle, ':lang' => $langcode]
    )->fetchField();

    $etag = '"' . md5($bundle . ':' . $signature . ':' . $request->getQueryString()) . '"';
    $request->attributes->set('bebbo_etag', $etag);

    $ifNoneMatch = $request->headers->get('If-None-Match');
    if ($ifNoneMatch === $etag) {
      $request->attributes->set('bebbo_etag_match', TRUE);
      return '';
    }

    return NULL;
  }

}
