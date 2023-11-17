<?php

namespace Drupal\Tests\tmgmt_local\Functional;

use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\JobItemInterface;
use Drupal\node\Entity\Node;

/**
 * Test Continuous jobs support in the local translator.
 *
 * @group tmgmt
 */
class LocalTranslatorContinuousTest extends LocalTranslatorTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = array('tmgmt_content');

  /**
   * Test continuous Jobs in TMGMT local.
   */
  public function testContinuousJobs() {
    $type = $this->drupalCreateContentType();

    // Enable entity translations for nodes.
    $content_translation_manager = \Drupal::service('content_translation.manager');
    $content_translation_manager->setEnabled('node', $type->label(), TRUE);

    $this->assignee = $this->drupalCreateUser($this->localTranslatorPermissions);
    $this->drupalLogin($this->assignee);
    $this->drupalGet($this->assignee->toUrl('edit-form'));
    $edit = array(
      'tmgmt_translation_skills[0][language_from]' => 'en',
      'tmgmt_translation_skills[0][language_to]' => 'de',
    );
    $this->submitForm($edit, t('Save'));
    $this->loginAsAdmin($this->localManagerPermissions);

    // Test continuous integration.
    $this->config('tmgmt.settings')
      ->set('submit_job_item_on_cron', TRUE)
      ->save();

    // Continuous settings configuration.
    $continuous_settings = [
      'content' => [
        'node' => [
          'enabled' => 1,
          'bundles' => [
            $type->id() => 1,
          ],
        ],
      ],
    ];

    $translator = $this->createTranslator(['plugin' => 'local']);

    $continuous_job = $this->createJob('en', 'de', 0, [
      'label' => 'Continuous job',
      'job_type' => Job::TYPE_CONTINUOUS,
      'translator' => $translator,
      'continuous_settings' => $continuous_settings,
    ]);
    $continuous_job->save();

    // Create an english node.
    $node = Node::create([
      'title' => $this->randomMachineName(),
      'uid' => 0,
      'type' => $type->id(),
      'langcode' => 'en',
    ]);
    $node->save();

    $continuous_job_items = $continuous_job->getItems();
    $this->assertCount(1, $continuous_job_items);
    $continuous_job_item = reset($continuous_job_items);
    $this->assertTrue($continuous_job_item->getState() == JobItemInterface::STATE_INACTIVE);

    tmgmt_cron();

    $this->drupalGet('translate');
    $this->clickLink('View');

    // Assign to user.
    $edit = array(
      'tuid' => $this->assignee->id(),
    );
    $this->submitForm($edit, t('Save task'));

    $this->drupalLogin($this->assignee);
    $this->drupalGet('translate');
    $this->clickLink('View');
    $this->clickLink('Translate');

    $edit = array(
      'title|0|value[translation]' => 'Text für Job Element mit Typ-Knoten und ID 1.',
    );
    $this->submitForm($edit, t('Save as completed'));

    $items = $continuous_job->getItems();
    $item = reset($items);

    // Check is set to need review.
    $data = $item->getData();
    $this->assertEquals('Text für Job Element mit Typ-Knoten und ID 1.', $data['title']['0']['value']['#translation']['#text']);
    $this->assertTrue($continuous_job->getState() == Job::STATE_CONTINUOUS);
    $this->assertTrue($item->getState() == JobItemInterface::STATE_REVIEW);
  }

}
