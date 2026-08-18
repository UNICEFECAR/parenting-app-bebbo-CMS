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
   * Leading characters dropped before titles are compared.
   *
   * Whitespace and the punctuation editors habitually open a title with. Kept
   * to a fixed list on purpose: see cleanExpression() for why a regular
   * expression is not an option.
   */
  protected const LEADING_NOISE = [
    ' ', "\t", "\n", "\r",
    '"', "'", '«', '“', '‘', '‚', '(', '[', '{',
    '-', '–', '—', '.', '…', '*', '#', '¿', '¡',
  ];

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

    $clean = static::cleanExpression($field);

    // Deliberately not a REGEXP: Drupal treats square brackets in a query
    // string as identifier quoting, so a '^[0-9]' pattern reaches the database
    // as '^"0-9"' and never matches.
    $query->addOrderBy(NULL, "(LEFT($clean, 1) BETWEEN '0' AND '9')", $direction, 'bebbo_title_numeric');
    $query->addOrderBy(NULL, $clean . ' COLLATE ' . static::COLLATION, $direction, 'bebbo_title_natural');
  }

  /**
   * Builds an expression stripping leading noise from a text column.
   *
   * Deliberately nested TRIM() calls rather than a regular expression: the
   * only portable replacement function, REGEXP_REPLACE(), does not exist on
   * MySQL before 8.0.4, and Acquia's environments crash on it where local
   * MariaDB does not. Character classes are out for the same reason, since
   * MySQL 5.7 ships the POSIX engine that has no "\W", and Drupal reads square
   * brackets in a query string as identifier quoting so "[[:punct:]]" never
   * reaches the database intact either.
   *
   * TRIM(LEADING) removes repeats of a single character, so the list is
   * applied twice: one pass alone would leave, say, a quote stranded behind
   * the spaces of ' "Title' or the reverse.
   *
   * @param string $field
   *   Fully qualified column, for example "node_field_revision.title".
   *
   * @return string
   *   SQL expression yielding the comparable part of the title.
   */
  protected static function cleanExpression(string $field): string {
    $expression = $field;
    for ($pass = 0; $pass < 2; $pass++) {
      foreach (static::LEADING_NOISE as $character) {
        $literal = "'" . str_replace(["\\", "'"], ["\\\\", "''"], $character) . "'";
        $expression = "TRIM(LEADING $literal FROM $expression)";
      }
    }
    return $expression;
  }

}
