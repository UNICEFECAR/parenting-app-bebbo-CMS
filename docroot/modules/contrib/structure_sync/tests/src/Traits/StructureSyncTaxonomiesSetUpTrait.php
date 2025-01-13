<?php

namespace Drupal\Tests\structure_sync\Traits;

use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Provides basic content for tests.
 */
trait StructureSyncTaxonomiesSetUpTrait {

  /**
   * {@inheritdoc}
   */
  public function createSetUpContent(): void {
    $this->taxonomyTermStorage = $this->container->get('entity_type.manager')->getStorage('taxonomy_term');

    // Create three menus to tests different cases.
    Vocabulary::create([
      'vid' => 'mig',
      'name' => 'MIG',
      'description' => 'Metal inert gas welding',
    ])->save();

    Vocabulary::create([
      'vid' => 'tig',
      'name' => 'TIG',
      'description' => 'Tungsten inert gas welding',
    ])->save();

    Vocabulary::create([
      'vid' => 'arc',
      'name' => 'Arc',
      'description' => 'Arc welding',
    ])->save();

    Term::create([
      'name' => 'Wire rod',
      'description' => 'Machine-fed wire rod',
      'vid' => 'mig',
    ])->save();

    Term::create([
      'name' => 'TIG Rod',
      'description' => 'Hand-held welding rod',
      'vid' => 'tig',
    ])->save();

    Term::create([
      'name' => 'Gas',
      'description' => 'Gas used with TIG',
      'vid' => 'tig',
      'status' => 0,
    ])->save();

    Term::create([
      'name' => 'Argon',
      'description' => 'Argon gas',
      'vid' => 'tig',
    ])->save();

    Term::create([
      'name' => 'Oxygen',
      'description' => 'Oxygen gas',
      'vid' => 'tig',
    ])->save();

    Term::create([
      'vid' => 'arc',
      'name' => 'SMAW',
    ])->save();
  }

}
