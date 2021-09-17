<?php

namespace Drupal\pb_custom_rest_api\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;

/**
 * Provides a Custom Rest Resource.
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

  /**
   * Responds to GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   */
  public function get($country = NULL) {
    $database = \Drupal::database();
    $query = $database->query("SELECT * FROM {forcefull_check_update_api} WHERE country_id = $country ORDER BY id DESC LIMIT 1");
    $result = $query->fetchAll();
    if (!empty($result)) {
      $response_array['status'] = 200;
      $response_array['flag'] = (int) $result[0]->flag;
      $response_array['updated_at'] = $result[0]->updated_at;
    }
    else {
      $response_array['status'] = 204;
      $response_array['message'] = "No Records Found";
    }
    return new ResourceResponse($response_array);
  }

}
