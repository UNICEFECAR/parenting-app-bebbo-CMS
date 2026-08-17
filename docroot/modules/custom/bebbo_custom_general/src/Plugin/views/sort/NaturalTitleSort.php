<?php

namespace Drupal\bebbo_custom_general\Plugin\views\sort;

use Drupal\views\Attribute\ViewsSort;
use Drupal\views\Plugin\views\query\Sql;
use Drupal\views\Plugin\views\sort\SortPluginBase;

/**
 * Sorts a text column alphabetically regardless of language, numbers last.
 *
 * The stored collation (utf8mb4_general_ci) only folds case for ASCII, so
 * accented letters sort after Z, leading whitespace skews the order and
 * numeric titles bubble to the top. This handler orders on a Unicode
 * collation instead and pushes titles that start with a digit to the end of
 * an ascending list.
 */
#[ViewsSort("bebbo_natural_title")]
class NaturalTitleSort extends SortPluginBase {

  /**
   * Collation used for the alphabetical comparison.
   *
   * Deliberately not one of MariaDB's utf8mb4_uca1400_* collations: those do
   * not exist on MySQL, and an unknown collation is a fatal SQL error rather
   * than a degraded sort.
   */
  public const COLLATION = 'utf8mb4_unicode_520_ci';

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    static::orderBy($this->query, $this->tableAlias . '.' . $this->realField, $this->options['order']);
  }

  /**
   * Adds the language-neutral ordering for a text column to a query.
   *
   * Shared with bebbo_custom_general_views_query_alter(), which applies the
   * same ordering to table column header click sorts.
   *
   * @param \Drupal\views\Plugin\views\query\Sql $query
   *   The query to order.
   * @param string $field
   *   Fully qualified column, for example "node_field_revision.title".
   * @param string $direction
   *   Either ASC or DESC.
   */
  public static function orderBy(Sql $query, string $field, string $direction): void {
    $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

    // Titles are routinely wrapped in quotes or opened with punctuation, which
    // would otherwise bunch them all together ahead of the alphabet. Drop any
    // leading non-word characters, whitespace included, before comparing.
    // "\W" is Unicode aware here, so accented Latin and Cyrillic letters are
    // kept; under NO_BACKSLASH_ESCAPES the pattern simply matches nothing and
    // the ordering degrades to the untrimmed title rather than breaking.
    $clean = "REGEXP_REPLACE($field, '^\\\\W+', '')";

    // Deliberately not a REGEXP: Drupal treats square brackets in a query
    // string as identifier quoting, so a '^[0-9]' pattern reaches the database
    // as '^"0-9"' and never matches.
    $query->addOrderBy(NULL, "(LEFT($clean, 1) BETWEEN '0' AND '9')", $direction, 'bebbo_title_numeric');
    $query->addOrderBy(NULL, $clean . ' COLLATE ' . static::COLLATION, $direction, 'bebbo_title_natural');
  }

}
