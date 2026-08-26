<?php

namespace Drupal\bebbo_serializer\Plugin\views\style;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\file\FileInterface;

/**
 * Shared row-transformation helpers for the Bebbo Views serializers.
 *
 * Pure, stateless utilities (type casting, media/HTML parsing, language
 * resolution) used by both BebboSerializer (V2) and BebboV1Serializer (V1).
 * Keeping them here avoids duplicating ~300 lines across the two style
 * plugins.
 *
 * Service-dependent helpers in this trait read injected properties from the
 * using class. Any class using this trait MUST declare these properties:
 *
 * @property \Symfony\Component\HttpFoundation\RequestStack $requestStack
 * @property \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
 * @property \Drupal\Core\Language\LanguageManagerInterface $languageManager
 * @property \Drupal\Core\Database\Connection $database
 */
trait BebboSerializerHelpers {

  /**
   * Resolves the response language code.
   *
   * Checks view arguments first (URL path segment such as /en or /ro),
   * then falls back to the currently active site language.
   *
   * @return string
   *   A BCP-47 language tag, e.g. "en" or "ro".
   */
  private function resolveLangcode(): string {
    // View arguments carry URL path segments (set via contextual filter).
    if (!empty($this->view->args[0])) {
      $candidate = $this->view->args[0];
      if ($this->languageManager->getLanguage($candidate) !== NULL) {
        return $candidate;
      }
    }

    return $this->languageManager->getCurrentLanguage()->getId();
  }

  /**
   * Casts the given row fields to integers.
   *
   * Missing or empty values default to 0.
   *
   * @param array $row
   *   A single row (passed by reference).
   * @param array $fields
   *   Field names to cast.
   */
  private function castToInt(array &$row, array $fields): void {
    foreach ($fields as $field) {
      if (array_key_exists($field, $row)) {
        $row[$field] = (int) ($row[$field] ?? 0);
      }
    }
  }

  /**
   * Overwrites an integer row value from the entity translation.
   *
   * Views field joins on translatable fields can resolve the wrong
   * translation, so the value is read directly from the loaded entity's
   * translation for the response language (falling back to the entity's
   * default translation). Rows must be aligned by index with
   * $this->view->result.
   *
   * @param array $rows
   *   Transformed rows (passed by reference), index-aligned with the view
   *   result set.
   * @param string $fieldName
   *   The entity field machine name, e.g. "field_old_calendar".
   * @param string $rowKey
   *   The row array key to overwrite, e.g. "old_calendar".
   */
  private function overrideIntFromTranslation(array &$rows, string $fieldName, string $rowKey): void {
    $langcode = $this->resolveLangcode();
    $results = array_values($this->view->result);

    foreach ($rows as $index => &$row) {
      $entity = $results[$index]->_entity ?? NULL;
      if (!$entity instanceof FieldableEntityInterface || !$entity->hasField($fieldName)) {
        continue;
      }
      if ($entity instanceof TranslatableInterface && $entity->hasTranslation($langcode)) {
        $entity = $entity->getTranslation($langcode);
      }
      $row[$rowKey] = (int) $entity->get($fieldName)->value;
    }
    unset($row);
  }

  /**
   * Casts the given row fields to booleans.
   *
   * Treats "1", "True", "true" as TRUE; everything else as FALSE.
   *
   * @param array $row
   *   A single row (passed by reference).
   * @param array $fields
   *   Field names to cast.
   */
  private function castToBool(array &$row, array $fields): void {
    foreach ($fields as $field) {
      if (array_key_exists($field, $row)) {
        $row[$field] = filter_var(
          $row[$field],
          FILTER_VALIDATE_BOOLEAN
        );
      }
    }
  }

