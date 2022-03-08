<?php

namespace Drupal\allowed_languages\Access;

use Drupal\allowed_languages\AllowedLanguagesManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;

/**
 * Allowed languages access check base class.
 */
abstract class AccessCheckBase implements AccessInterface {

  /**
   * The allowed language manager service.
   *
   * @var \Drupal\allowed_languages\AllowedLanguagesManagerInterface
   */
  protected $allowedLanguagesManager;

  /**
   * AccessCheck constructor.
   */
  public function __construct(AllowedLanguagesManagerInterface $allowed_languages_manager) {
    $this->allowedLanguagesManager = $allowed_languages_manager;
  }

}
