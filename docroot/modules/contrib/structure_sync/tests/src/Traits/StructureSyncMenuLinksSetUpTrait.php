<?php

namespace Drupal\Tests\structure_sync\Traits;

use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\system\Entity\Menu;

/**
 * Provides basic content for tests.
 */
trait StructureSyncMenuLinksSetUpTrait {

  /**
   * {@inheritdoc}
   */
  public function createSetUpContent(): void {
    $this->menuLinkStorage = $this->container->get('entity_type.manager')->getStorage('menu_link_content');

    // Create three menus to tests different cases, avoid exporting the third
    // thanks to unchecking the corresponding checkbox.
    Menu::create([
      'id' => 'mig',
      'label' => 'MIG',
      'description' => 'Metal inert gas welding',
    ])->save();

    Menu::create([
      'id' => 'tig',
      'label' => 'TIG',
      'description' => 'Tungsten inert gas welding',
    ])->save();

    Menu::create([
      'id' => 'arc',
      'label' => 'Arc',
      'description' => 'Arc welding',
    ])->save();

    MenuLinkContent::create([
      'title' => 'Wire rod',
      'description' => 'Machine-fed wire rod',
      'link' => 'https://en.wikipedia.org/wiki/Filler_metal',
      'weight' => 0,
      'menu_name' => 'mig',
      'uuid' => 'a568e500-850a-4445-9e97-bf3b20aee779',
    ])->save();

    MenuLinkContent::create([
      'title' => 'TIG Rod',
      'description' => 'Hand-held welding rod',
      'link' => [
        'uri' => 'https://en.wikipedia.org/wiki/Filler_metal',
        'title' => 'Manual rod',
      ],
      'weight' => 0,
      'menu_name' => 'tig',
      'uuid' => '8d767d8a-3852-4021-a1cc-d41e71f39881',
      'expanded' => TRUE,
      'enabled' => TRUE,
    ])->save();

    MenuLinkContent::create([
      'title' => 'Gas',
      'description' => 'Gas used with TIG',
      'link' => 'internal:/admin/structure/structure-sync',
      'weight' => 1,
      'menu_name' => 'tig',
      'uuid' => '54d1a5db-c9c1-4a09-98fa-26905094f77e',
      'expanded' => FALSE,
      'enabled' => TRUE,
    ])->save();

    MenuLinkContent::create([
      'title' => 'Argon',
      'description' => 'Argon gas',
      'link' => [
        'uri' => 'https://en.wikipedia.org/wiki/Argon',
        // Set the menu link 'class' and 'target' attributes.
        'options' => [
          'attributes' => [
            'class' => ['tig-argon-gas-class1 tig-argon-gas-class2'],
            'target' => '_blank',
          ],
        ],
      ],
      'weight' => 1,
      'menu_name' => 'tig',
      'uuid' => '48062c1c-132b-476e-bc3c-8511fb8896e4',
      'expanded' => FALSE,
      'enabled' => TRUE,
      'parent' => 'menu_link_content:54d1a5db-c9c1-4a09-98fa-26905094f77e',
    ])->save();

    MenuLinkContent::create([
      'title' => 'Oxygen',
      'description' => 'Oxygen gas',
      'link' => [
        'uri' => 'https://en.wikipedia.org/wiki/Oxygen',
        // Set the menu link 'class' and 'target' attributes.
        'options' => [
          'attributes' => [
            'class' => ['tig-oxygen-gas-class1 tig-oxygen-gas-class2'],
            'target' => '_self',
          ],
        ],
      ],
      'weight' => 2,
      'menu_name' => 'tig',
      'uuid' => 'd2385dc8-e70d-490f-8ec7-d95c828c98ac',
      'expanded' => FALSE,
      'enabled' => FALSE,
      'parent' => 'menu_link_content:54d1a5db-c9c1-4a09-98fa-26905094f77e',
    ])->save();

    MenuLinkContent::create([
      'title' => 'SMAW',
      'link' => 'https://en.wikipedia.org/wiki/Shielded_metal_arc_welding',
      'menu_name' => 'arc',
      'uuid' => '73e0f490-803c-439a-a15c-2d1c3d4ceac7',
    ])->save();
  }

}
