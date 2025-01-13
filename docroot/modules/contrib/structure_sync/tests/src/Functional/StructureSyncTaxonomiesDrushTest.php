<?php

namespace Drupal\Tests\structure_sync\Functional;

use Drupal\taxonomy\Entity\Term;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\structure_sync\Traits\StructureSyncTaxonomiesSetUpTrait;
use Drush\TestTraits\DrushTestTrait;

/**
 * Tests basic import and export functionalities using drush.
 *
 * @group structure_sync
 */
class StructureSyncTaxonomiesDrushTest extends BrowserTestBase {

  use DrushTestTrait;
  use StructureSyncTaxonomiesSetUpTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'structure_sync',
    'taxonomy',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The taxonomy term storage handler.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $taxonomyTermStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createSetUpContent();
  }

  /**
   * Tests exporting and importing of menu items entities using drush command.
   */
  public function testTaxonomiesExportImportUsingDrush() {
    // Exporting menus.
    $this->drush('et');
    $taxonomies = $this->config('structure_sync.data')->get('taxonomies');
    $this->assertCount(3, $taxonomies);

    // Editing the "Oxygen" taxonomy term.
    $oxygenTerms = $this->taxonomyTermStorage->loadByProperties(['name' => 'Oxygen']);
    reset($oxygenTerms)->set('description', 'Oxygen')->save();

    // Deleting the "Tig gas" taxonomy term.
    $tigGasTaxonomiesLinkBefore = $this->taxonomyTermStorage->loadByProperties(['name' => 'Argon']);
    $this->taxonomyTermStorage->delete($tigGasTaxonomiesLinkBefore);
    $this->assertEmpty($this->taxonomyTermStorage->loadByProperties(['name' => 'Argon']));

    // Adding an "Electrode" taxonomy term.
    Term::create([
      'vid' => 'arc',
      'name' => 'Electrode',
    ])->save();

    // Importing menus using safe mode.
    $this->drush('it --choice=safe');

    // Deleted menu item should be imported.
    $tigGasTerms = $this->taxonomyTermStorage->loadByProperties(['name' => 'Argon']);
    $this->assertNotEmpty($tigGasTerms);

    // "Oxygen" taxonomy term should not be updated by a safe import.
    $oxygenTerms = $this->taxonomyTermStorage->loadByProperties(['name' => 'Oxygen']);
    $this->assertEquals('Oxygen', reset($oxygenTerms)->description->value);

    // "Electrode" taxonomy term should not be deleted by a safe or full import.
    $electrodeTerms = $this->taxonomyTermStorage->loadByProperties(['name' => 'Electrode']);
    $this->assertNotEmpty($electrodeTerms);

    // Importing menus using full mode.
    $this->drush('it --choice=full');

    // "Electrode" taxonomy term should be deleted by full import.
    $electrodeTerms = $this->taxonomyTermStorage->loadByProperties(['name' => 'Electrode']);
    $this->assertEmpty($electrodeTerms);

    // Importing menus using force mode.
    $this->drush('it --choice=force');

    // "Oxygen" taxonomy term should be updated by a full import.
    $oxygenTerms = $this->taxonomyTermStorage->loadByProperties(['name' => 'Oxygen']);
    $this->assertEquals('Oxygen gas', reset($oxygenTerms)->description->value);
  }

}