  /**
   * Casts row fields to their natural numeric type without rounding.
   *
   * @param array $row
   *   A single row (passed by reference).
   * @param array $fields
   *   Field names to cast.
   */
  private function castToNumber(array &$row, array $fields): void {
    foreach ($fields as $field) {
      if (array_key_exists($field, $row)) {
        // Adding 0 preserves the natural int/float type, but PHP 8 throws a
        // TypeError on non-numeric strings (e.g. an empty decimal field that
        // Views renders as ''). Guard with is_numeric() and default to 0.
        $row[$field] = is_numeric($row[$field]) ? $row[$field] + 0 : 0;
      }
    }
  }

  /**
   * Converts row fields to deduplicated integer arrays.
   *
   * Handles both raw arrays (raw_output:true) and comma-separated strings
   * (raw_output:false) from the Views row plugin.
   *
   * @param array $row
   *   A single row (passed by reference).
   * @param array $fields
   *   Field names to convert.
   */
  private function toIntArray(array &$row, array $fields): void {
    foreach ($fields as $field) {
      if (!array_key_exists($field, $row)) {
        continue;
      }
      $raw = $row[$field] ?? [];
      if (is_array($raw)) {
        $ids = $raw;
      }
      else {
        $ids = array_filter(
          array_map('trim', explode(',', (string) $raw)),
          'is_numeric'
        );
      }
      $row[$field] = array_values(array_unique(array_map('intval', $ids)));
    }
  }

  /**
   * Converts comma-separated string fields to string arrays.
   *
   * Splits on comma, trims whitespace, removes empty values.
   *
   * @param array $row
   *   A single row (passed by reference).
   * @param array $fields
   *   Field names to convert.
   */
  private function toStringArray(array &$row, array $fields): void {
    foreach ($fields as $field) {
      if (!array_key_exists($field, $row)) {
        continue;
      }
      $raw = $row[$field] ?? [];
      if (is_array($raw)) {
        $values = $raw;
      }
      else {
        $values = array_map('trim', explode(',', (string) $raw));
      }
      $row[$field] = array_values(array_filter($values, fn($v) => $v !== ''));
    }
  }

  /**
   * Decodes HTML entities in the given row fields.
   *
   * @param array $row
   *   A single row (passed by reference).
   * @param array $fields
   *   Field names to decode.
   */
  private function decodeHtmlEntities(array &$row, array $fields): void {
    foreach ($fields as $field) {
      if (!empty($row[$field])) {
        $row[$field] = htmlspecialchars_decode((string) $row[$field], ENT_QUOTES | ENT_HTML5);
      }
    }
  }

  /**
   * Parses a cover_video view field HTML to {url, name, site}.
   *
   * The embedded media_details view renders unformatted HTML with
   * labeled fields. An empty media reference has no "view-content"
   * wrapper.
   *
   * @param mixed $raw
   *   The raw field value (HTML string from the embedded view).
   *
   * @return array
   *   Resolved {url, name, site}, or empty strings on failure.
   */
  private function parseViewVideoMedia(mixed $raw): array {
    $empty = ['url' => '', 'name' => '', 'site' => ''];

    if (empty($raw) || !is_string($raw)) {
      return $empty;
    }

    if (!str_contains($raw, 'view-content')) {
      return $empty;
    }

    // Match </span> or </div> — the field-content wrapper element varies
    // by theme and display settings. Using only </span> causes the lazy
    // capture to overshoot into the next field's label text.
    $closingTag = '<\/(?:span|div)>';

    $url = '';
    $pattern = '/views-field-field-media-oembed-video"'
      . '.*?field-content[^>]*>(.*?)' . $closingTag . '/s';
    if (preg_match($pattern, $raw, $m)) {
      $url = trim(strip_tags($m[1]));
    }

    $name = '';
    if (preg_match('/views-field-name.*?field-content[^>]*>(.*?)' . $closingTag . '/s', $raw, $m)) {
      $name = trim(strip_tags($m[1]));
    }

    $site = '';
    $sitePattern = '/views-field-field-media-oembed-video-1'
      . '.*?field-content[^>]*>(.*?)' . $closingTag . '/s';
    if (preg_match($sitePattern, $raw, $m)) {
      $site = trim(strip_tags($m[1]));
    }

    return ['url' => $url, 'name' => $name, 'site' => $site];
  }

