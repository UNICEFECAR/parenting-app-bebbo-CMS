<?php

namespace Drupal\Tests\tmgmt\Functional;

use Drupal\file\Entity\File;
use Drupal\filter\Entity\FilterFormat;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\Entity\JobItem;

/**
 * Verifies the UI of the review form.
 *
 * @group tmgmt
 */
class TMGMTUiReviewTest extends TMGMTTestBase {
  use TmgmtEntityTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['tmgmt_content', 'image', 'node'];

  /**
   * {@inheritdoc}
   */
  function setUp(): void {
    parent::setUp();

    $this->addLanguage('de');

    $filtered_html_format = FilterFormat::create(array(
      'format' => 'filtered_html',
      'name' => 'Filtered HTML',
    ));
    $filtered_html_format->save();

    $this->drupalCreateContentType(array('type' => 'test_bundle'));

    $this->loginAsAdmin(array(
      'create translation jobs',
      'submit translation jobs',
      'create test_bundle content',
      $filtered_html_format->getPermissionName(),
    ));

    \Drupal::service('file_system')->copy(DRUPAL_ROOT . '/core/misc/druplicon.png', 'public://example.jpg');
    $this->image = File::create(array(
      'uri' => 'public://example.jpg',
    ));
    $this->image->save();
  }

  /**
   * Tests text format permissions on translation fields.
   */
  public function testTextFormatPermissions() {
    // Create a job.
    $job1 = $this->createJob();
    $job1->save();
    $job1->setState(Job::STATE_ACTIVE);

    \Drupal::state()->set('tmgmt.test_source_data', array(
      'title' => array(
        'deep_nesting' => array(
          '#text' => '<p><em><strong>Six dummy HTML tags in the title.</strong></em></p>',
          '#label' => 'Title',
        ),
      ),
      'body' => array(
        'deep_nesting' => array(
          '#text' => '<p>Two dummy HTML tags in the body.</p>',
          '#label' => 'Body',
          '#format' => 'full_html',
        )
      ),
    ));
    $item1 = $job1->addItem('test_source', 'test', 1);
    $item1->setState(JobItem::STATE_REVIEW);
    $item1->save();
    $this->drupalGet('admin/tmgmt/items/' . $item1->id());

    // Assert that translator has no permission to review/update "body" field.
    $source_field_message = $this->xpath('//*[@id="edit-bodydeep-nesting-source"]')[0];
    $translation_field_message = $this->xpath('//*[@id="edit-bodydeep-nesting-translation"]')[0];
    $this->assertEquals(t('This field has been disabled because you do not have sufficient permissions to edit it. It is not possible to review or accept this job item.'), $source_field_message->getText());
    $this->assertEquals(t('This field has been disabled because you do not have sufficient permissions to edit it. It is not possible to review or accept this job item.'), $translation_field_message->getText());
    $this->assertSession()->responseNotContains('Save as completed" class="button button--primary js-form-submit form-submit"');

    // Remove full html format from the body field.
    $item1->updateData('body|deep_nesting', ['#format' => '']);
    $item1->save();

    // Translator should see enabled translation field again.
    $this->drupalGet('admin/tmgmt/items/' . $item1->id());
    $this->assertSession()->responseContains('Save as completed" class="button button--primary js-form-submit form-submit"');
    $this->assertSession()->fieldExists('body|deep_nesting[translation]');
    $translation_field = $this->xpath('//*[@id="edit-bodydeep-nesting-translation"]')[0];
    $this->assertEquals('', $translation_field->getText());
  }

}
