<?php

namespace Drupal\symfony_mailer\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an EmailBuilder item annotation object.
 *
 * @Annotation
 */
class EmailBuilder extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The type.
   *
   * This is the part of the ID before the dot. This value is set
   * automatically and should not be part of the annotation comment.
   *
   * @var string
   */
  public $type;

  /**
   * The sub-type.
   *
   * This is the part of the ID after the dot. Most often there is no dot, and
   * this is the empty string. This value is set automatically and should not
   * be part of the annotation comment.
   *
   * @var string
   */
  public $sub_type;

  /**
   * The human-readable name of the plugin.
   *
   * Leave blank to derive from an entity type or module matching the ID.
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $label = '';

  /**
   * Array of sub-types.
   *
   * The array key is the sub-type value and the value is the human-readable
   * label.
   *
   * @var string[]
   */
  public $sub_types = [];

  /**
   * Whether the plugin is associated with a config entity.
   *
   * @var bool
   */
  public $has_entity = FALSE;

  /**
   * Array of common adjuster IDs.
   *
   * @var string[]
   */
  public $common_adjusters = [];

  /**
   * Information about overriding emails for another module.
   *
   * The value is an array of email IDs to override. The annotation may set the
   * value TRUE which is automatically converted to an single-value array
   * containing the plugin ID.
   *
   * @var bool|string[]
   */
  public $override = [];

  /**
   * Human-readable warning for overriding.
   *
   * @var string
   */
  public $override_warning = '';

  /**
   * Array of config IDs to load when the override is enabled.
   *
   * Matching mailer policy is included automatically so should not be listed
   * here.
   *
   * @var array
   */
  public $override_config = [];

  /**
   * Human-readable name of config to import.
   *
   * @var string
   */
  public $import = '';

  /**
   * Human-readable warning for importing.
   *
   * @var string
   */
  public $import_warning = '';

  /**
   * Array of config overrides.
   *
   * As required by ConfigFactoryOverrideInterface::loadOverrides().
   *
   * @var array
   */
  public $config_overrides = [];

  /**
   * Array of form alter information.
   *
   * The array key is the form ID, or '*' for the add/edit form of the
   * corresponding config entity. The value is an array with the following
   * allowed keys.
   * - remove: Array of fields to remove from the form.
   * - default: Array with key as the field name, and value as the field
   *   default value.
   * - entity_sub_type: Show entity policy for the specified sub-type.
   * - type: Show policy for the specified type.
   *
   * @var array
   */
  public $form_alter = [];

}
