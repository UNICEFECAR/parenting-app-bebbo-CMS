<?php

namespace Drupal\json_field;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Render\MarkupTrait;

/**
 * Provides a markup render plugin that supports JSON.
 *
 * @package Drupal\json_field
 */
class JsonMarkup implements MarkupInterface {

  use MarkupTrait;

}
