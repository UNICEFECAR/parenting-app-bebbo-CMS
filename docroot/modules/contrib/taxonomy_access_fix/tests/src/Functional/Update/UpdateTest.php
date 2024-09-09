<?php

namespace Drupal\Tests\taxonomy_access_fix\Functional\Update;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Tests update hooks.
 *
 * @group Update
 */
class UpdateTest extends UpdateTestBase {

  use TaxonomyTestTrait;

  /**
   * {@inheritdoc}
   */
  public function testUpdateHooks(): void {
    $assert_session = $this->assertSession();

    // Create vocabularies.
    $vocabularies = [
      $this->createVocabulary(),
      $this->createVocabulary(),
    ];

    // Create roles.
    $storage = $this->container
      ->get('entity_type.manager')
      ->getStorage('user_role');
    $roles_view_access = [
      'administrator' => [
        $vocabularies[0]->id() => TRUE,
        $vocabularies[1]->id() => TRUE,
      ],
      'first_vocabulary' => [
        $vocabularies[0]->id() => TRUE,
        $vocabularies[1]->id() => FALSE,
      ],
      'second_vocabulary' => [
        $vocabularies[0]->id() => FALSE,
        $vocabularies[1]->id() => TRUE,
      ],
      'no_permissions' => [
        $vocabularies[0]->id() => FALSE,
        $vocabularies[1]->id() => FALSE,
      ],
    ];
    foreach ($roles_view_access as $role_id => $view_access) {
      $role = $storage->create([
        'id' => $role_id,
        'label' => $role_id,
        'is_admin' => $role_id === 'administrator',
      ]);
      foreach ($view_access as $vocabulary_id => $has_access) {
        if (!$has_access) {
          continue;
        }
        $role->grantPermission('view terms in ' . $vocabulary_id);
      }
      $role->save();
    }
    $storage->resetCache();

    // Assert expected permissions.
    $roles = $storage->loadMultiple();
    foreach ($roles_view_access as $role_id => $view_access) {
      foreach ($view_access as $vocabulary_id => $has_access) {
        if ($has_access) {
          $this->assertTrue($roles[$role_id]->hasPermission('view terms in ' . $vocabulary_id), new FormattableMarkup('@role_id has permission to view terms in @vocabulary_id', [
            '@role_id' => $role_id,
            '@vocabulary_id' => $vocabulary_id,
          ]));
        }
        else {
          $this->assertFalse($roles[$role_id]->hasPermission('view terms in ' . $vocabulary_id), new FormattableMarkup('@role_id has no permission to view terms in @vocabulary_id', [
            '@role_id' => $role_id,
            '@vocabulary_id' => $vocabulary_id,
          ]));
        }
        if ($roles[$role_id]->isAdmin()) {
          $this->assertTrue($roles[$role_id]->hasPermission('view term names in ' . $vocabulary_id), new FormattableMarkup('@role_id has permission to view term names in @vocabulary_id', [
            '@role_id' => $role_id,
            '@vocabulary_id' => $vocabulary_id,
          ]));
          $this->assertTrue($roles[$role_id]->hasPermission('select terms in ' . $vocabulary_id), new FormattableMarkup('@role_id has permission to select terms in @vocabulary_id', [
            '@role_id' => $role_id,
            '@vocabulary_id' => $vocabulary_id,
          ]));
        }
        else {
          $this->assertFalse($roles[$role_id]->hasPermission('view term names in ' . $vocabulary_id), new FormattableMarkup('@role_id has no permission to view term names in @vocabulary_id', [
            '@role_id' => $role_id,
            '@vocabulary_id' => $vocabulary_id,
          ]));
          $this->assertFalse($roles[$role_id]->hasPermission('select terms in ' . $vocabulary_id), new FormattableMarkup('@role_id has no permission to select terms in @vocabulary_id', [
            '@role_id' => $role_id,
            '@vocabulary_id' => $vocabulary_id,
          ]));
        }
      }
    }

    // Run updates.
    $raw_messages = [
      9401 => "Populate &#039;VOCABULARY: View published term names&#039; permission.",
      9402 => "Populate &#039;VOCABULARY: Select published terms&#039; permission.",
    ];
    $this->runUpdates(8202, $raw_messages);
    $storage->resetCache();

    // Assert expected permissions.
    $roles = $storage->loadMultiple();
    foreach ($roles_view_access as $role_id => $view_access) {
      foreach ($view_access as $vocabulary_id => $has_access) {
        if ($has_access) {
          $this->assertTrue($roles[$role_id]->hasPermission('view term names in ' . $vocabulary_id), new FormattableMarkup('@role_id has permission to view term names in @vocabulary_id', [
            '@role_id' => $role_id,
            '@vocabulary_id' => $vocabulary_id,
          ]));
          $this->assertTrue($roles[$role_id]->hasPermission('select terms in ' . $vocabulary_id), new FormattableMarkup('@role_id has permission to select terms in @vocabulary_id', [
            '@role_id' => $role_id,
            '@vocabulary_id' => $vocabulary_id,
          ]));
          $this->assertTrue($roles[$role_id]->hasPermission('view terms in ' . $vocabulary_id), new FormattableMarkup('@role_id has permission to view terms in @vocabulary_id', [
            '@role_id' => $role_id,
            '@vocabulary_id' => $vocabulary_id,
          ]));
        }
        else {
          if ($roles[$role_id]->isAdmin()) {
            continue;
          }
          $this->assertFalse($roles[$role_id]->hasPermission('view term names in ' . $vocabulary_id), new FormattableMarkup('@role_id has no permission to view term names in @vocabulary_id', [
            '@role_id' => $role_id,
            '@vocabulary_id' => $vocabulary_id,
          ]));
          $this->assertFalse($roles[$role_id]->hasPermission('select terms in ' . $vocabulary_id), new FormattableMarkup('@role_id has no permission to select terms in @vocabulary_id', [
            '@role_id' => $role_id,
            '@vocabulary_id' => $vocabulary_id,
          ]));
          $this->assertFalse($roles[$role_id]->hasPermission('view terms in ' . $vocabulary_id), new FormattableMarkup('@role_id has no permission to view terms in @vocabulary_id', [
            '@role_id' => $role_id,
            '@vocabulary_id' => $vocabulary_id,
          ]));
        }
      }
    }
  }

}
