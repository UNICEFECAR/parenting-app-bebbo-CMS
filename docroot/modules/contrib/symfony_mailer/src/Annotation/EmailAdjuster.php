<?php

namespace Drupal\symfony_mailer\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an EmailAdjuster item annotation object.
 *
 * @Annotation
 */
class EmailAdjuster extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * Human-readable label of the plugin.
   *
   * @var string
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * Human-readable description of the plugin.
   *
   * @var string
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * Whether the plugin is automatically run for all emails.
   *
   * @var bool
   */
  public $automatic = FALSE;

  /**
   * The plugin weight.
   *
   * The array key is the phase (EmailInterface::PHASE_*) and the value is the
   * weight for that phase. Lower weights are executed first. The annotation
   * may specify a single integer that applies to all phases, and this will be
   * automatically converted to an array.
   *
   * @var int|int[]
   */
  public $weight;

}
