<?php

declare(strict_types=1);

namespace Drupal\bebbo_serializer\Plugin\views\cache;

use Drupal\bebbo_serializer\Cache\ApiCacheTags;
use Drupal\Core\Cache\Cache;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsCache;
use Drupal\views\Plugin\views\cache\Tag;

/**
 * Tag-based caching scoped to the listing's bundles and language.
 *
 * Core's tag plugin hands the response one cache tag per loaded entity plus
 * the entity type's list tag. On the API listings that is close to a thousand
 * tags — 938 on /api/articles/ro-ro, 1241 on the English one — and node_list
 * among them means any node save anywhere expires every API response on the
 * site, including the 40-odd endpoints that could not contain the saved node.
 *
 * This narrows the response to one tag per bundle the display lists, in the
 * language it was built for. Per-row invalidation is not lost: the row
 * fragments carry their own language-scoped tags, so an edit re-renders the
 * row it touched and leaves the rest of the listing cached.
 *
 * The plugin keeps core's behaviour whenever it cannot establish what the
 * display lists — an unknown bundle set or a language it does not recognise
 * would otherwise trade staleness for speed.
 *
 * @see \Drupal\bebbo_serializer\Cache\RowFragmentCache
 * @see \Drupal\views\Plugin\views\query\Sql::getCacheTags()
 */
#[ViewsCache(
  id: 'bebbo_api_tag',
  title: new TranslatableMarkup('Tag based (Bebbo API)'),
  help: new TranslatableMarkup('Tag based caching scoped to the listed bundles and the requested language.'),
)]
class BebboApiTag extends Tag {

  /**
   * {@inheritdoc}
   */
  public function summaryTitle() {
    return $this->t('Bebbo API tag');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $bundles = $this->listedBundles();
    $langcode = $this->requestedLangcode();

    if (!$bundles || $langcode === NULL) {
      return parent::getCacheTags();
    }

    // media_list stays: the row fragments record the file entities they
    // render but not the media entities wrapping them, so without it a media
    // edit would leave both layers serving the old image for the full TTL.
    return Cache::mergeTags(
      $this->view->storage->getCacheTags(),
      array_merge(ApiCacheTags::listTags($bundles, $langcode), ['media_list']),
    );
  }

  /**
   * The node bundles this display lists.
   *
   * Read from the display's bundle filter rather than from the result set:
   * a listing that is currently empty still has to expire when its first
   * node is published.
   *
   * @return string[]
   *   Bundle machine names, empty when the display has no bundle filter.
   */
  protected function listedBundles(): array {
    $filters = $this->view->display_handler->getOption('filters') ?? [];
    $value = $filters['type']['value'] ?? NULL;

    return is_array($value) ? array_values($value) : [];
  }

  /**
   * The language this display was built for.
   *
   * Every API display takes the langcode as its first argument. Anything else
   * — a missing argument, or one that is not a language on this site — means
   * the assumption does not hold here.
   *
   * @return string|null
   *   The langcode, or NULL when it cannot be established.
   */
  protected function requestedLangcode(): ?string {
    $langcode = $this->view->args[0] ?? NULL;
    if (!is_string($langcode) || $langcode === '') {
      return NULL;
    }

    return isset(\Drupal::languageManager()->getLanguages()[$langcode]) ? $langcode : NULL;
  }

}
