<?php

namespace Drupal\Tests\tmgmt_config\Functional;

use Drupal\Core\Url;
use Drupal\Tests\tmgmt\Functional\TmgmtEntityTestTrait;
use Drupal\Tests\tmgmt\Functional\TMGMTTestBase;
use Drupal\tmgmt\Entity\Job;
use Drupal\views\Entity\View;

/**
 * Content entity source UI tests.
 *
 * @group tmgmt
 */
class ConfigSourceUiTest extends TMGMTTestBase {
  use TmgmtEntityTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = array('tmgmt_config', 'views', 'views_ui', 'field_ui', 'config_translation');

  /**
   * {@inheritdoc}
   */
  function setUp(): void {
    parent::setUp();

    $this->loginAsAdmin(array(
      'create translation jobs',
      'submit translation jobs',
      'accept translation jobs',
    ));

    $this->addLanguage('de');
    $this->addLanguage('it');
    $this->addLanguage('es');
    $this->addLanguage('el');

    $this->createNodeType('article', 'Article', TRUE);
  }

  /**
   * Test the node type for a single checkout.
   */
  function testNodeTypeTranslateTabSingleCheckout() {
    $this->loginAsTranslator(array('translate configuration'));

    // Go to the translate tab.
    $this->drupalGet('admin/structure/types/manage/article/translate');

    // Assert some basic strings on that page.
    $this->assertSession()->pageTextContains(t('Translations of Article content type'));
    $this->assertSession()->pageTextContains(t('There are 0 items in the translation cart.'));

    // Request a translation for german.
    $edit = array(
      'languages[de]' => TRUE,
    );
    $this->submitForm($edit, t('Request translation'));

    // Verify that we are on the translate tab.
    $this->assertSession()->pageTextContains(t('One job needs to be checked out.'));
    $this->assertSession()->pageTextContains('Article content type (English to German, Unprocessed)');

    // Submit.
    $this->submitForm([], t('Submit to provider'));

    // Make sure that we're back on the originally defined destination URL.
    $this->assertSession()->addressEquals('admin/structure/types/manage/article/translate');

    // We are redirected back to the correct page.
    $this->drupalGet('admin/structure/types/manage/article/translate');

    // Translated languages - german should now be listed as Needs review.
    $rows = $this->xpath('//tbody/tr');
    $found = FALSE;
    foreach ($rows as $value) {
      $image = $value->find('css', 'td:nth-child(3) a img');
      if ($image && $image->getAttribute('title') == 'Needs review') {
        $found = TRUE;
        $this->assertEquals('German', $value->find('css', 'td:nth-child(2)')->getText());
      }
    }
    $this->assertTrue($found);

    // Assert that 'Source' label is displayed properly.
    $this->assertSession()->responseContains('<strong>Source</strong>');

    // Verify that the pending translation is shown.
    $this->clickLinkWithImageTitle('Needs review');
    $this->submitForm([], t('Save'));

    // Request a spanish translation.
    $edit = array(
      'languages[es]' => TRUE,
    );
    $this->submitForm($edit, t('Request translation'));

    // Verify that we are on the checkout page.
    $this->assertSession()->pageTextContains(t('One job needs to be checked out.'));
    $this->assertSession()->pageTextContains('Article content type (English to Spanish, Unprocessed)');
    $this->submitForm([], t('Submit to provider'));

    // Make sure that we're back on the originally defined destination URL.
    $this->assertSession()->addressEquals('admin/structure/types/manage/article/translate');

    // Translated languages should now be listed as Needs review.
    $rows = $this->xpath('//tbody/tr');
    $counter = 0;
    foreach ($rows as $element) {
      $language = $element->find('css', 'td:nth-child(2)')->getText();
      if ('Spanish' == $language || 'German' == $language) {
        $this->assertEquals('Needs review', $element->find('css', 'td:nth-child(3) a img')->getAttribute('title'));
        $counter++;
      }
    }
    $this->assertEquals(2, $counter);

    // Test that a job can not be accepted if the translator does not exist.
    // Request an italian translation.
    $edit = array(
      'languages[it]' => TRUE,
    );
    $this->submitForm($edit, t('Request translation'));

    // Go back to the originally defined destination URL without submitting.
    $this->drupalGet('admin/structure/types/manage/article/translate');

    // Verify that the pending translation is shown.
    $this->clickLink(t('Inactive'));

    // Try to save, should fail because the job has no translator assigned.
    $edit = array(
      'name[translation]' => $this->randomMachineName(),
    );
    $this->submitForm($edit, t('Save'));

    // Verify that we are on the checkout page.
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Test the node type for a single checkout.
   */
  function testNodeTypeTranslateTabMultipeCheckout() {
    $this->loginAsTranslator(array('translate configuration'));

    // Go to the translate tab.
    $this->drupalGet('admin/structure/types/manage/article/translate');

    // Assert some basic strings on that page.
    $this->assertSession()->pageTextContains(t('Translations of Article content type'));
    $this->assertSession()->pageTextContains(t('There are 0 items in the translation cart.'));

    // Request a translation for german and spanish.
    $edit = array(
      'languages[de]' => TRUE,
      'languages[es]' => TRUE,
    );
    $this->submitForm($edit, t('Request translation'));

    // Verify that we are on the translate tab.
    $this->assertSession()->pageTextContains(t('2 jobs need to be checked out.'));

    // Submit all jobs.
    $this->assertSession()->pageTextContains('Article content type (English to German, Unprocessed)');
    $this->submitForm([], t('Submit to provider and continue'));
    $this->assertSession()->pageTextContains('Article content type (English to Spanish, Unprocessed)');
    $this->submitForm([], t('Submit to provider'));

    // Make sure that we're back on the translate tab.
    $this->assertSession()->addressEquals('admin/structure/types/manage/article/translate');
    $this->assertSession()->pageTextContains(t('Test translation created.'));
    $this->assertSession()->pageTextNotContains(t('The translation of @title to @language is finished and can now be reviewed.', array(
      '@title' => 'Article',
      '@language' => t('Spanish')
    )));

    // Translated languages should now be listed as Needs review.
    $rows = $this->xpath('//tbody/tr');
    $counter = 0;
    foreach ($rows as $element) {
      $language = $element->find('css', 'td:nth-child(2)')->getText();
      if ('Spanish' == $language || 'German' == $language) {
        $this->assertEquals('Needs review', $element->find('css', 'td:nth-child(3) a img')->getAttribute('title'));
        $counter++;
      }
    }
    $this->assertEquals(2, $counter);
  }

  /**
   * Test the node type for a single checkout.
   */
  function testViewTranslateTabSingleCheckout() {
    $this->loginAsTranslator(array('translate configuration'));

    // Go to the translate tab.
    $this->drupalGet('admin/structure/views/view/content/translate');

    // Assert some basic strings on that page.
    $this->assertSession()->pageTextContains(t('Translations of Content view'));
    $this->assertSession()->pageTextContains(t('There are 0 items in the translation cart.'));

    // Request a translation for german.
    $edit = array(
      'languages[de]' => TRUE,
    );
    $this->submitForm($edit, t('Request translation'));

    // Verify that we are on the translate tab.
    $this->assertSession()->pageTextContains(t('One job needs to be checked out.'));
    $this->assertSession()->pageTextContains('Content view (English to German, Unprocessed)');

    // Submit.
    $this->submitForm([], t('Submit to provider'));

    // Make sure that we're back on the originally defined destination URL.
    $this->assertSession()->addressEquals('admin/structure/views/view/content/translate');

    // We are redirected back to the correct page.
    $this->drupalGet('admin/structure/views/view/content/translate');

    // Translated languages should now be listed as Needs review.
    $rows = $this->xpath('//tbody/tr');
    foreach ($rows as $element) {
      if ($element->find('css', 'td:nth-child(2)')->getText() == 'German') {
        $this->assertEquals('Needs review', $element->find('css', 'td:nth-child(3) a img')->getAttribute('title'));
      }
    }

    // Verify that the pending translation is shown.
    $this->clickLinkWithImageTitle('Needs review');
    $this->submitForm([], t('Save'));

    // Request a spanish translation.
    $edit = array(
      'languages[es]' => TRUE,
    );
    $this->submitForm($edit, t('Request translation'));

    // Verify that we are on the checkout page.
    $this->assertSession()->pageTextContains(t('One job needs to be checked out.'));
    $this->assertSession()->pageTextContains('Content view (English to Spanish, Unprocessed)');
    $this->submitForm([], t('Submit to provider'));

    // Make sure that we're back on the originally defined destination URL.
    $this->assertSession()->addressEquals('admin/structure/views/view/content/translate');

    // Translated languages should now be listed as Needs review.
    $rows = $this->xpath('//tbody/tr');
    $counter = 0;
    foreach ($rows as $element) {
      $language = $element->find('css', 'td:nth-child(2)')->getText();
      if ('Spanish' == $language || 'German' == $language) {
        $this->assertEquals('Needs review', $element->find('css', 'td:nth-child(3) a img')->getAttribute('title'));
        $counter++;
      }
    }
    $this->assertEquals(2, $counter);

    // Test that a job can not be accepted if the entity does not exist.
    $this->clickLinkWithImageTitle('Needs review');

    // Delete the view  and assert that the job can not be accepted.
    $view_content = View::load('content');
    $view_content->delete();

    $this->submitForm([], t('Save as completed'));
    $this->assertSession()->pageTextContains(t('@id of type @type does not exist, the job can not be completed.', array('@id' => $view_content->id(), '@type' => $view_content->getEntityTypeId())));
  }

  /**
   * Test the field config entity type for a single checkout.
   */
  function testFieldConfigTranslateTabSingleCheckout() {
    $this->loginAsAdmin(array('translate configuration'));

    // Add a continuous job.
    $job = $this->createJob('en', 'de', 1, ['job_type' => Job::TYPE_CONTINUOUS]);
    $job->save();

    // Go to sources, field configuration list.
    $this->drupalGet('admin/tmgmt/sources/config/field_config');
    $this->assertSession()->pageTextContains(t('Configuration ID'));
    $this->assertSession()->pageTextContains('field.field.node.article.body');

    $edit = [
      'items[field.field.node.article.body]' => TRUE,
    ];
    $this->submitForm($edit, t('Add to cart'));
    $this->clickLink(t('cart'));

    $this->assertSession()->pageTextContains('Body');

    $edit = [
      'target_language[]' => 'de',
    ];
    $this->submitForm($edit, t('Request translation'));

    // Assert that we cannot add config entities into continuous jobs.
    $this->assertSession()->pageTextNotContains(t('Check for continuous jobs'));
    $this->assertSession()->fieldNotExists('add_all_to_continuous_jobs');

    // Go to the translate tab.
    $this->drupalGet('admin/structure/types/manage/article/fields/node.article.body/translate');

    // Request a german translation.
    $this->submitForm(['languages[de]' => TRUE], t('Request translation'));

    // Verify that we are on the checkout page.
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains(t('One job needs to be checked out.'));
    $this->submitForm([], t('Submit to provider'));

    // Verify that the pending translation is shown.
    $this->clickLinkWithImageTitle('Needs review');
    $this->submitForm([], t('Save as completed'));

  }
  /**
   * Test the entity source specific cart functionality.
   */
  function testCart() {
    $this->loginAsTranslator(array('translate configuration'));

    // Test the source overview.
    $this->drupalGet('admin/structure/views/view/content/translate');
    $this->submitForm([], t('Add to cart'));
    $this->drupalGet('admin/structure/types/manage/article/translate');
    $this->submitForm([], t('Add to cart'));

    // Test if the content and article are in the cart.
    $this->drupalGet('admin/tmgmt/cart');
    $this->assertSession()->linkExists('Content view');
    $this->assertSession()->linkExists('Article content type');

    // Test the label on the source overivew.
    $this->drupalGet('admin/structure/views/view/content/translate');
    $this->assertSession()->responseContains(t('There are @count items in the <a href=":url">translation cart</a> including the current item.',
        array('@count' => 2, ':url' => Url::fromRoute('tmgmt.cart')->toString())));
  }

  /**
   * Test the node type for a single checkout.
   */
  function testSimpleConfiguration() {
    $this->loginAsTranslator(array('translate configuration'));

    // Go to the translate tab.
    $this->drupalGet('admin/config/system/site-information/translate');

    // Assert some basic strings on that page.
    $this->assertSession()->pageTextContains(t('Translations of System information'));

    // Request a translation for german.
    $edit = array(
      'languages[de]' => TRUE,
    );
    $this->submitForm($edit, t('Request translation'));

    // Verify that we are on the translate tab.
    $this->assertSession()->pageTextContains(t('One job needs to be checked out.'));
    $this->assertSession()->pageTextContains('System information (English to German, Unprocessed)');

    // Submit.
    $this->submitForm([], t('Submit to provider'));

    // Make sure that we're back on the originally defined destination URL.
    $this->assertSession()->addressEquals('admin/config/system/site-information/translate');

    // We are redirected back to the correct page.
    $this->drupalGet('admin/config/system/site-information/translate');

    // Translated languages should now be listed as Needs review.
    $rows = $this->xpath('//tbody/tr');
    $found = FALSE;
    foreach ($rows as $value) {
      $image = $value->find('css', 'td:nth-child(3) a img');
      if ($image && $image->getAttribute('title') == 'Needs review') {
        $found = TRUE;
        $this->assertEquals('German', $value->find('css', 'td:nth-child(2)')->getText());
      }
    }
    $this->assertTrue($found);

    // Verify that the pending translation is shown.
    $this->clickLinkWithImageTitle('Needs review');
    $this->submitForm(['name[translation]' => 'de_Druplicon'], t('Save'));
    $this->clickLinkWithImageTitle('Needs review');
    $this->assertSession()->pageTextContains('de_Druplicon');
    $this->submitForm([], t('Save'));

    // Request a spanish translation.
    $edit = array(
      'languages[es]' => TRUE,
    );
    $this->submitForm($edit, t('Request translation'));

    // Verify that we are on the checkout page.
    $this->assertSession()->pageTextContains(t('One job needs to be checked out.'));
    $this->assertSession()->pageTextContains('System information (English to Spanish, Unprocessed)');
    $this->submitForm([], t('Submit to provider'));

    // Make sure that we're back on the originally defined destination URL.
    $this->assertSession()->addressEquals('admin/config/system/site-information/translate');

    // Translated languages should now be listed as Needs review.
    $rows = $this->xpath('//tbody/tr');
    $counter = 0;
    foreach ($rows as $value) {
      $image = $value->find('css', 'td:nth-child(3) a img');
      if ($image && $image->getAttribute('title') == 'Needs review') {
        $this->assertTrue(in_array($value->find('css', 'td:nth-child(2)')->getText(), ['Spanish', 'German']));
        $counter++;
      }
    }
    $this->assertEquals(2, $counter);

    // Test translation and validation tags of account settings.
    $this->drupalGet('admin/config/people/accounts/translate');

    $this->submitForm(['languages[de]' => TRUE], t('Request translation'));

    // Submit.
    $this->submitForm([], t('Submit to provider'));
    $this->clickLinkWithImageTitle('Needs review');
    $this->submitForm(['user__settings|anonymous[translation]' => 'de_Druplicon'], t('Validate HTML tags'));
    $this->assertSession()->pageTextContains('de_Druplicon');
    $this->submitForm([], t('Save'));
    $this->clickLinkWithImageTitle('Needs review');
    $this->assertSession()->pageTextContains('de_Druplicon');
  }

}
