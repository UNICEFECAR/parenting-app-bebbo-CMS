<?php

namespace Drupal\csp\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a CSP Response Handler Annotation object.
 *
 * @Annotation
 */
class CspReportingHandler extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The plugin label.
   *
   * @var string
   */
  public $label;

  /**
   * The plugin description.
   *
   * @var string
   */
  public $description;

}
