<?php

namespace Drupal\Tests\pb_content_analytics\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\pb_content_analytics\Service\AnalyticsSyncService;
use GuzzleHttp\ClientInterface;
use Psr\Log\NullLogger;

/**
 * Tests analytics updates against real field and revision storage.
 *
 * @group pb_content_analytics
 */
class AnalyticsSyncRegressionTest extends KernelTestBase {

  protected static $modules = ['system', 'user', 'field', 'node', 'text', 'filter'];

  /**
   * Malformed data cannot clear counters; old revisions remain unchanged.
   */
  public function testCountersAndRevisionHistory(): void {
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['node', 'filter']);
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    foreach (['field_like_count' => 'integer', 'field_read_count' => 'integer', 'field_analytics_updated' => 'timestamp'] as $name => $type) {
      FieldStorageConfig::create(['field_name' => $name, 'entity_type' => 'node', 'type' => $type])->save();
      FieldConfig::create(['field_name' => $name, 'entity_type' => 'node', 'bundle' => 'article'])->save();
    }
    $node = Node::create(['type' => 'article', 'title' => 'Test', 'field_like_count' => 7, 'field_read_count' => 20, 'field_analytics_updated' => 1]);
    $node->save();
    $old_vid = $node->getRevisionId();
    $node->setNewRevision(TRUE);
    $node->save();
    $service = new AnalyticsSyncService($this->createMock(ClientInterface::class), $this->container->get('database'),
      $this->container->get('entity_type.manager'), $this->container->get('config.factory'), new NullLogger());
    $stats = $service->processBatch([$node->id() => [
      'content_type' => 'article', 'total_likes' => 11, 'total_reads' => 40, 'last_updated' => '2026-11-16T00:00:00Z',
    ]]);
    $this->assertSame(1, $stats['updated']);
    $this->assertSame(11, (int) Node::load($node->id())->get('field_like_count')->value);
    $old = $this->container->get('entity_type.manager')->getStorage('node')->loadRevision($old_vid);
    $this->assertInstanceOf(\Drupal\node\NodeInterface::class, $old);
    assert($old instanceof \Drupal\node\NodeInterface);
    $this->assertSame(7, (int) $old->get('field_like_count')->value);
    foreach ([NULL, ['content_type' => []], ['content_type' => 'article', 'total_likes' => -1, 'total_reads' => 10, 'last_updated' => 'invalid']] as $bad) {
      $stats = $service->processBatch([$node->id() => $bad]);
      $this->assertSame(1, $stats['skipped_invalid_data']);
    }
    $this->assertSame(11, (int) Node::load($node->id())->get('field_like_count')->value);
  }

}
