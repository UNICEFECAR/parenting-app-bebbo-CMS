<?php

namespace Drupal\custom_serialization\Plugin\views\style;

use Drupal\rest\Plugin\views\style\Serializer;

/**
 * Placeholder retaining the custom_serialization style plugin ID.
 *
 * The original implementation was superseded by bebbo_serializer and removed.
 * Sites that have not yet imported the configuration removing this module still
 * reference this plugin ID, and Drupal cannot boot far enough to uninstall a
 * module whose plugins no longer resolve. This stub keeps discovery working for
 * that uninstall and carries no behaviour of its own.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "custom_serialization",
 *   title = @Translation("Custom serialization"),
 *   help = @Translation("Deprecated. Superseded by the Bebbo serializer."),
 *   display_types = {"data"}
 * )
 */
class CustomSerializer extends Serializer {

}
