<?php

namespace Drupal\Tests\structure_sync\Functional;

use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\structure_sync\Traits\StructureSyncMenuLinksSetUpTrait;
use Drush\TestTraits\DrushTestTrait;

/**
 * Tests basic import and export functionalities.
 *
 * @group structure_sync
 */
class StructureSyncMenuLinksDrushTest extends BrowserTestBase {

  use DrushTestTrait;
  use StructureSyncMenuLinksSetUpTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'dblog',
    'structure_sync',
    'menu_link_content',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The menu link entity storage handler.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $menuLinkStorage;

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
  public function testMenuLinksExportImportUsingDrush(): void {
    // Exporting menus.
    $this->drush('em');
    $menus = $this->config('structure_sync.data')->get('menus');
    $this->assertCount(6, $menus);

    // Editing the "Oxygen" menu link.
    $oxygenMenuLinks = $this->menuLinkStorage->loadByProperties(['uuid' => 'd2385dc8-e70d-490f-8ec7-d95c828c98ac']);
    reset($oxygenMenuLinks)->set('description', 'Oxygen')->save();

    // Deleting the "Tig gas" menu link.
    $tigGasMenuLinkBefore = $this->menuLinkStorage->loadByProperties(['uuid' => '48062c1c-132b-476e-bc3c-8511fb8896e4']);
    $this->menuLinkStorage->delete($tigGasMenuLinkBefore);
    $this->assertEmpty($this->menuLinkStorage->loadByProperties(['uuid' => '48062c1c-132b-476e-bc3c-8511fb8896e4']));

    // Adding an "Electrode" menu link.
    MenuLinkContent::create([
      'title' => 'Electrode',
      'link' => 'https://en.wikipedia.org/wiki/Electrode',
      'menu_name' => 'arc',
      'uuid' => '9a49342a-45e0-4d15-a251-83d704371c79',
    ])->save();

    // Importing menus using safe mode.
    $this->drush('im --choice=safe');

    // Deleted menu item should be imported.
    $tigGasMenuLink = $this->menuLinkStorage->loadByProperties(['uuid' => '48062c1c-132b-476e-bc3c-8511fb8896e4']);
    $this->assertNotEmpty($tigGasMenuLink);

    // "Oxygen" menu link should not be updated by a safe import.
    $oxygenMenuLinks = $this->menuLinkStorage->loadByProperties(['uuid' => 'd2385dc8-e70d-490f-8ec7-d95c828c98ac']);
    $this->assertEquals('Oxygen', reset($oxygenMenuLinks)->description->value);

    // "Electrode" menu link should not be deleted by a safe import.
    $electrodeMenuLinks = $this->menuLinkStorage->loadByProperties(['uuid' => '9a49342a-45e0-4d15-a251-83d704371c79']);
    $this->assertNotEmpty($electrodeMenuLinks);

    // Importing menus using full mode.
    $this->drush('im --choice=full');

    // "Electrode" menu link should be deleted by full import.
    $electrodeMenuLinks = $this->menuLinkStorage->loadByProperties(['uuid' => '9a49342a-45e0-4d15-a251-83d704371c79']);
    $this->assertEmpty($electrodeMenuLinks);

    // Importing menus using force mode.
    $this->drush('im --choice=force');

    // "Oxygen" menu link should be updated by a full import.
    $oxygenMenuLinks = $this->menuLinkStorage->loadByProperties(['uuid' => 'd2385dc8-e70d-490f-8ec7-d95c828c98ac']);
    $this->assertEquals('Oxygen gas', reset($oxygenMenuLinks)->description->value);

    // Importing with no config should print out a log message.
    $this->drush('config:delete "structure_sync.data"');
    $this->drush('import:menus --choice=safe');
    $this->drush('watchdog:show --count=1 --field=message');
    $this->assertEquals("No menu exported: Nothing to import.\\nMenus need to be exported first before they can be imported.", $this->getOutput());
  }

}
