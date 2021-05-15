<?php

namespace Drupal\json_field;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Render\MarkupTrait;
use Zend\Stdlib\JsonSerializable;

/**
 *
 */
class JsonMarkup implements MarkupInterface, JsonSerializable {

  use MarkupTrait;

}
