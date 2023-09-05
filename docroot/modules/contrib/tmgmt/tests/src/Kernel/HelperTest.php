<?php

namespace Drupal\Tests\tmgmt\Kernel;

use Drupal\tmgmt\Entity\Job;

/**
 * Tests the helper functions in tmgmt.module.
 *
 * @group tmgmt
 */
class HelperTest extends TMGMTKernelTestBase {

  /**
   * Tests tmgmt_job_match_item()
   *
   * @see tmgmt_job_match_item
   */
  function testTMGTJobMatchItem() {
    $this->addLanguage('fr');
    $this->addLanguage('es');

    // Add a job from en to fr and en to sp.
    $job_en_fr = $this->createJob('en', 'fr');
    $job_en_sp = $this->createJob('en', 'es');

    // Add a job which has existing source-target combinations.
    $this->assertEquals($job_en_fr->id(), tmgmt_job_match_item('en', 'fr')->id());
    $this->assertEquals($job_en_sp->id(), tmgmt_job_match_item('en', 'es')->id());

    // Add a job which has no existing source-target combination.
    $this->assertTrue(tmgmt_job_match_item('fr', 'es') instanceof Job);
  }

  /**
   * Tests the itemLabel() function.
   *
   * @todo: Move into a unit test case once available.
   */
  function testDataIemLabel() {
    $no_label = array(
      '#text' => 'No label',
    );
    $this->assertEquals('No label', \Drupal::service('tmgmt.data')->itemLabel($no_label));
    $this->assertEquals('No la…', \Drupal::service('tmgmt.data')->itemLabel($no_label, 6));
    $label = array(
      '#parent_label' => array(),
      '#label' => 'A label',
    );
    $this->assertEquals('A label', \Drupal::service('tmgmt.data')->itemLabel($label));
    $this->assertEquals('A lab…', \Drupal::service('tmgmt.data')->itemLabel($label, 6));
    $parent_label = array(
      '#parent_label' => array('Parent label', 'Sub label'),
      '#label' => 'A label',
    );
    $this->assertEquals('Parent label > Sub label', \Drupal::service('tmgmt.data')->itemLabel($parent_label));
    $this->assertEquals('Parent… > Sub la…', \Drupal::service('tmgmt.data')->itemLabel($parent_label, 18));
    $nested = array(
      '#parent_label' => array('Parent label', 'Sub label', 'Sub-sub label'),
      '#label' => 'A label',
    );
    $this->assertEquals('Parent label > Sub label > Sub-sub label', \Drupal::service('tmgmt.data')->itemLabel($nested));
    $this->assertEquals('Parent… > Sub la… > Sub-su…', \Drupal::service('tmgmt.data')->itemLabel($nested, 28));
    $long_label = array(
      '#parent_label' => array('Loooooooooooong label', 'Short'),
      '#label' => 'A label',
    );
    $this->assertEquals('Loooooooooooong label > Short', \Drupal::service('tmgmt.data')->itemLabel($long_label));
    $this->assertEquals('Loooooooooooong label > Short', \Drupal::service('tmgmt.data')->itemLabel($long_label, 30));
    $node_example = array(
      '#parent_label' => array('This is a very loooong title, so looong', 'Body', 'Delta #0', 'Body'),
      '#label' => 'A label',
    );
    $this->assertEquals('This is a very loooong title, so looong > Body > Delta #0 > Body', \Drupal::service('tmgmt.data')->itemLabel($node_example));
    $this->assertEquals('This is a very loooong title, … > Body > Delta #0 > Body', \Drupal::service('tmgmt.data')->itemLabel($node_example, 56));
  }

  function testWordCount() {
    $unit_tests = array(
      'empty' => array(
        'text' => '',
        'count' => 0,
      ),
      'latin' => array(
        'text' => 'Drupal is the best!',
        'count' => 4,
      ),
      'non-latin' => array(
        'text' => 'Друпал лучший!',
        'count' => 2,
      ),
      'complex punctuation' => array(
        'text' => '<[({-!ReAd@*;: ,?+MoRe...})]>\\|/',
        'count' => 2,
        'exclude_tags' => FALSE,
      ),
      'repeat' => array(
        'text' => 'repeat repeat',
        'count' => 2,
      ),
      'strip tags' => array(
        'text' => '<a href="http://example.com">link text</a> plain text <div class="some-css-class"></div>',
        'count' => 4,
      ),
    );
    $config = $this->config('tmgmt.settings');
    foreach ($unit_tests as $id => $test_data) {
      // Set the exclude_tags flag. In case not provided the TRUE is default.
      $test_data += array('exclude_tags' => TRUE);
      if ($config->get('word_count_exclude_tags') != $test_data['exclude_tags']) {
        $config->set('word_count_exclude_tags', $test_data['exclude_tags'])->save();
      }
      $this->assertEquals($test_data['count'], \Drupal::service('tmgmt.data')->wordCount($test_data['text']));
    }
  }
}
