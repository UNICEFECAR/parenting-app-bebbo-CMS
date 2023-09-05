<?php

namespace Drupal\json_field\Element;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Render\Element\RenderElement;
use Drupal\json_field\JsonMarkup;

/**
 * Provides a JSON text render element.
 *
 * @RenderElement("json_text")
 */
class JsonText extends RenderElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#text' => '',
      '#langcode' => '',
      '#pre_render' => [[$class, 'preRenderText']],
    ];
  }

  /**
   * Pre-render callback: Renders a JSON text element into #markup.
   *
   * @todo Add JSON formatting libraries.
   */
  public static function preRenderText($element) {
    // Create the render array.
    $markup_element = [
      '#markup' => new FormattableMarkup('<pre class="json-field"><code>@json</code></pre>', ['@json' => JsonMarkup::create($element['#text'])]),
    ];

    return $markup_element;
  }

}
