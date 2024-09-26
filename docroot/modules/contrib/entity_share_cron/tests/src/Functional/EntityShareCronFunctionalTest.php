<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_cron\Functional;

use Drupal\entity_share_cron\EntityShareCronServiceInterface;
use Drupal\entity_share_cron\HookHandler\CronHookHandler;
use Drupal\node\NodeInterface;
use Drupal\Tests\entity_share_client\Functional\EntityShareClientFunctionalTestBase;

/**
 * General functional test class.
 *
 * @group entity_share_cron
 */
class EntityShareCronFunctionalTest extends EntityShareClientFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_share_cron',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'node';

  /**
   * {@inheritdoc}
   */
  protected static $entityBundleId = 'es_test';

  /**
   * {@inheritdoc}
   */
  protected static $entityLangcode = 'en';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->postSetupFixture();
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesDataArray(): array {
    $nodes_infos = [];
    for ($i = 1; $i <= 300; ++$i) {
      $nodes_infos['es_test_' . $i] = $this->getCompleteNodeInfos([
        'title' => [
          'value' => 'Node ' . $i,
          'checker_callback' => 'getValue',
        ],
        'status' => [
          'value' => NodeInterface::PUBLISHED,
          'checker_callback' => 'getValue',
        ],
      ]);
    }

    return [
      'node' => [
        'en' => $nodes_infos,
      ],
    ];
  }

  /**
   * Test cron feature.
   */
  public function testCron(): void {
    $queue_factory = $this->container->get('queue');
    $state_storage = $this->container->get('state');
    $channel_id = \key($this->channels);

    // Set entity_share_cron.settings to check pagination handling.
    $entity_share_cron_settings = $this->config('entity_share_cron.settings');
    $entity_share_cron_settings->set('page_limit', 2);
    $entity_share_cron_settings->set('remotes', [
      $this->remote->id() => [
        'enabled' => TRUE,
        'channels' => [
          $channel_id => [
            'enabled' => TRUE,
            'import_config' => $this->importConfig->id(),
            'operations' => [
              'create' => TRUE,
              'update' => TRUE,
            ],
          ],
        ],
      ],
    ]);
    $entity_share_cron_settings->save();

    // Check that there are no contents and nothing in queue.
    $number_of_content = $this->entityTypeManager->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();
    $this->assertEquals(0, $number_of_content, 'There are no contents on the client website before cron run.');
    $queue = $queue_factory->get(EntityShareCronServiceInterface::PENDING_QUEUE_NAME);
    $this->assertEquals(0, $queue->numberOfItems(), 'The queue is empty.');

    $this->container->get('cron')->run();

    // Check that after the first cron run, the first two pages had been
    // imported. And the next one is in the queue.
    $number_of_content = $this->entityTypeManager->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();
    $this->assertEquals(100, $number_of_content, 'There are 100 contents on the client website after the first cron run. The first 2 pages had been imported.');
    $queue = $queue_factory->get(EntityShareCronServiceInterface::PENDING_QUEUE_NAME);
    $this->assertEquals(1, $queue->numberOfItems(), 'The next page had been queued.');

    $this->container->get('cron')->run();

    // Check that after the second cron run, the next two pages had been
    // imported. And the next one is in the queue.
    $number_of_content = $this->entityTypeManager->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();
    $this->assertEquals(200, $number_of_content, 'There are 200 contents on the client website after the second cron run. The first 4 pages had been imported.');
    $queue = $queue_factory->get(EntityShareCronServiceInterface::PENDING_QUEUE_NAME);
    $this->assertEquals(1, $queue->numberOfItems(), 'The next page had been queued.');

    $this->container->get('cron')->run();

    // Check that after the third cron run, the last two pages had been
    // imported. And the queue is empty as there is no more pages to import.
    $number_of_content = $this->entityTypeManager->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();
    $this->assertEquals(300, $number_of_content, 'There are 300 contents on the client website after the second cron run. All the pages had been imported.');
    $queue = $queue_factory->get(EntityShareCronServiceInterface::PENDING_QUEUE_NAME);
    $this->assertEquals(0, $queue->numberOfItems(), 'The queue is empty as there is no more pages to import.');

    // Test update operation. Delete one content and change one to see
    // that the deleted content will not be recreated and the changed one will
    // be updated.
    $state_storage->delete(CronHookHandler::STATE_ID);
    $content_to_delete = $this->loadEntity('node', 'es_test_1');
    $this->assertNotNull($content_to_delete);
    $content_to_delete->delete();
    $content_to_update = $this->loadEntity('node', 'es_test_2');
    $this->assertNotNull($content_to_update);
    $content_to_update->set('title', 'another title');
    $content_to_update->save();

    $entity_share_cron_settings->set('remotes', [
      $this->remote->id() => [
        'enabled' => TRUE,
        'channels' => [
          $channel_id => [
            'enabled' => TRUE,
            'import_config' => $this->importConfig->id(),
            'operations' => [
              'create' => FALSE,
              'update' => TRUE,
            ],
          ],
        ],
      ],
    ]);
    $entity_share_cron_settings->save();

    $this->container->get('cron')->run();

    $deleted_content = $this->loadEntity('node', 'es_test_1');
    $this->assertNull($deleted_content, 'The deleted content had not been re-imported.');
    $updated_content = $this->loadEntity('node', 'es_test_2');
    $this->assertNotNull($updated_content);
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $updated_content */
    $this->assertEquals('Node 2', $updated_content->label(), 'The changed content had been updated.');

    // Test create operation. Change an existing content and see that it will
    // not be updated and that the previously deleted content will be created.
    $state_storage->delete(CronHookHandler::STATE_ID);
    $updated_content->set('title', 'another title 2');
    $updated_content->save();

    $entity_share_cron_settings->set('remotes', [
      $this->remote->id() => [
        'enabled' => TRUE,
        'channels' => [
          $channel_id => [
            'enabled' => TRUE,
            'import_config' => $this->importConfig->id(),
            'operations' => [
              'create' => TRUE,
              'update' => FALSE,
            ],
          ],
        ],
      ],
    ]);
    $entity_share_cron_settings->save();

    $this->container->get('cron')->run();

    $deleted_content = $this->loadEntity('node', 'es_test_1');
    $this->assertNotNull($deleted_content, 'The deleted content had been re-imported.');
    $updated_content = $this->loadEntity('node', 'es_test_2');
    $this->assertNotNull($updated_content);
    $this->assertEquals('another title 2', $updated_content->label(), 'The changed content had not been updated.');
  }

}
