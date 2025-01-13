<?php

namespace Drupal\Tests\structure_sync\Functional;

use Drupal\block_content\Entity\BlockContent;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\structure_sync\Traits\StructureSyncBlocksSetUpTrait;
use Drush\TestTraits\DrushTestTrait;

/**
 * Tests basic import and export functionalities.
 *
 * @group structure_sync
 */
class StructureSyncBlocksDrushTest extends BrowserTestBase {

  use DrushTestTrait;
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
  }

  /**
   * Tests exporting and importing of block entities using drush command.
   */
  public function testBlocksExportImportUsingDrush() {
    // Exporting blocks.
    $this->drush('eb');
    $blocks = $this->config('structure_sync.data')->get('blocks');
    $this->assertCount(6, $blocks);

    // Editing the "Oxygen" block content.
    $oxygenBlockContents = $this->blockContentStorage->loadByProperties(['info' => 'Oxygen']);
    reset($oxygenBlockContents)->set('body', [
      [
        'value' => 'Oxygen',
        'format' => 'plain_text',
      ],
    ])->save();

    // Deleting the "Tig gas" block content.
    $tigGasBlocksLinkBefore = $this->blockContentStorage->loadByProperties(['info' => 'Argon']);
    $this->blockContentStorage->delete($tigGasBlocksLinkBefore);
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
    $this->drush('ib --choice=safe');

    // Deleted block item should be imported.
    $tigGasBlockContents = $this->blockContentStorage->loadByProperties(['info' => 'Argon']);
    $this->assertNotEmpty($tigGasBlockContents);

    // "Oxygen" block content should not be updated by a safe import.
    $oxygenBlockContents = $this->blockContentStorage->loadByProperties(['info' => 'Oxygen']);
    $this->assertEquals('Oxygen', reset($oxygenBlockContents)->body->value);

    // "Electrode" block content should not be deleted by a safe or full import.
    $electrodeBlockContents = $this->blockContentStorage->loadByProperties(['info' => 'Electrode']);
    $this->assertNotEmpty($electrodeBlockContents);

    // Importing blocks using full mode.
    $this->drush('ib --choice=full');

    // "Electrode" block content should be deleted by full import.
    $electrodeBlockContents = $this->blockContentStorage->loadByProperties(['info' => 'Electrode']);
    $this->assertEmpty($electrodeBlockContents);

    // Importing blocks using force mode.
    $this->drush('ib --choice=force');

    // "Oxygen" block content should be updated by a full import.
    $oxygenBlockContents = $this->blockContentStorage->loadByProperties(['info' => 'Oxygen']);
    $this->assertEquals('Oxygen gas', reset($oxygenBlockContents)->body->value);
  }

}