  /**
   * Parses a cover_image view field HTML to {url, name, alt}.
   *
   * The embedded media_details view renders unformatted HTML with
   * labeled fields. The thumbnail URL is root-relative and made
   * absolute using the current request's scheme and host.
   *
   * @param mixed $raw
   *   The raw field value (HTML string from the embedded view).
   *
   * @return array
   *   Resolved {url, name, alt}, or empty strings on failure.
   */
  private function parseViewCoverImage(mixed $raw): array {
    $empty = ['url' => '', 'name' => '', 'alt' => ''];

    if (empty($raw) || !is_string($raw)) {
      return $empty;
    }

    // No view-content wrapper means empty media reference.
    if (!str_contains($raw, 'view-content')) {
      return $empty;
    }

    // Match </span> or </div> — the field-content wrapper element varies.
    $closingTag = '<\/(?:span|div)>';

    $name = '';
    $namePattern = '/views-field-name'
      . '.*?field-content[^>]*>(.*?)' . $closingTag . '/s';
    if (preg_match($namePattern, $raw, $m)) {
      $name = trim(strip_tags($m[1]));
    }

    $url = '';
    $urlPattern = '/Thumbnail:\s*<\/(?:span|div)>'
      . '\s*<(?:span|div)[^>]*>(.*?)' . $closingTag . '/s';
    if (preg_match($urlPattern, $raw, $m)) {
      $url = trim(strip_tags($m[1]));
    }

    $alt = '';
    $altPattern = '/views-field-thumbnail__alt'
      . '.*?field-content[^>]*>(.*?)' . $closingTag . '/s';
    if (preg_match($altPattern, $raw, $m)) {
      $alt = trim(strip_tags($m[1]));
    }

    // Make the URL absolute if it is a root-relative path.
    if ($url !== '' && !str_starts_with($url, 'http')) {
      $request = $this->requestStack->getCurrentRequest();
      $url = ($request !== NULL
        ? $request->getSchemeAndHttpHost()
        : '') . $url;
    }

    return ['url' => $url, 'name' => $name, 'alt' => $alt];
  }

  /**
   * Batch-resolves media IDs to {url, name, alt} arrays.
   *
   * Loads media entities, extracts the image file, generates a styled URL
   * using the content_1200xh_ image style, and returns the media name and
   * alt text.
   *
   * @param array $mediaIds
   *   Array of media entity IDs.
   *
   * @return array
   *   Keyed by media ID, each value has url, name, and alt keys.
   */
  private function resolveMediaIds(array $mediaIds): array {
    $empty = ['url' => '', 'name' => '', 'alt' => ''];
    $mediaIds = array_filter(array_unique($mediaIds));
    if (empty($mediaIds)) {
      return [];
    }

    $mediaEntities = $this->entityTypeManager
      ->getStorage('media')
      ->loadMultiple($mediaIds);
    $imageStyle = $this->entityTypeManager
      ->getStorage('image_style')
      ->load('content_1200xh_');

    $resolved = [];
    foreach ($mediaIds as $id) {
      if (!isset($mediaEntities[$id]) || !$mediaEntities[$id]->hasField('field_media_image')) {
        $resolved[$id] = $empty;
        continue;
      }

      $media = $mediaEntities[$id];
      /** @var \Drupal\file\FileInterface|null $file */
      $file = $media->get('field_media_image')->entity;
      if (!$file instanceof FileInterface) {
        $resolved[$id] = $empty;
        continue;
      }

      $name = (string) ($media->get('name')->value ?? '');
      $imageField = $media->get('field_media_image')->getValue();
      $alt = (string) ($imageField[0]['alt'] ?? '');

      $fileUri = $file->getFileUri();

      // SVG/GIF — serve original URL without image style or WebP conversion.
      if (preg_match('/\.(svg|gif)$/i', $fileUri)) {
        $url = $this->fileUrlGenerator->generateAbsoluteString($fileUri);
        $resolved[$id] = ['url' => $url, 'name' => $name, 'alt' => $alt];
        continue;
      }

      $url = '';
      if ($imageStyle) {
        $url = $imageStyle->buildUrl($fileUri);
      }

      if ($url !== '' && !str_starts_with($url, 'http')) {
        $request = $this->requestStack->getCurrentRequest();
        $url = ($request !== NULL ? $request->getSchemeAndHttpHost() : '') . $url;
      }

      // Replace original extension with .webp before query string.
      if ($url !== '') {
        $url = preg_replace('/\.(jpe?g|png)(\?.*)?$/i', '.webp$2', $url) ?? $url;
      }

      $resolved[$id] = ['url' => $url, 'name' => $name, 'alt' => $alt];
    }

    return $resolved;
  }

