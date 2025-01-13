<?php

namespace Drupal\Tests\structure_sync\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\Tests\structure_sync\Traits\StructureSyncMenuLinksSetUpTrait;

/**
 * Tests basic import and export functionalities.
 *
 * @group structure_sync
 */
class StructureSyncMenuLinksTest extends WebDriverTestBase {

  use StructureSyncMenuLinksSetUpTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
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

    // Log in an administrative user.
    $this->drupalLogin($this->drupalCreateUser([
      'administer site configuration',
      'administer menu',
    ]));
  }

  /**
   * Test importing and exporting of menu items entities.
   */
  public function testMenuLinksExportImportUsingAdmin(): void {
    $this->drupalGet('admin/structure/structure-sync/menu-links');

    // Test export of all the link items of all menus except 'Arc'.
    $page = $this->getSession()->getPage();
    $page->uncheckField('Arc');
    $this->click('#edit-export-menu-links');

    // Menu "arc" should not be exported.
    $menus = $this->config('structure_sync.data')->get('menus');
    foreach ($menus as $menu) {
      $this->assertNotContains('arc', $menu);
    }

    $this->assertCount(5, $menus);

    // Update the link's 'class' and 'target' attributes of the tested links.
    $updated_options = [
      'attributes' => [
        'class' => ['testing-class3 testing-class4'],
        'target' => '_blank',
      ],
    ];

    /* Test 'Safe' import. */
    // Adding an "Electrode" menu link.
    MenuLinkContent::create([
      'title' => 'Electrode',
      'link' => 'https://en.wikipedia.org/wiki/Electrode',
      'menu_name' => 'arc',
      'uuid' => '9a49342a-45e0-4d15-a251-83d704371c79',
    ])->save();

    // Deleting the "Tig gas" menu link.
    $tigGasMenuLinkBefore = $this->menuLinkStorage->loadByProperties(['uuid' => '48062c1c-132b-476e-bc3c-8511fb8896e4']);
    $this->menuLinkStorage->delete($tigGasMenuLinkBefore);
    $this->assertEmpty($this->menuLinkStorage->loadByProperties(['uuid' => '48062c1c-132b-476e-bc3c-8511fb8896e4']));

    // Editing the "Oxygen" menu link of the 'tig' menu.
    $oxygenMenuLinks = $this->menuLinkStorage->loadByProperties(['uuid' => 'd2385dc8-e70d-490f-8ec7-d95c828c98ac']);
    // Update the description of the "Oxygen" menu link.
    /** @var array $oxygenMenuLinks */
    $oxygenMenuLink = reset($oxygenMenuLinks)->set('description', 'Oxygen');
    // The link item has to be set separately.
    $oxygenMenuLink->link->get(0)->set('options', $updated_options);
    // Save the "Oxygen" menu link after it was updated.
    $oxygenMenuLink->save();

    // Editing the "Wire rod" menu link of the 'mig' menu.
    $wirerodMenuLinks = $this->menuLinkStorage->loadByProperties(['uuid' => 'a568e500-850a-4445-9e97-bf3b20aee779']);
    // Update the description of the "Wire rod" menu link.
    /** @var array $wirerodMenuLinks */
    $wirerodMenuLink = reset($wirerodMenuLinks)->set('description', 'Wire rod');
    // The link item has to be set separately.
    $wirerodMenuLink->link->get(0)->set('options', $updated_options);
    // Save the "Wire rod" menu link after it was updated.
    $wirerodMenuLink->save();

    // Importing menus using 'safe' mode.
    $this->click('#edit-import-menus-safe');
    // Wait for the import batch to complete so the buttons are visible again.
    $this->assertSession()->waitForElementVisible('css', '#edit-import-menus-full');

    // "Electrode" menu link should not be deleted by a 'safe' import.
    $electrodeMenuLinks = $this->menuLinkStorage->loadByProperties(['uuid' => '9a49342a-45e0-4d15-a251-83d704371c79']);
    $this->assertNotEmpty($electrodeMenuLinks);

    // Deleted "Tig gas" menu item should be imported.
    $tigGasMenuLinks = $this->menuLinkStorage->loadByProperties(['uuid' => '48062c1c-132b-476e-bc3c-8511fb8896e4']);
    $this->assertNotEmpty($tigGasMenuLinks);
    $tigGasMenuLink = reset($tigGasMenuLinks);
    // Check whether exported menu link's options were imported correctly.
    $this->assertEquals('tig-argon-gas-class1 tig-argon-gas-class2', $tigGasMenuLink->link->options['attributes']['class'][0]);
    $this->assertEquals('_blank', $tigGasMenuLink->link->options['attributes']['target']);

    // "Oxygen" menu link should not be updated by a 'safe' import.
    $oxygenMenuLinks = $this->menuLinkStorage->loadByProperties(['uuid' => 'd2385dc8-e70d-490f-8ec7-d95c828c98ac']);
    $oxygenMenuLink = reset($oxygenMenuLinks);
    $this->assertEquals('Oxygen', $oxygenMenuLink->description->value);
    // Ensure the "Oxygen" menu link still has the updated options values.
    $this->assertEquals($updated_options['attributes']['class'][0], $oxygenMenuLink->link->options['attributes']['class'][0]);
    $this->assertEquals($updated_options['attributes']['target'], $oxygenMenuLink->link->options['attributes']['target']);

    // "Wire rod" menu link should not be updated by a 'safe' import.
    $wirerodMenuLinks = $this->menuLinkStorage->loadByProperties(['uuid' => 'a568e500-850a-4445-9e97-bf3b20aee779']);
    $this->assertNotEmpty($wirerodMenuLinks);
    $wirerodMenuLink = reset($wirerodMenuLinks);
    $this->assertEquals('Wire rod', $wirerodMenuLink->description->value);
    // Ensure the "Wire rod" menu link still has the updated options values.
    $this->assertEquals($updated_options['attributes']['class'][0], $wirerodMenuLink->link->options['attributes']['class'][0]);
    $this->assertEquals($updated_options['attributes']['target'], $wirerodMenuLink->link->options['attributes']['target']);

    // "Arc" should not be deleted during a 'safe' import.
    $this->assertNotEmpty($this->menuLinkStorage->loadByProperties(['uuid' => '73e0f490-803c-439a-a15c-2d1c3d4ceac7']));

    /* Test 'Full' import. */
    // Deleting the "Tig gas" menu link to prepare next import ('full').
    $tigGasMenuLinkBefore = $this->menuLinkStorage->loadByProperties(['uuid' => '48062c1c-132b-476e-bc3c-8511fb8896e4']);
    $this->menuLinkStorage->delete($tigGasMenuLinkBefore);
    $this->assertEmpty($this->menuLinkStorage->loadByProperties(['uuid' => '48062c1c-132b-476e-bc3c-8511fb8896e4']));

    // Importing menus using 'full' mode.
    $this->click('#edit-import-menus-full');
    // Wait for the import batch to complete so the buttons are visible again.
    $this->assertSession()->waitForElementVisible('css', '#edit-import-menus-force');

    // "Electrode" menu link should be deleted by 'full' import.
    $electrodeMenuLinks = $this->menuLinkStorage->loadByProperties(['uuid' => '9a49342a-45e0-4d15-a251-83d704371c79']);
    $this->assertEmpty($electrodeMenuLinks);

    // "Tig gas" should be correctly imported.
    $tigGasMenuLinks = $this->menuLinkStorage->loadByProperties(['uuid' => '48062c1c-132b-476e-bc3c-8511fb8896e4']);
    $this->assertNotEmpty($tigGasMenuLinks);
    $tigGasMenuLink = reset($tigGasMenuLinks);
    // Check whether exported menu link's options were imported correctly.
    $this->assertEquals('tig-argon-gas-class1 tig-argon-gas-class2', $tigGasMenuLink->link->options['attributes']['class'][0]);
    $this->assertEquals('_blank', $tigGasMenuLink->link->options['attributes']['target']);

    // "Oxygen" menu link should not be updated by a 'full' import.
    $oxygenMenuLinks = $this->menuLinkStorage->loadByProperties(['uuid' => 'd2385dc8-e70d-490f-8ec7-d95c828c98ac']);
    $oxygenMenuLink = reset($oxygenMenuLinks);
    $this->assertEquals('Oxygen', $oxygenMenuLink->description->value);
    // Ensure the "Oxygen" menu link still has the updated options values.
    $this->assertEquals($updated_options['attributes']['class'][0], $oxygenMenuLink->link->options['attributes']['class'][0]);
    $this->assertEquals($updated_options['attributes']['target'], $oxygenMenuLink->link->options['attributes']['target']);

    // "Wire rod" menu link should not be updated by a 'full' import.
    $wirerodMenuLinks = $this->menuLinkStorage->loadByProperties(['uuid' => 'a568e500-850a-4445-9e97-bf3b20aee779']);
    $this->assertNotEmpty($wirerodMenuLinks);
    $wirerodMenuLink = reset($wirerodMenuLinks);
    $this->assertEquals('Wire rod', $wirerodMenuLink->description->value);
    // Ensure the "Wire rod" menu link still has the updated options values.
    $this->assertEquals($updated_options['attributes']['class'][0], $wirerodMenuLink->link->options['attributes']['class'][0]);
    $this->assertEquals($updated_options['attributes']['target'], $wirerodMenuLink->link->options['attributes']['target']);

    // "Arc" should be deleted during a 'full' import.
    $this->assertEmpty($this->menuLinkStorage->loadByProperties(['uuid' => '73e0f490-803c-439a-a15c-2d1c3d4ceac7']));

    /* Test 'Force' import. */
    // Adding an "Electrode" menu link back again for the 'force' tests.
    MenuLinkContent::create([
      'title' => 'Electrode',
      'link' => 'https://en.wikipedia.org/wiki/Electrode',
      'menu_name' => 'arc',
      'uuid' => '9a49342a-45e0-4d15-a251-83d704371c79',
    ])->save();

    // Deleting the "Tig gas" menu link to prepare next import ('force').
    $tigGasMenuLinkBefore = $this->menuLinkStorage->loadByProperties(['uuid' => '48062c1c-132b-476e-bc3c-8511fb8896e4']);
    $this->menuLinkStorage->delete($tigGasMenuLinkBefore);
    $this->assertEmpty($this->menuLinkStorage->loadByProperties(['uuid' => '48062c1c-132b-476e-bc3c-8511fb8896e4']));

    // Adding an "Arc" menu link back again for the 'force' tests.
    MenuLinkContent::create([
      'title' => 'SMAW',
      'link' => 'https://en.wikipedia.org/wiki/Shielded_metal_arc_welding',
      'menu_name' => 'arc',
      'uuid' => '73e0f490-803c-439a-a15c-2d1c3d4ceac7',
    ])->save();

    // Importing menus using 'force' mode.
    $this->click('#edit-import-menus-force');
    // Wait for the import batch to complete so the buttons are visible again.
    $this->assertSession()->waitForElementVisible('css', '#edit-import-menus-force');

    // "Electrode" menu link should be deleted by 'force' import.
    $electrodeMenuLinks = $this->menuLinkStorage->loadByProperties(['uuid' => '9a49342a-45e0-4d15-a251-83d704371c79']);
    $this->assertEmpty($electrodeMenuLinks);

    // "Tig gas" should be correctly imported.
    $tigGasMenuLinks = $this->menuLinkStorage->loadByProperties(['uuid' => '48062c1c-132b-476e-bc3c-8511fb8896e4']);
    $this->assertNotEmpty($tigGasMenuLinks);
    $tigGasMenuLink = reset($tigGasMenuLinks);
    // Check whether exported menu link's options were imported correctly.
    $this->assertEquals('tig-argon-gas-class1 tig-argon-gas-class2', $tigGasMenuLink->link->options['attributes']['class'][0]);
    $this->assertEquals('_blank', $tigGasMenuLink->link->options['attributes']['target']);

    // "Oxygen" menu link should be updated by a 'force' import.
    $oxygenMenuLinks = $this->menuLinkStorage->loadByProperties(['uuid' => 'd2385dc8-e70d-490f-8ec7-d95c828c98ac']);
    $oxygenMenuLink = reset($oxygenMenuLinks);
    $this->assertEquals('Oxygen gas', $oxygenMenuLink->description->value);
    // Ensure the "Oxygen" menu link options were reset to their initial values.
    $this->assertEquals('tig-oxygen-gas-class1 tig-oxygen-gas-class2', $oxygenMenuLink->link->options['attributes']['class'][0]);
    $this->assertEquals('_self', $oxygenMenuLink->link->options['attributes']['target']);

    // "Wire rod" menu link should be updated by a 'force' import.
    $wirerodMenuLinks = $this->menuLinkStorage->loadByProperties(['uuid' => 'a568e500-850a-4445-9e97-bf3b20aee779']);
    $this->assertNotEmpty($wirerodMenuLinks);
    $wirerodMenuLink = reset($wirerodMenuLinks);
    $this->assertEquals('Machine-fed wire rod', $wirerodMenuLink->description->value);
    // Ensure "Wire rod" menu link options were reset to their initial values.
    $this->assertEmpty($wirerodMenuLink->link->options);

    // "Arc" should be deleted during a 'force' import.
    $this->assertEmpty($this->menuLinkStorage->loadByProperties(['uuid' => '73e0f490-803c-439a-a15c-2d1c3d4ceac7']));
  }

}
