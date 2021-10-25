<?php

namespace Drupal\pb_custom_form\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Defines PbMobile class.
 */
class PbMobile extends ControllerBase {

  /**
   * Display the pb-mobile.
   *
   * @return array
   *   Return pb-mobile array.
   */
  public static function render() {
    return [
      '#theme' => 'pb-mobile',
    ];
  }

}
