<?php

namespace Drupal\Tests\json_field_widget\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Tests\BrowserTestBase;

/**
 * Verify that JSON Field widget UI works as expected
 *
 * @group json_field
 */
class WidgetTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // This test helper loads everything that's needed.
    'json_field_widget_test_helper',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalLogin($this->rootUser);
  }

  /**
   * Test the widget functionality.
   */
  public function testWidget(): void {
    // In order to accommodate both local testing and drupalci, abstract out the
    // site root path and module paths for use later on.
    $base_path = base_path();
    $module_path = $base_path . $this->getModulePath('json_field_widget') . '/';

    // Load the Story node form and confirm the widget works as expected.
    $this->drupalGet('node/add/story');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('<link rel="stylesheet" media="all" href="' . $base_path . 'libraries/jsoneditor/dist/jsoneditor.min.css');
    $this->assertSession()->responseContains('<link rel="stylesheet" media="all" href="' . $module_path . 'assets/css/json_widget.css');
    $this->assertSession()->responseContains('<div class="field--type-json field--name-field-json field--widget-json-editor js-form-wrapper form-wrapper" data-drupal-selector="edit-field-json-wrapper" id="edit-field-json-wrapper">');
    $this->assertSession()->responseContains('<div class="js-form-item form-item js-form-type-textarea form-item-field-json-0-value js-form-item-field-json-0-value">');
    $this->assertSession()->responseContains('data-drupal-selector="edit-field-json-0-value" id="edit-field-json-0-value" name="field_json[0][value]" rows="5" cols="60" class="form-textarea"></textarea>');
    $this->assertSession()->responseContains('<script src="' . $base_path . 'libraries/jsoneditor/dist/jsoneditor.min.js');
    $this->assertSession()->responseContains('<script src="' . $module_path . 'assets/js/json_widget.js');

    // Confirm the settings passed to the field.
    $settings = $this->getDrupalSettings();
    $json_field_settings = $settings['json_field'];
    $this->assertTrue(!empty($json_field_settings));
    $keys = array_keys($json_field_settings);
    $editor_id = reset($keys);
    $this->assertSession()->responseContains('<textarea data-json-editor="' . $editor_id . '" data-drupal-selector="edit-field-json-0-value" id="edit-field-json-0-value" name="field_json[0][value]" rows="5" cols="60" class="form-textarea"></textarea>');
    $this->assertEquals($json_field_settings[$editor_id]['mode'], 'code');
    $this->assertEquals($json_field_settings[$editor_id]['modes'], [
      'code',
      'tree',
    ]);

    // Attempt saving some example data. Start with some JSON.
    $json = Json::encode([
      'test' => $this->randomString(),
      'fruit' => 'Mango',
      'nested' => [
        'first' => $this->randomString(),
        'second' => $this->randomString(),
      ],
    ]);
    $edit = [
      'title[0][value]' => 'Testing JSON Field',
      'field_json[0][value]' => $json,
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Story Testing JSON Field has been created');
  }

}
