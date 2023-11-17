<?php

namespace Drupal\tb_megamenu\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Handler for attaching columns to MegaMenu render arrays.
 */
class TBMegaMenuController extends ControllerBase implements TrustedCallbackInterface {

  /**
   * Attach the number of columns into JS.
   *
   * @throws \Exception
   */
  public static function tbMegamenuAttachNumberColumns($childrens, $elements) {
    $number_columns = &drupal_static('column');
    $render_array = [];
    $render_array['#attached']['drupalSettings']['TBMegaMenu'] = [
      'TBElementsCounter' => ['column' => $number_columns],
    ];

    // Can't use DI here since it's invoked by the static method below.
    \Drupal::service('renderer')->render($render_array);

    return $childrens;
  }

  /**
   * {@inheritDoc}
   */
  public static function trustedCallbacks(): array {
    return ['tbMegamenuAttachNumberColumns'];
  }

}
