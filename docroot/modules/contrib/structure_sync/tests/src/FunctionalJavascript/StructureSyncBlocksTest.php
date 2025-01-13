<?php

namespace Drupal\Tests\structure_sync\FunctionalJavascript;

use Drupal\block_content\Entity\BlockContent;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\structure_sync\Traits\StructureSyncBlocksSetUpTrait;

/**
 * Tests basic import and export functionalities.
 *
 * @group structure_sync
 */
class StructureSyncBlocksTest extends WebDriverTestBase {

  use StructureSyncBlocksSetUpTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'structure_sync',
    'block_content',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The block storage handler.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $blockContentStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createSetUpContent();

    // Log in an administrative user.
    $this->drupalLogin($this->drupalCreateUser([
      'administer site configuration',
      'administer blocks',
    ]));
  }

  /**
   * Test importing and exporting of blocks.
   */
  public function testBlocksExportImportUsingAdmin(): void {
    $this->drupalGet('admin/structure/structure-sync/blocks');

    $page = $this->getSession()->getPage();
    $page->uncheckField('TIG Rod');
    $this->click('#edit-export-blocks');

    // Taxonomy "arc" should not be exported.
    $blocks = $this->config('structure_sync.data')->get('blocks');
    foreach ($blocks as $taxonomy) {
      $this->assertNotContains('arc', $taxonomy);
    }

    $this->assertCount(5, $blocks);

    // Editing the "Oxygen" block content.
    $oxygenBlockContents = $this->blockContentStorage->loadByProperties(['info' => 'Oxygen']);
    reset($oxygenBlockContents)->set('body', [
      [
        'value' => 'Oxygen',
        'format' => 'plain_text',
      ],
    ])->save();

    // Deleting the "Tig gas" block content.
    $tigGasBlockContentsBefore = $this->blockContentStorage->loadByProperties(['info' => 'Argon']);
    $this->blockContentStorage->delete($tigGasBlockContentsBefore);
    $this->assertEmpty($this->blockContentStorage->loadByProperties(['info' => 'Argon']));

    // Adding an "Electrode" block content.
    BlockContent::create([
      'info' => 'Electrode',
      'type' => 'basic',
      'body' => [
        [
          'value' => 'Electrode',
          'format' => 'plain_text',
        ],
      ],
    ])->save();

    // Importing blocks using safe mode.
    $this->drupalGet('admin/structure/structure-sync/blocks');
    $this->click('#edit-import-blocks-safe');
    // Wait for the import batch to complete so the buttons are visible again.
    $this->assertSession()->waitForElementVisible('css', '#edit-import-blocks-full');

    // Deleted block item should be imported.
    $tigGasBlockContents = $this->blockContentStorage->loadByProperties(['info' => 'Argon']);
    $this->assertNotEmpty($tigGasBlockContents);

    // "Oxygen" block content should not be updated by a safe import.
    $oxygenBlockContents = $this->blockContentStorage->loadByProperties(['info' => 'Oxygen']);
    $this->assertEquals('Oxygen', reset($oxygenBlockContents)->body->value);

    // "Electrode" block content should not be deleted by a safe or full import.
    $electrodeBlockContents = $this->blockContentStorage->loadByProperties(['info' => 'Electrode']);
    $this->assertNotEmpty($electrodeBlockContents);

    // "Tig Rod" block content should not be deleted by a safe or full import.
    $tigRodBlockContents = $this->blockContentStorage->loadByProperties(['info' => 'Tig Rod']);
    $this->assertNotEmpty($tigRodBlockContents);

    // Importing blocks using full mode.
    $this->click('#edit-import-blocks-full');
    // Wait for the import batch to complete so the buttons are visible again.
    $this->assertSession()->waitForElementVisible('css', '#edit-import-blocks-force');

    // "Electrode" block content should be deleted by full import.
    $electrodeBlockContents = $this->blockContentStorage->loadByProperties(['info' => 'Electrode']);
    $this->assertEmpty($electrodeBlockContents);

    // "Tig Rod" block content should not be deleted by a safe or full import.
    $tigRodBlockContents = $this->blockContentStorage->loadByProperties(['info' => 'Tig Rod']);
    $this->assertEmpty($tigRodBlockContents);

    // Importing blocks using force mode.
    $this->click('#edit-import-blocks-force');
    // Wait for the import batch to complete so the buttons are visible again.
    $this->assertSession()->waitForElementVisible('css', '#edit-import-blocks-force');

    // "Oxygen" block content should be updated by a full import.
    $oxygenBlockContents = $this->blockContentStorage->loadByProperties(['info' => 'Oxygen']);
    $this->assertEquals('Oxygen gas', reset($oxygenBlockContents)->body->value);
  }

}
