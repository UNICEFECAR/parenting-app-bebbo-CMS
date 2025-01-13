<?php

namespace Drupal\Tests\structure_sync\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\Tests\structure_sync\Traits\StructureSyncTaxonomiesSetUpTrait;

/**
 * Tests basic import and export functionalities.
 *
 * @group structure_sync
 */
class StructureSyncTaxonomiesTest extends WebDriverTestBase {

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

    // Log in an administrative user.
    $this->drupalLogin($this->drupalCreateUser([
      'administer site configuration',
      'administer taxonomy',
    ]));
  }

  /**
   * Test exporting and importing of taxonomies.
   */
  public function testTaxonomiesExportImportUsingAdmin(): void {
    $this->drupalGet('admin/structure/structure-sync/taxonomies');

    $page = $this->getSession()->getPage();
    $page->uncheckField('Arc');
    $this->click('#edit-export-taxonomies');

    // Taxonomy "arc" should not be exported.
    $taxonomies = $this->config('structure_sync.data')->get('taxonomies');
    foreach ($taxonomies as $taxonomy) {
      $this->assertNotContains('arc', $taxonomy);
    }

    $this->assertCount(2, $taxonomies);

    // Deleting the "Tig gas" taxonomy term.
    $tigGasTermsBefore = $this->taxonomyTermStorage->loadByProperties(['name' => 'Argon']);
    $this->taxonomyTermStorage->delete($tigGasTermsBefore);
    $this->assertEmpty($this->taxonomyTermStorage->loadByProperties(['name' => 'Argon']));

    // Adding an "Electrode" taxonomy term.
    Term::create([
      'vid' => 'arc',
      'name' => 'Electrode',
    ])->save();

    $this->click('#edit-import-taxonomies-safe');
    // Wait for the import batch to complete so the buttons are visible again.
    $this->assertSession()->waitForElementVisible('css', '#edit-import-taxonomies-full');

    // Deleted menu item should be imported.
    $tigGasTerms = $this->taxonomyTermStorage->loadByProperties(['name' => 'Argon']);
    $this->assertNotEmpty($tigGasTerms);

    // "MIG" and "Arc" should not be deleted during the process.
    $this->assertNotEmpty($this->taxonomyTermStorage->loadByProperties(['name' => 'Wire rod']));
    $this->assertNotEmpty($this->taxonomyTermStorage->loadByProperties(['name' => 'SMAW']));

    // Trying full.
    $this->click('#edit-import-taxonomies-full');
    // Wait for the import batch to complete so the buttons are visible again.
    $this->assertSession()->waitForElementVisible('css', '#edit-import-taxonomies-full');

    // "Tig gas" should be correctly imported.
    $tigGasTaxonomies = $this->taxonomyTermStorage->loadByProperties(['name' => 'Argon']);
    $this->assertNotEmpty($tigGasTaxonomies);
  }

}
