<?php

namespace Drupal\taxonomy_access_fix;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\BundlePermissionHandlerTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\taxonomy\VocabularyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides additional permissions for entities provided by Taxonomy module.
 */
class TaxonomyAccessFixPermissions implements ContainerInjectionInterface {

  use BundlePermissionHandlerTrait;
  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a TaxonomyAccessFixPermissions instance.
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
   * Gets additional permissions for Taxonomy Vocabulary entities.
   *
   * @return array
   *   Permissions array.
   */
  public function getPermissions() {
    $permissions = [
      'view any term' => $this->t('View any published term'),
      'view any unpublished term' => $this->t('View any unpublished term'),
      'view any term name' => $this->t('View any published term name'),
      'view any unpublished term name' => $this->t('View any unpublished term name'),
      'view any vocabulary name' => $this->t('View any vocabulary name'),
      'create any term' => $this->t('Create any term'),
      'update any term' => $this->t('Edit any term'),
      'delete any term' => $this->t('Delete any term'),
      'reorder terms in any vocabulary' => $this->t('Reorder terms in any vocabulary'),
      'reset any vocabulary' => [
        'title' => $this->t('Reset any vocabulary'),
        'description' => $this->t('Allows resetting term order of any vocabulary to alphabetical order.'),
      ],
      'select any term' => [
        'title' => $this->t('Select any published term'),
        'description' => $this->t('Select published terms for Entity Reference fields referencing Taxonomy terms in any vocabulary using the "Default" reference method.'),
      ],
      'select any unpublished term' => [
        'title' => $this->t('Select any unpublished term'),
        'description' => $this->t('Select unpublished terms for Entity Reference fields referencing Taxonomy terms in any vocabulary using the "Default" reference method.'),
      ],
    ];

    $vocabularies = $this
      ->entityTypeManager
      ->getStorage('taxonomy_vocabulary')
      ->loadMultiple();

    $permissions += $this->generatePermissions($vocabularies, [
      $this,
      'buildPermissions',
    ]);

    return $permissions;
  }

  /**
   * Builds additional taxonomy term permissions for a given vocabulary.
   *
   * @param \Drupal\taxonomy\VocabularyInterface $vocabulary
   *   The vocabulary.
   *
   * @return array
   *   An array of permission names and descriptions.
   */
  protected function buildPermissions(VocabularyInterface $vocabulary) {
    $permissions['view terms in ' . $vocabulary->id()] = [
      'title' => $this->t('%vocabulary: View published terms', [
        '%vocabulary' => $vocabulary->label(),
      ]),
    ];
    $permissions['view unpublished terms in ' . $vocabulary->id()] = [
      'title' => $this->t('%vocabulary: View unpublished terms', [
        '%vocabulary' => $vocabulary->label(),
      ]),
    ];
    $permissions['view term names in ' . $vocabulary->id()] = [
      'title' => $this->t('%vocabulary: View published term names', [
        '%vocabulary' => $vocabulary->label(),
      ]),
    ];
    $permissions['view unpublished term names in ' . $vocabulary->id()] = [
      'title' => $this->t('%vocabulary: View unpublished term names', [
        '%vocabulary' => $vocabulary->label(),
      ]),
    ];
    $permissions['view vocabulary name of ' . $vocabulary->id()] = [
      'title' => $this->t('%vocabulary: View vocabulary name', [
        '%vocabulary' => $vocabulary->label(),
      ]),
    ];
    $permissions['reorder terms in ' . $vocabulary->id()] = [
      'title' => $this->t('%vocabulary: Reorder terms', [
        '%vocabulary' => $vocabulary->label(),
      ]),
    ];
    $permissions['reset ' . $vocabulary->id()] = [
      'title' => $this->t('%vocabulary: Reset', [
        '%vocabulary' => $vocabulary->label(),
      ]),
      'description' => $this->t('Allows resetting term order in the specified vocabulary to alphabetical order.'),
    ];
    $permissions['select terms in ' . $vocabulary->id()] = [
      'title' => $this->t('%vocabulary: Select published terms', [
        '%vocabulary' => $vocabulary->label(),
      ]),
      'description' => $this->t('Select published terms for Entity Reference fields referencing Taxonomy terms in the specified vocabulary using the "Default" reference method.'),
    ];
    $permissions['select unpublished terms in ' . $vocabulary->id()] = [
      'title' => $this->t('%vocabulary: Select unpublished terms', [
        '%vocabulary' => $vocabulary->label(),
      ]),
      'description' => $this->t('Select unpublished terms for Entity Reference fields referencing Taxonomy terms in the specified vocabulary using the "Default" reference method.'),
    ];

    return $permissions;
  }

}
