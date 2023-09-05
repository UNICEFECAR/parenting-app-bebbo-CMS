<?php

namespace Drupal\languagefield\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the CustomLanguage entity.
 *
 * The CustomLanguage entity stores information about custom
 * languages added to be used by the language field.
 *
 * @ConfigEntityType(
 *   id = "custom_language",
 *   label = @Translation("Custom language"),
 *   fieldable = FALSE,
 *   module = "languagefield",
 *   config_prefix = "custom_language",
 *   admin_permission = "administer languagefield",
 *   handlers = {
 *     "storage" = "Drupal\languagefield\CustomLanguageStorage",
 *     "list_builder" = "Drupal\languagefield\CustomLanguageListBuilder",
 *     "form" = {
 *       "default" = "Drupal\languagefield\Form\CustomLanguageForm",
 *       "delete" = "Drupal\languagefield\Form\CustomLanguageDeleteForm"
 *     },
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/regional/custom_language/manage/{custom_language}",
 *     "delete-form" = "/admin/config/regional/custom_language/manage/{custom_language}/delete"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "weight" = "weight"
 *   },
 *   config_export = {
 *     "id" = "id",
 *     "label" = "label",
 *     "direction",
 *     "weight" = "weight",
 *     "native_name" = "native_name",
 *   }
 * )
 */
class CustomLanguage extends ConfigEntityBase implements CustomLanguageInterface {

  /**
   * The language ID (machine name).
   *
   * @var string
   */
  protected $id;

  /**
   * The english name of this CustomLanguage.
   *
   * @var string
   */
  protected $label;

  /**
   * The native name of this CustomLanguage.
   *
   * @var string
   */
  protected $native_name;

  /**
   * The direction of language, either DIRECTION_LTR or DIRECTION_RTL.
   *
   * @var int
   */
  protected $direction = self::DIRECTION_LTR;

  /**
   * The position weight (not physical) of this CustomLanguage.
   *
   * @var int
   */
  protected $weight;

  /**
   * {@inheritdoc}
   */
  public function isDefault() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isLocked() {
    return (bool) FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->label();
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->label = $name;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getNativeName() {
    return $this->native_name;
  }

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return $this->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getDirection() {
    return $this->direction;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return $this->weight;
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight($weight) {
    $this->weight = $weight;
    return $this;
  }

}
