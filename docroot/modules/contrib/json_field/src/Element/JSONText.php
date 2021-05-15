<?php

namespace Drupal\json_field\Element;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\Element\RenderElement;
use Drupal\json_field\JsonMarkup;

/**
 * Provides a json text render element.
 *
 * @RenderElement("json_text")
 */
class JSONText extends RenderElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return array(
      '#text' => '',
      '#langcode' => '',
      '#pre_render' => [[$class, 'preRenderText']],
    );
  }

  /**
   * Pre-render callback: Renders a json text element into #markup.
   *
   * @todo Add JSON formatting libraries.
   */
  public static function preRenderText($element) {
    // Create the render array.
    $markup_element = [
      '#markup' => new FormattableMarkup('<pre><code>@json</code></pre>', ['@json' => JsonMarkup::create($element['#text'])]),
    ];

    return $markup_element;
  }

}
