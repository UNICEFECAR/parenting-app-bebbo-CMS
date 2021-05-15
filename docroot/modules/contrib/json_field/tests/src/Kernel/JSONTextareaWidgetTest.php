<?php

namespace Drupal\Tests\json_field\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;

/**
 * @coversDefaultClass \Drupal\json_field\Plugin\Field\FieldWidget\JSONTextareaWidget
 *
 * @group json_field
 */
class JSONTextareaWidgetTest extends KernelTestBase {

  /**
   * Tests that we can save form settings without error.
   */
  public function testWidgetSettings() {
    $this->createTestField();

    $entity_form_display = EntityFormDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
    ]);
    $entity_form_display->setComponent('test_json_field', ['type' => 'json_textarea']);
    $entity_form_display->save();
  }

}
