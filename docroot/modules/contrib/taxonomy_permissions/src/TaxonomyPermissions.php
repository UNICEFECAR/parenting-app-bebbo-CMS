<?php

namespace Drupal\taxonomy_permissions;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\core\Entity\EntityTypeManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class for dynamic permissions based on vocabularies.
 */
class TaxonomyPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * Returns an array of transition permissions.
   *
   * @return array
   *   The access protected permissions.
   */
  public function permissions() {

    $perms = [];
    $vocabularies = Vocabulary::loadMultiple();
    /* @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    foreach ($vocabularies as $id => $vocabulary) {
      $perms['view terms in ' . $id] = [
        'title' => $this->t('View terms in %label', [
          '%label' => $vocabulary->label(),
        ]),
        'description' => $this->t('View the terms of %label vocabulary', [
          '%label' => $vocabulary->label(),
        ]),
      ];
    }

    return $perms;
  }

}
