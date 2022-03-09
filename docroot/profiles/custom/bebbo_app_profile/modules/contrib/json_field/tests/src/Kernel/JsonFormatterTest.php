<?php

namespace Drupal\Tests\json_field\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\entity_test\Entity\EntityTest;

/**
 * @coversDefaultClass \Drupal\json_field\Plugin\Field\FieldFormatter\JSONFormatter
 *
 * @group json_field
 */
class JsonFormatterTest extends KernelTestBase {

  /**
   * Tests that the formatter is rendering an empty array.
   */
  public function testFormatter() {
    $this->createTestField();

    $entity_view_display = EntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
    ]);
    $entity_view_display->setComponent('test_json_field', []);
    $entity_view_display->save();

    $entity = EntityTest::create([
      'test_json_field' => json_encode([]),
    ]);
    $entity->save();

    $build = $entity_view_display->build($entity);

    $content = $this->container->get('renderer')->renderRoot($build);
    $this->assertEquals('<pre><code>[]</code></pre>', $content);
  }

  /**
   * Tests that the formatter is rendering data.
   */
  public function testFormatterWithData() {
    $this->createTestField([]);

    $entity_view_display = EntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
    ]);
    $entity_view_display->setComponent('test_json_field', []);
    $entity_view_display->save();

    $entity = EntityTest::create([
      'test_json_field' => json_encode(['Looking for a' => 'complication']),
    ]);
    $entity->save();

    $build = $entity_view_display->build($entity);
    $content = $this->container->get('renderer')->renderRoot($build);
    $this->assertEquals('<pre><code>{"Looking for a":"complication"}</code></pre>', $content);
  }

}
