<?php

namespace Drupal\tb_megamenu;

use Drupal\Core\Block\BlockPluginInterface;

/**
 * Extends the block plugins interface.
 *
 * @ingroup block_api
 */
interface TBMegaMenuBlockPluginInterface extends BlockPluginInterface {

  /**
   * A function that returns the theme name as a string.
   *
   * @return string
   *   The theme name.
   */
  public function getThemeName(): string;

}
