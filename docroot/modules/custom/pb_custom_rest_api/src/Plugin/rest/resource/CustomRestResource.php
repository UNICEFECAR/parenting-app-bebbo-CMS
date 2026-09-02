<?php

namespace Drupal\pb_custom_rest_api\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;

/**
 * Placeholder retaining the custom_rest_resource plugin ID.
 *
 * The original implementation was superseded by bebbo_serializer and removed.
 * Sites that have not yet imported the configuration removing this module still
 * reference this plugin ID, and Drupal cannot boot far enough to uninstall a
 * module whose plugins no longer resolve. This stub keeps discovery working for
 * that uninstall and exposes no methods, so it serves no routes.
 *
 * @RestResource(
 *   id = "custom_rest_resource",
 *   label = @Translation("Custom Rest Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/check-update/{country}"
 *   }
 * )
 */
class CustomRestResource extends ResourceBase {

}
