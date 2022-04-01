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
  public  function render($param1,$param2,$param3) {
    return [
      '#theme' => 'pb-mobile',
    ];
  }
  /**
   * Display the Kosovo-mobile.
   *
   * @return array
   *   Return kosovo-mobile array.
   */
  public  function kosovorender($param1,$param2,$param3) {
    return [
      '#theme' => 'kosovo-mobile',
    ];
  }

}
