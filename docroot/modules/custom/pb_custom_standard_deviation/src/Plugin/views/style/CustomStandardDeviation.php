<?php

namespace Drupal\pb_custom_standard_deviation\Plugin\views\style;

use Drupal\rest\Plugin\views\style\Serializer;

/**
 * Placeholder retaining the pb_custom_standard_deviation style plugin ID.
 *
 * The original implementation was removed. Sites that have not yet imported the
 * configuration removing this module still reference this plugin ID, and Drupal
 * cannot boot far enough to uninstall a module whose plugins no longer resolve.
 * This stub keeps discovery working for that uninstall and carries no behaviour
 * of its own.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "pb_custom_standard_deviation",
 *   title = @Translation("Custom standard deviation"),
 *   help = @Translation("Deprecated. Retained only for uninstall."),
 *   display_types = {"data"}
 * )
 */
class CustomStandardDeviation extends Serializer {

}
