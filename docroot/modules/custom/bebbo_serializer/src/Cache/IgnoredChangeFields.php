<?php

declare(strict_types=1);

namespace Drupal\bebbo_serializer\Cache;

/**
 * Fields that a node save may change without any API response going stale.
 *
 * Deciding which cache tags to expire means comparing the saved node against
 * its original, field by field. Bookkeeping that Drupal rewrites on every save
 * would report a change every time and the comparison would decide nothing, so
 * it is excluded here.
 *
 * @see bebbo_serializer_node_update()
 */
final class IgnoredChangeFields {

  /**
   * Fields excluded from the untranslatable-change comparison.
   *
   * Untranslatable values are shared by every translation, so a change to one
   * has to expire all languages. These are the exceptions: revision
   * bookkeeping that changes on every save regardless of content, and counters
   * that no API response carries. Comparing them would expire all 28 languages
   * on every save and on every analytics run, which is exactly what this is
   * meant to avoid.
   */
  public const UNTRANSLATABLE = [
    'vid',
    'revision_timestamp',
    'revision_uid',
    'revision_log',
    'revision_default',
    'feeds_item',
    'field_analytics_updated',
    'field_like_count',
    'field_read_count',
  ];

  /**
   * Fields excluded from the per-translation comparison.
   *
   * Bookkeeping that Drupal rewrites on every save, in every language, whether
   * or not the editor touched that translation. Left in, they would report all
   * 28 languages as changed on any save. Computed fields are skipped
   * separately: they are derived on load rather than stored, so the saved
   * entity and the original disagree by construction — "path" and
   * "moderation_state" both do.
   *
   * @see _bebbo_serializer_translation_changed()
   */
  public const TRANSLATABLE = [
    'revision_translation_affected',
    'content_translation_source',
    'content_translation_outdated',
  ];

}
