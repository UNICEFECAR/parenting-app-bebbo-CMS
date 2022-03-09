<?php

namespace Drupal\pb_custom_form\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Database\Database;


/**
 * An pb_custom_form controller.
 */
class ForceCountrySaveController extends ControllerBase {

  /**
   * Returns a render-able array for a test page.
   */
  public function content() {
   global $base_url;

   $country_id = \Drupal::request()->query->get('country_id'); 
   $flag = \Drupal::request()->query->get('flag');
   $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
   $uuid = $user->uuid();
   $date = new DrupalDateTime();
   $conn = Database::getConnection();
   $conn->insert('forcefull_check_update_api')->fields(
      [
        'flag' => $flag,
        'country_id' => $country_id,
        'updated_at' => $date->getTimestamp(),
        'uuid' => $uuid,
        'created_at' => $date->getTimestamp(),
      ]
    )->execute();
   drupal_flush_all_caches();
   $path = $base_url . '/admin/config/parent-buddy/forcefull-update-check';
     my_goto($path);

    // $build = [
    //   '#markup' => 'insert seccefully',
    // ];
    // return $build;
  }

}
