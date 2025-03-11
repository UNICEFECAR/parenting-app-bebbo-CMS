<?php

namespace Drupal\custom_article\Commands;

use Drush\Commands\DrushCommands;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
/**
 * A Drush commandfile.
 */
class CopyKeyword extends DrushCommands {

  /**
   * @param string $node_type
   * The content type machine name.
   * @param int $offset
   * The starting point for processing nodes.
   * @command custom-article:copy-keyword
   * @aliases copy-keyword
   */
  public function MetaKeywordsUpdate($node_type, $offset) {
    // Load all nodes of type Article and Video Article.

    $nids = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('type', $node_type)
      ->sort('nid', 'ASC')
      ->range($offset, 200)
      ->execute();
    //   $count = $nids->count()->execute();
    foreach ($nids as $nid) {
    
      $node = Node::load($nid);
      // Get all translations.
      $translations = $node->getTranslationLanguages();
      foreach ($translations as $langcode => $language) {
        $translated_node = $node->getTranslation($langcode);
        $keywords = $translated_node->get('field_keywords')->getValue();
        if (!empty($keywords)) {
          $keyword_list = [];
          foreach ($keywords as $keyword) {
            $term = Term::load($keyword['target_id']);
            if ($term) {
                // Get the translated term.
                if ($term->hasTranslation($langcode)) {
                  $translated_term = $term->getTranslation($langcode);
                  $keyword_list[] = trim($translated_term->getName());
                } else {
                  $keyword_list[] = trim($term->getName());
                }
            }
          }
           
          // Apply mb_lcfirst to each keyword in the list
          $keyword_list = array_map(function($keyword) {
            return $this->mb_lcfirst($keyword);
          }, $keyword_list);

          $meta_keywords = implode(', ', $keyword_list);
          $translated_node->set('field_meta_keywords', $meta_keywords);
          $translated_node->save();
        }
      }
      $this->logger()->success(dt('Processing node ID: @nid', ['@nid' => $nid]));
    }
}

  function mb_lcfirst($string, $encoding = 'UTF-8') {
    $firstChar = mb_substr($string, 0, 1, $encoding);
    $rest = mb_substr($string, 1, null, $encoding);
    return mb_strtolower($firstChar, $encoding) . $rest;
  }
}
