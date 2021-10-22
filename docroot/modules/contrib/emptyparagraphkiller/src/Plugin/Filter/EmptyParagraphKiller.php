<?php

namespace Drupal\emptyparagraphkiller\Plugin\Filter;

use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * @Filter(
 *   id = "emptyparagraphkiller",
 *   title = @Translation("Empty Paragraph filter"),
 *   description = @Translation("When entering more than one carriage return, only the first will be honored."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_MARKUP_LANGUAGE,
 * )
 */
class EmptyParagraphKiller extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function prepare($text, $langcode) {
    return preg_replace('#<p[^>]*>(\s|&nbsp;?)*</p>#', '[empty-para]', $text);
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $text = str_replace('[empty-para]', '', $text);
    return new FilterProcessResult($text);
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    if ($long) {
      return $this->t("Your typing habits may include hitting the return key twice when completing a paragraph. This site will accommodate your habit, and ensure the content is in keeping with the the stylistic formatting of the site's theme.");
    }
    return $this->t("Empty paragraph killer - multiple returns will not break the site's style.");
  }

}