  /**
   * Converts MarkupInterface objects to plain strings recursively.
   *
   * @param mixed $value
   *   A value, array, or MarkupInterface object.
   *
   * @return mixed
   *   The value with all MarkupInterface objects cast to strings.
   */
  private function normalizeMarkup(mixed $value): mixed {
    if ($value instanceof MarkupInterface) {
      return (string) $value;
    }
    if (is_array($value)) {
      return array_map([$this, 'normalizeMarkup'], $value);
    }
    return $value;
  }

  /**
   * Loads English node titles for the given node IDs.
   *
   * @param array $nids
   *   Node IDs.
   *
   * @return array
   *   Map of nid => English title.
   */
  private function getEnglishNodeTitles(array $nids): array {
    if (empty($nids)) {
      return [];
    }

    return $this->database->select('node_field_data', 'n')
      ->fields('n', ['nid', 'title'])
      ->condition('nid', array_filter(array_unique($nids)), 'IN')
      ->condition('langcode', 'en')
      ->execute()
      ->fetchAllKeyed(0, 1);
  }

  /**
   * Batch-loads embedded image URLs for the given node IDs and language.
   *
   * Reads field_embedded_images (auto-populated on node save from the body),
   * preserving delta order. Falls back to the English value when the
   * requested language has no row.
   *
   * @param int[] $nids
   *   Node IDs.
   * @param string $langcode
   *   The requested language code.
   *
   * @return array<int, string[]>
   *   Map of node ID to an ordered list of embedded image URLs.
   */
  private function resolveEmbeddedImagesByNid(array $nids, string $langcode): array {
    $nids = array_values(array_unique(array_filter($nids)));
    if (empty($nids)) {
      return [];
    }

    $records = $this->database->select('node__field_embedded_images', 'e')
      ->fields('e', ['entity_id', 'langcode', 'delta', 'field_embedded_images_value'])
      ->condition('entity_id', $nids, 'IN')
      ->condition('langcode', [$langcode, 'en'], 'IN')
      ->orderBy('delta')
      ->execute()
      ->fetchAll();

    // Prefer the requested language; fall back to English per node.
    $byLang = [];
    foreach ($records as $r) {
      $byLang[(int) $r->entity_id][$r->langcode][] = (string) $r->field_embedded_images_value;
    }

    $result = [];
    foreach ($byLang as $nid => $langs) {
      $result[$nid] = $langs[$langcode] ?? $langs['en'] ?? [];
    }
    return $result;
  }

  /**
   * Derives a stable machine name from a term label.
   *
   * Used where a vocabulary has no field_unique_name to read, and for terms
   * whose field_unique_name is empty. Callers pass the English label so the
   * result does not vary by language.
   *
   * @param string $name
   *   The term label.
   *
   * @return string
   *   Lowercase label with every non-alphanumeric run collapsed to a single
   *   underscore, e.g. "Article for birth to 6 years" becomes
   *   "article_for_birth_to_6_years".
   */
  private function slugifyTermName(string $name): string {
    $machine = strtolower($name);
    $machine = preg_replace('/[^a-z0-9]/', '_', $machine);
    $machine = preg_replace('/_+/', '_', $machine);
    return trim($machine, '_');
  }

}
