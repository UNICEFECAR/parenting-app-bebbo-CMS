<?php

namespace Drupal\pb_custom_form\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Database\Database;

/* use Symfony\Component\HttpFoundation\Request;
use Drupal\user\Entity\User; */

/**
 * An pb_custom_form controller.
 */
class ForceCountrySaveController extends ControllerBase {

  /**
   * Returns a render-able array for a test page.
   */
  public function content() {
    global $base_url;

    /* $country_id = \Drupal::request()->query->get('country_id');
    $flag = \Drupal::request()->query->get('flag'); */
    $request = $this->getRequest();
    $country_id = $request->query->get('country_id');
    $flag = $request->query->get('flag');
    /* $uid = \Drupal::currentUser()->id();
    $user = User::load($uid); */
    $uid = $this->currentUser()->id();
    $user = $this->entityTypeManager()->getStorage('user')->load($uid);
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
  }

}
