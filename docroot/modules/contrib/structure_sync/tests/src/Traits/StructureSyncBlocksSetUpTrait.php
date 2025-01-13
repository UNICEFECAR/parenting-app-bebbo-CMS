<?php

namespace Drupal\Tests\structure_sync\Traits;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Provides basic content for tests.
 */
trait StructureSyncBlocksSetUpTrait {

  /**
   * {@inheritdoc}
   */
  public function createSetUpContent(): void {
    $this->blockContentStorage = $this->container->get('entity_type.manager')->getStorage('block_content');

    // Create the "basic" block type.
    $bundle = BlockContentType::create([
      'id' => 'basic',
      'label' => 'basic',
      'revision' => FALSE,
    ]);
    $bundle->save();

    // Code taken from \block_content_add_body_field from block_content.module.
    FieldConfig::create([
      'field_storage' => FieldStorageConfig::loadByName('block_content', 'body'),
      'bundle' => 'basic',
      'label' => 'Body',
      'settings' => ['display_summary' => FALSE],
    ])->save();

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = $this->container->get('entity_display.repository');

    $display_repository
      ->getFormDisplay('block_content', 'basic')
      ->setComponent('body', [
        'type' => 'text_textarea_with_summary',
      ])->save();

    $display_repository
      ->getViewDisplay('block_content', 'basic')
      ->setComponent('body', [
        'label' => 'hidden',
        'type' => 'text_default',
      ])->save();

    // Create blocks to test different cases.
    BlockContent::create([
      'info' => 'Wire rod',
      'type' => 'basic',
      'body' => [
        'value' => 'Machine-fed wire rod.',
        'format' => 'plain_text',
      ],
      'revision' => FALSE,
      'reusable' => FALSE,
    ])->save();

    BlockContent::create([
      'info' => 'TIG Rod',
      'type' => 'basic',
      'body' => [
        [
          'value' => '<p>Hand-held welding rod.</p>',
          'format' => 'basic_html',
        ],
      ],
    ])->save();

    BlockContent::create([
      'info' => 'Gas',
      'type' => 'basic',
      'body' => [
        [
          'value' => 'Gas used with TIG.',
          'format' => 'plain_text',
        ],
      ],
    ])->save();

    BlockContent::create([
      'info' => 'Argon',
      'type' => 'basic',
      'body' => [
        [
          'value' => 'Argon gas',
          'format' => 'plain_text',
        ],
      ],
    ])->save();

    BlockContent::create([
      'info' => 'Oxygen',
      'type' => 'basic',
      'body' => [
        [
          'value' => 'Oxygen gas',
          'format' => 'plain_text',
        ],
      ],
    ])->save();

    BlockContent::create([
      'info' => 'SMAW',
      'type' => 'basic',
    ])->save();
  }

}
