<?php

namespace Drupal\Tests\tmgmt_config\Kernel;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\tmgmt\Kernel\TMGMTKernelTestBase;
use Drupal\views\Entity\View;

/**
 * Unit tests for exporting translatable data from config entities and saving it back.
 *
 * @group tmgmt
 */
class ConfigSourceUnitTest extends TMGMTKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = array('tmgmt', 'tmgmt_config', 'tmgmt_test', 'node', 'filter', 'language', 'config_translation', 'locale', 'views', 'views_ui', 'options');

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Add the languages.
    $this->installConfig(['language']);

    $this->installEntitySchema('tmgmt_job');
    $this->installEntitySchema('tmgmt_job_item');
    $this->installEntitySchema('tmgmt_message');
    $this->installSchema('locale', array('locales_location', 'locales_source', 'locales_target'));

    \Drupal::service('router.builder')->rebuild();

    tmgmt_translator_auto_create(\Drupal::service('plugin.manager.tmgmt.translator')->getDefinition('test_translator'));
  }

  /**
   * Tests the node type config entity.
   */
  public function testNodeType() {
    // Create an english test entity.
    $node_type = NodeType::create(array(
      'type' => 'test',
      'name' => 'Node type name',
      'description' => 'Node type description',
      'title_label' => 'Title label',
      'langcode' => 'en',
    ));
    $node_type->save();

    $job = tmgmt_job_create('en', 'de');
    $job->translator = 'test_translator';
    $job->save();
    $job_item = tmgmt_job_item_create('config', 'node_type', 'node.type.' . $node_type->id(), array('tjid' => $job->id()));
    $job_item->save();

    $source_plugin = $this->container->get('plugin.manager.tmgmt.source')->createInstance('config');
    $data = $source_plugin->getData($job_item);

    // Test the name property.
    $this->assertEquals('Name', $data['name']['#label']);
    $this->assertEquals($node_type->label(), $data['name']['#text']);
    $this->assertTrue($data['name']['#translate']);
    $this->assertEquals('Description', $data['description']['#label']);
    $this->assertEquals($node_type->getDescription(), $data['description']['#text']);
    $this->assertTrue($data['description']['#translate']);

    // Test item types.
    $this->assertEquals(t('Content type'), $source_plugin->getItemTypes()['node_type']);

    // Now request a translation and save it back.
    $job->requestTranslation();
    $items = $job->getItems();
    $item = reset($items);
    $item->acceptTranslation();
    $data = $item->getData();

    // Check that the translations were saved correctly.
    $language_manager = \Drupal::languageManager();
    $language_manager->setConfigOverrideLanguage($language_manager->getLanguage('de'));
    $node_type = NodeType::load($node_type->id());

    $this->assertEquals($data['name']['#translation']['#text'], $node_type->label());
    $this->assertEquals($data['description']['#translation']['#text'], $node_type->getDescription());
  }

  /**
   * Tests the view config entity
   */
  public function testView() {
    $this->installConfig(['system', 'tmgmt']);
    $job = tmgmt_job_create('en', 'de');
    $job->translator = 'test_translator';
    $job->save();
    $job_item = tmgmt_job_item_create('config', 'view', 'views.view.tmgmt_job_overview', array('tjid' => $job->id()));
    $job_item->save();
    $view = View::load('tmgmt_job_overview');

    $source_plugin = $this->container->get('plugin.manager.tmgmt.source')->createInstance('config');
    $data = $source_plugin->getData($job_item);

    // Test the name property.
    $this->assertEquals('Label', $data['label']['#label']);
    $this->assertEquals($view->label(), $data['label']['#text']);
    $this->assertTrue($data['label']['#translate']);
    $this->assertEquals('Administrative description', $data['description']['#label']);
    $this->assertEquals('Gives a bulk operation overview of translation jobs in the system.', $data['description']['#text']);
    $this->assertTrue($data['description']['#translate']);
    $this->assertEquals('Master', $data['display']['default']['display_title']['#text']);
    $this->assertEquals('Submit button text', $data['display']['default']['display_options']['exposed_form']['options']['submit_button']['#label']);
    $this->assertEquals('Items per page label', $data['display']['default']['display_options']['pager']['options']['expose']['items_per_page_label']['#label']);

    // Tests for labels on more levels.
    $this->assertEquals('Exposed options', $data['display']['default']['display_options']['pager']['options']['expose']['#label']);
    $this->assertEquals('Paged output, full pager', $data['display']['default']['display_options']['pager']['options']['#label']);
    $this->assertEquals('Pager', $data['display']['default']['display_options']['pager']['#label']);
    $this->assertEquals('Default display options', $data['display']['default']['display_options']['#label']);
    $this->assertEquals('Display settings', $data['display']['default']['#label']);

    // Test item types.
    $this->assertEquals(t('View'), $source_plugin->getItemTypes()['view']);

    // Now request a translation and save it back.
    $job->requestTranslation();
    $items = $job->getItems();
    $item = reset($items);
    $item->acceptTranslation();
    $data = $item->getData();

    // Check that the translations were saved correctly.
    $language_manager = \Drupal::languageManager();
    $language_manager->setConfigOverrideLanguage($language_manager->getLanguage('de'));
    $view = View::load('tmgmt_job_overview');

    $this->assertEquals($data['label']['#translation']['#text'], $view->label());
    $this->assertEquals($data['description']['#translation']['#text'], $view->get('description'));

    $display = $view->get('display');
    $this->assertEquals($data['label']['#translation']['#text'], $display['default']['display_options']['title']);
    $this->assertEquals($data['display']['default']['display_options']['exposed_form']['options']['submit_button']['#translation']['#text'], $display['default']['display_options']['exposed_form']['options']['submit_button']);
  }

  /**
   * Tests the view of the system site.
   */
  public function testSystemSite() {
    $this->installConfig(['system']);
    $this->config('system.site')->set('slogan', 'Test slogan')->save();
    $job = tmgmt_job_create('en', 'de');
    $job->translator = 'test_translator';
    $job->save();
    $job_item = tmgmt_job_item_create('config', '_simple_config', 'system.site_information_settings', array('tjid' => $job->id()));
    $job_item->save();

    $source_plugin = $this->container->get('plugin.manager.tmgmt.source')->createInstance('config');
    $data = $source_plugin->getData($job_item);

    // Test the name property.
    $this->assertEquals('Slogan', $data['slogan']['#label']);
    $this->assertEquals('Test slogan', $data['slogan']['#text']);
    $this->assertTrue($data['slogan']['#translate']);

    // Test item types.
    $this->assertEquals(t('View'), $source_plugin->getItemTypes()['view']);

    // Now request a translation and save it back.
    $job->requestTranslation();
    $items = $job->getItems();
    $item = reset($items);
    $item->acceptTranslation();
    $data = $item->getData();

    // Check that the translations were saved correctly.
    $language_manager = \Drupal::languageManager();
    $language_manager->setConfigOverrideLanguage($language_manager->getLanguage('de'));

    $this->assertEquals($data['slogan']['#translation']['#text'], \Drupal::config('system.site')->get('slogan'));
  }
  /**
   * Tests the user config entity.
   */
  public function testAccountSettings() {
    $this->installConfig(['user']);
    $this->config('user.settings')->set('anonymous', 'Test Anonymous')->save();
    $job = tmgmt_job_create('en', 'de');
    $job->translator = 'test_translator';
    $job->save();
    $job_item = tmgmt_job_item_create('config', '_simple_config', 'entity.user.admin_form', array('tjid' => $job->id()));
    $job_item->save();

    $source_plugin = $this->container->get('plugin.manager.tmgmt.source')->createInstance('config');
    $data = $source_plugin->getData($job_item);

    // Test the name property.
    $this->assertEquals('Name', $data['user__settings']['anonymous']['#label']);
    $this->assertEquals('Test Anonymous', $data['user__settings']['anonymous']['#text']);
    $this->assertTrue($data['user__settings']['anonymous']['#translate']);

    // Test item types.
    $this->assertEquals(t('View'), $source_plugin->getItemTypes()['view']);

    // Now request a translation and save it back.
    $job->requestTranslation();
    $items = $job->getItems();
    $item = reset($items);
    $item->acceptTranslation();
    $data = $item->getData();

    // Check that the translations were saved correctly.
    $language_manager = \Drupal::languageManager();
    $language_manager->setConfigOverrideLanguage($language_manager->getLanguage('de'));

    $this->assertEquals($data['user__settings']['anonymous']['#translation']['#text'], \Drupal::config('user.settings')->get('anonymous'));
  }
}
