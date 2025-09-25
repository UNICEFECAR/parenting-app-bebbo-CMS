<?php

namespace Drupal\custom_article\Commands;

use Drush\Commands\DrushCommands;
use Drupal\node\NodeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides Drush commands for copying and updating meta keywords on nodes.
 */
class CopyKeyword extends DrushCommands {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new CopyKeyword object.
   */
  public function __construct() {
    parent::__construct();
  }

  /**
   * Get the entity type manager service.
   */
  protected function getEntityTypeManager(): EntityTypeManagerInterface {
    if (!isset($this->entityTypeManager)) {
      $this->entityTypeManager = \Drupal::entityTypeManager();
    }
    return $this->entityTypeManager;
  }

  /**
   * Copies and updates meta keywords for nodes of a given type.
   *
   * @param string $node_type
   *   The content type machine name.
   * @param int $offset
   *   The starting point for processing nodes.
   *
   * @command custom-article:copy-keyword
   * @aliases copy-keyword
   */
  public function metaKeywordsUpdate(string $node_type, int $offset = 0): void {
    $query = $this->getEntityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $node_type)
      ->sort('nid', 'ASC')
      ->range($offset, 200);

    $nids = $query->execute();

    if (empty($nids)) {
      $this->logger()->notice('No nodes found for type: @type', ['@type' => $node_type]);
      return;
    }

    $node_storage = $this->getEntityTypeManager()->getStorage('node');
    $term_storage = $this->getEntityTypeManager()->getStorage('taxonomy_term');

    foreach ($nids as $nid) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = $node_storage->load($nid);
      if (!$node instanceof NodeInterface) {
        continue;
      }

      foreach ($node->getTranslationLanguages() as $langcode => $language) {
        $translated_node = $node->getTranslation($langcode);
        $keywords = $translated_node->get('field_keywords')->getValue();

        if (empty($keywords)) {
          continue;
        }

        $keyword_list = [];
        foreach ($keywords as $keyword_item) {
          $term = $term_storage->load($keyword_item['target_id']);
          if (!$term) {
            continue;
          }

          if ($term->hasTranslation($langcode)) {
            $term = $term->getTranslation($langcode);
          }

          $keyword_list[] = $this->mbLcfirst(trim($term->getName()));
        }

        if (!empty($keyword_list)) {
          $meta_keywords = implode(', ', $keyword_list);
          $translated_node->set('field_meta_keywords', $meta_keywords);
          $translated_node->save();
        }
      }

      $this->logger()->success(dt('Processed node ID: @nid', ['@nid' => $nid]));
    }
  }

  /**
   * Makes the first character of a string lowercase (multibyte safe).
   *
   * @param string $string
   *   The input string.
   * @param string $encoding
   *   The character encoding.
   *
   * @return string
   *   The string with the first character in lowercase.
   */
  protected function mbLcfirst(string $string, string $encoding = 'UTF-8'): string {
    $firstChar = mb_substr($string, 0, 1, $encoding);
    $rest = mb_substr($string, 1, NULL, $encoding);
    return mb_strtolower($firstChar, $encoding) . $rest;
  }

}
