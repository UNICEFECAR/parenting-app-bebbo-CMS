<?php

namespace Drupal\symfony_mailer\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Defines a Mailer Policy configuration entity class.
 */
interface MailerPolicyInterface extends ConfigEntityInterface {

  /**
   * Gets the email type this policy applies to.
   *
   * @return string
   *   Email type, or NULL if the policy applies to all types.
   */
  public function getType();

  /**
   * Gets the email sub-type this policy applies to.
   *
   * @return string
   *   Email sub-type, or NULL if the policy applies to all sub-types.
   */
  public function getSubType();

  /**
   * Gets the config entity this policy applies to.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface
   *   Entity, or NULL if the policy applies to all entities.
   */
  public function getEntity();

  /**
   * Gets a human-readable label for the email type this policy applies to.
   *
   * @return string
   *   Email type label.
   */
  public function getTypeLabel();

  /**
   * Gets a human-readable label for the the email sub-type.
   *
   * @return string
   *   Email sub-type label.
   */
  public function getSubTypeLabel();

  /**
   * Gets a human-readable label for the config entity this policy applies to.
   *
   * @return string
   *   Email config entity label, or NULL if the builder doesn't support
   *   entities.
   */
  public function getEntityLabel();

  /**
   * {@inheritdoc}
   */
  public function label();

  /**
   * Sets the email adjuster configuration for this policy record.
   *
   * @param array $configuration
   *   An associative array of adjuster configuration, keyed by the plug-in ID
   *   with value as an array of configured settings.
   *
   * @return $this
   */
  public function setConfiguration(array $configuration);

  /**
   * Gets the email adjuster configuration for this policy record.
   *
   * @return array
   *   An associative array of adjuster configuration, keyed by the plug-in ID
   *   with value as an array of configured settings.
   */
  public function getConfiguration();

  /**
   * Returns the ordered collection of configured adjuster plugin instances.
   *
   * @return \Drupal\symfony_mailer\Processor\AdjusterPluginCollection
   *   The adjuster collection.
   */
  public function adjusters();

  /**
   * Returns all available adjuster plugin definitions.
   *
   * @return array
   *   An associative array of plugin definitions, keyed by the plug-in ID.
   */
  public function adjusterDefinitions();

  /**
   * Gets a short human-readable summary of the configured policy.
   *
   * @param bool $expanded
   *   (Optional) If FALSE return just the labels. If TRUE include a short
   *   summary of each element.
   *
   * @return string
   *   Summary text.
   */
  public function getSummary($expanded = FALSE);

  /**
   * Returns the common adjusters for this policy.
   *
   * @return array
   *   An array of common adjuster IDs.
   */
  public function getCommonAdjusters();

  /**
   * Helper callback to sort entities.
   */
  public static function sort(ConfigEntityInterface $a, ConfigEntityInterface $b);

  /**
   * Loads a Mailer Policy, or creates a new one.
   *
   * @param string $id
   *   The id of the policy to load or create.
   *
   * @return static
   *   The policy object.
   */
  public static function loadOrCreate(string $id);

  /**
   * Loads config for a Mailer Policy including inherited policy.
   *
   * @param string $id
   *   The id of the policy.
   *
   * @return array
   *   The configuration array.
   */
  public static function loadInheritedConfig(string $id);

  /**
   * Imports a Mailer Policy from configuration.
   *
   * @param string $id
   *   The id of the policy to import.
   * @param array $configuration
   *   An associative array of adjuster configuration, keyed by the plug-in ID
   *   with value as an array of configured settings.
   */
  public static function import($id, array $configuration);

}
