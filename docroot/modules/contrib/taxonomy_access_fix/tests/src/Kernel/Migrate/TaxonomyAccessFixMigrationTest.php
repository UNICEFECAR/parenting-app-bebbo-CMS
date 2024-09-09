<?php

namespace Drupal\Tests\taxonomy_access_fix\Kernel\Migrate;

use Drupal\Tests\migrate_drupal\Kernel\MigrateDrupalTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Tests Taxonomy Access Fix permission migrations.
 *
 * @group taxonomy_access_fix
 */
class TaxonomyAccessFixMigrationTest extends MigrateDrupalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'filter',
    'taxonomy',
    'taxonomy_access_fix',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->loadFixture(
      implode(DIRECTORY_SEPARATOR, [
        $this->getModulePath('migrate_drupal'),
        'tests',
        'fixtures',
        'drupal7.php',
      ])
    );

    $this->addTermEditorRoleToSourceDatabase();
  }

  /**
   * Tests Taxonomy Access Fix permission migration.
   */
  public function testMigration(): void {
    $this->executeMigration('d7_taxonomy_vocabulary');
    $this->executeMigration('d7_user_role');

    // 'term_editor' role must have taxonomy_access_fix permissions for tags
    // vocabulary.
    $term_editor_role = Role::load('term_editor');
    $this->assertInstanceOf(RoleInterface::class, $term_editor_role);
    $this->assertTrue($term_editor_role->hasPermission('reorder terms in tags'));
    $this->assertTrue($term_editor_role->hasPermission('view terms in tags'));
    $this->assertTrue($term_editor_role->hasPermission('view unpublished terms in tags'));

    // 'authenticated' role shouldn't have taxonomy_access_fix permissions for
    // tags vocabulary.
    $authenticated_role = Role::load('authenticated');
    $this->assertInstanceOf(RoleInterface::class, $authenticated_role);
    $this->assertFalse($authenticated_role->hasPermission('reorder terms in tags'));
    $this->assertFalse($authenticated_role->hasPermission('view terms in tags'));
    $this->assertFalse($authenticated_role->hasPermission('view unpublished terms in tags'));
  }

  /**
   * Adds a 'term editor' role to the source database.
   *
   * This role will have the 'add terms in tags' permission provided by Taxonomy
   * Access Fix and the role ID will be 'term_editor' on the destination Drupal
   * instance.
   */
  protected function addTermEditorRoleToSourceDatabase(): void {
    // Insert an extra user role: 'term editor'.
    $this->sourceDatabase->insert('role')
      ->fields([
        'rid' => 111,
        'name' => 'term editor',
        'weight' => 111,
      ])
      ->execute();

    // The 'term editor' role will have the permission provided by
    // taxonomy_access_fix for adding terms in the tags vocabulary.
    $this->sourceDatabase->insert('role_permission')
      ->fields(['rid', 'permission', 'module'])
      ->values([
        'rid' => 111,
        'permission' => 'access content',
        'module' => 'node',
      ])
      ->values([
        'rid' => 111,
        'permission' => 'access comments',
        'module' => 'comment',
      ])
      ->values([
        'rid' => 111,
        'permission' => 'add terms in tags',
        'module' => 'taxonomy_access_fix',
      ])
      ->execute();
  }

}
