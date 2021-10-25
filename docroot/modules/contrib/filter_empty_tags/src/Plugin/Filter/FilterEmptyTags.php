<?php

namespace Drupal\filter_empty_tags\Plugin\Filter;

use Drupal\filter\Annotation\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * @Filter(
 *   id = "filter_empty_tags",
 *   title = @Translation("Filter Empty Tags"),
 *   description = @Translation("Recursively remove empty tags."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_HTML_RESTRICTOR,
 * )
 */
class FilterEmptyTags extends FilterBase {

  public function process($text, $langcode) {

    //** Return if string not given or empty.
    if (!is_string ($text) || trim ($text) == '')
      return new FilterProcessResult($text);

    //** Recursive empty HTML tags.
    $text = preg_replace (
      //** Pattern to match empty tags.
      '/<([^<\/>]*)>([\s]*?|(?R))<\/\1>/imsU',
      //** Replace with nothing.
      '',
      //** Source string
      $text
    );

    return new FilterProcessResult($text);
  }

}
