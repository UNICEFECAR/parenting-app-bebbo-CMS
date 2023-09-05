<?php

namespace Drupal\json_field\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Plugin\Field\FieldWidget\StringTextareaWidget;

/**
 * Plugin implementation of the 'json_textarea' widget.
 *
 * @FieldWidget(
 *   id = "json_textarea",
 *   label = @Translation("Plain textarea (multiple rows)"),
 *   field_types = {
 *     "json",
 *     "json_native",
 *     "json_native_binary"
 *   }
 * )
 */
class JsonTextareaWidget extends StringTextareaWidget {
}
