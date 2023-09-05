<?php

namespace Drupal\Tests\tmgmt\Functional;

use Drupal\tmgmt\Entity\Job;

/**
 * Verifies basic functionality of the user interface
 *
 * @group tmgmt
 */
class TMGMTCartTest extends TMGMTTestBase {

  protected static $modules = array('tmgmt_content');

  /**
   * {@inheritdoc}
   */
  function setUp(): void {
    parent::setUp();

    // Login as admin to be able to set environment variables.
    $this->loginAsAdmin();
    $this->addLanguage('es');
    $this->addLanguage('sk');
    $this->addLanguage('cs');

    // Login as translator only with limited permissions to run these tests.
    $this->loginAsTranslator(array(
      'access administration pages',
      'create translation jobs',
      'submit translation jobs',
    ), TRUE);
  }

  /**
   * Test if the source is able to pull content in requested language.
   */
  function testCartEnforceSourceLanguage() {
    $content_type = $this->drupalCreateContentType();

    $node_sk = $this->drupalCreateNode(array(
      'title' => $this->randomMachineName(),
      'langcode' => 'sk',
      'type' => $content_type->id(),
    ));

    $node = $node_sk->addTranslation('en');

    $node->title->value = $this->randomMachineName();
    $node->body->value = $this->randomMachineName();
    $node->save();

    $node_cs = $this->drupalCreateNode(array(
      'title' => $this->randomMachineName(),
      'langcode' => 'cs',
      'type' => $content_type->id(),
    ));

    $this->loginAsTranslator();

    $job_item_sk = tmgmt_job_item_create('content', 'node', $node_sk->id());
    $job_item_sk->save();
    $this->drupalGet('tmgmt-add-to-cart/' . $job_item_sk->id());
    $job_items_data[$job_item_sk->getItemId()] = $job_item_sk->getItemType();

    $job_item_cs = tmgmt_job_item_create('content', 'node', $node_cs->id());
    $job_item_cs->save();
    $this->drupalGet('tmgmt-add-to-cart/' . $job_item_cs->id());
    $job_items_data[$job_item_cs->getItemId()] = $job_item_cs->getItemType();

    $this->drupalGet('admin/tmgmt/cart');
    $this->submitForm([
      'enforced_source_language' => TRUE,
      'source_language' => 'en',
      'target_language[]' => ['es'],
    ], t('Request translation'));

    $this->assertSession()->pageTextContains(t('One job needs to be checked out.'));
    $this->assertSession()->responseContains(t('One item skipped as for the language @language it was not possible to retrieve a translation.', array('@language' => 'English')));
    $this->assertSession()->pageTextContains(t('You have enforced the job source language which most likely resulted in having a translation of your original content as the job source text. You should review the job translation received from the translator carefully to prevent the content quality loss.'));

    $args = explode('/', $this->getUrl());
    $tjid = array_pop($args);

    $this->submitForm([], t('Submit to provider'));
    // We cannot test for the item data as items without a job are not able to
    // get the data in case the source language is overridden. Therefore only
    // testing for item_id and item_type values.
    foreach (Job::load($tjid)->getItems() as $job_item) {
      $this->assertEquals($job_items_data[$job_item->getItemId()], $job_item->getItemType());
    }

    $this->drupalGet('admin/tmgmt/cart');
    $this->assertSession()->pageTextContains($node_cs->getTitle());
    $this->assertSession()->pageTextNotContains($node_sk->getTitle());

    // Test that duplicate submission of an item is not allowed.
    $this->drupalGet('admin/tmgmt/cart');
    $this->submitForm([
      'target_language[]' => ['es'],
    ], t('Request translation'));
    $this->submitForm([], t('Submit to provider'));
    $this->assertSession()->pageTextContains(t('Test translation created.'));

    $job_item_cs = tmgmt_job_item_create('content', 'node', $node_cs->id());
    $job_item_cs->save();
    $this->drupalGet('tmgmt-add-to-cart/' . $job_item_cs->id());
    $job_items_data[$job_item_cs->getItemId()] = $job_item_cs->getItemType();

    $this->drupalGet('admin/tmgmt/cart');
    $this->submitForm([
      'target_language[]' => ['es'],
    ], t('Request translation'));
    $this->assertSession()->pageTextContains('1 item conflicts with pending item and will be dropped on submission. Conflicting item: ' . $node_cs->getTitle() . '.');
    $this->submitForm([], t('Submit to provider'));
    $this->assertSession()->pageTextContains(t('All job items are conflicting, the job can not be submitted.'));
  }

}
