<?php

namespace Drupal\Tests\tmgmt_content\Functional;

use Drupal\comment\Entity\Comment;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\tmgmt\Functional\TMGMTTestBase;
use Drupal\tmgmt\Entity\JobItem;
use Drupal\Tests\tmgmt\Functional\TmgmtEntityTestTrait;
use Drupal\Core\Language\LanguageInterface;

/**
 * Tests the user interface for entity translation lists.
 *
 * @group tmgmt
 */
class ContentTmgmtEntitySourceListTest extends TMGMTTestBase {
  use TmgmtEntityTestTrait;
  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = array('tmgmt_content', 'taxonomy', 'comment');

  protected $nodes = array();

  function setUp(): void {
    parent::setUp();
    $this->loginAsAdmin();

    $this->addLanguage('de');
    $this->addLanguage('fr');
    $this->addLanguage('it');

    $this->createNodeType('article', 'Article', TRUE);
    $this->createNodeType('page', 'Page', TRUE);

    // Enable entity translations for nodes and comments.
    $content_translation_manager = \Drupal::service('content_translation.manager');
    $content_translation_manager->setEnabled('node', 'article', TRUE);
    $content_translation_manager->setEnabled('node', 'page', FALSE);

    // Create nodes that will be used during tests.
    // NOTE that the order matters as results are read by xpath based on
    // position in the list.
    $this->nodes['page']['en'][] = $this->createTranslatableNode('page');
    $this->nodes['article']['de'][0] = $this->createTranslatableNode('article', 'de');
    $this->nodes['article']['fr'][0] = $this->createTranslatableNode('article', 'fr');
    $this->nodes['article']['en'][3] = $this->createTranslatableNode('article', 'en');
    $this->nodes['article']['en'][2] = $this->createTranslatableNode('article', 'en');
    $this->nodes['article']['en'][1] = $this->createTranslatableNode('article', 'en');
    $this->nodes['article']['en'][0] = $this->createTranslatableNode('article', 'en');
    $this->nodes['article'][LanguageInterface::LANGCODE_NOT_SPECIFIED][0] = $this->createTranslatableNode('article', LanguageInterface::LANGCODE_NOT_SPECIFIED);
    $this->nodes['article'][LanguageInterface::LANGCODE_NOT_APPLICABLE][0] = $this->createTranslatableNode('article', LanguageInterface::LANGCODE_NOT_APPLICABLE);
  }

  /**
   * Tests that the term bundle filter works.
   */
  function testTermBundleFilter() {

    $vocabulary1 = Vocabulary::create([
      'vid' => 'vocab1',
      'name' => $this->randomMachineName(),
    ]);
    $vocabulary1->save();

    $term1 = Term::create([
      'name' => $this->randomMachineName(),
      'vid' => $vocabulary1->id(),
    ]);
    $term1->save();

    $vocabulary2 = Vocabulary::create([
      'vid' => 'vocab2',
      'name' => $this->randomMachineName(),
    ]);
    $vocabulary2->save();

    $term2 = Term::create([
      'name' => $this->randomMachineName(),
      'vid' => $vocabulary2->id(),
    ]);
    $term2->save();

    $content_translation_manager = \Drupal::service('content_translation.manager');
    $content_translation_manager->setEnabled('taxonomy_term', $vocabulary1->id(), TRUE);
    $content_translation_manager->setEnabled('taxonomy_term', $vocabulary2->id(), TRUE);

    $this->drupalGet('admin/tmgmt/sources/content/taxonomy_term');
    // Both terms should be displayed with their bundle.
    $this->assertSession()->pageTextContains($term1->label());
    $this->assertSession()->pageTextContains($term2->label());
    $this->assertNotEmpty($this->xpath('//td[text()=@vocabulary]', array('@vocabulary' => $vocabulary1->label())));
    $this->assertNotEmpty($this->xpath('//td[text()=@vocabulary]', array('@vocabulary' => $vocabulary2->label())));

    // Limit to the first vocabulary.
    $edit = array();
    $edit['search[vid]'] = $vocabulary1->id();
    $this->submitForm($edit, t('Search'));
    // Only term 1 should be displayed now.
    $this->assertSession()->pageTextContains($term1->label());
    $this->assertSession()->pageTextNotContains($term2->label());
    $this->assertNotEmpty($this->xpath('//td[text()=@vocabulary]', array('@vocabulary' => $vocabulary1->label())));
    $this->assertEmpty($this->xpath('//td[text()=@vocabulary]', array('@vocabulary' => $vocabulary2->label())));

  }

  function testAvailabilityOfEntityLists() {

    $this->drupalGet('admin/tmgmt/sources/content/comment');
    // Check if we are at comments page.
    $this->assertSession()->pageTextContains(t('Comment overview (Content Entity)'));
    // No comments yet - empty message is expected.
    $this->assertSession()->pageTextContains(t('No source items matching given criteria have been found.'));

    $this->drupalGet('admin/tmgmt/sources/content/node');
    // Check if we are at nodes page.
    $this->assertSession()->pageTextContains(t('Content overview (Content Entity)'));
    // We expect article title as article node type is entity translatable.
    $this->assertSession()->pageTextContains($this->nodes['article']['en'][0]->label());
    // Page node type should not be listed as it is not entity translatable.
    $this->assertSession()->pageTextNotContains($this->nodes['page']['en'][0]->label());
    // If the source language is not defined, don't display it.
    $this->assertSession()->pageTextNotContains($this->nodes['article'][LanguageInterface::LANGCODE_NOT_SPECIFIED][0]->label());
    // If the source language is not applicable, don't display it.
    $this->assertSession()->pageTextNotContains($this->nodes['article'][LanguageInterface::LANGCODE_NOT_APPLICABLE][0]->label());
  }

  function testTranslationStatuses() {

    // Test statuses: Source, Missing.
    $this->drupalGet('admin/tmgmt/sources/content/node');
    $langstatus_en = $this->xpath('//table[@id="edit-items"]/tbody/tr[1]/td[@class="langstatus-en"]/a/img');
    $langstatus_de = $this->xpath('//table[@id="edit-items"]/tbody/tr[1]/td[@class="langstatus-de"]/img');

    $this->assertEquals(t('Original language'), $langstatus_en[0]->getAttribute('title'));
    $this->assertEquals(t('Not translated'), $langstatus_de[0]->getAttribute('title'));

    // Test status: Active job item.
    $job = $this->createJob('en', 'de');
    $job->translator = $this->default_translator->id();
    $job->settings = array();
    $job->save();

    $job->addItem('content', 'node', $this->nodes['article']['en'][0]->id());
    $job->requestTranslation();

    $this->drupalGet('admin/tmgmt/sources/content/node');
    $langstatus_de = $this->xpath('//table[@id="edit-items"]/tbody/tr[1]/td[@class="langstatus-de"]/a/img');

    $items = $job->getItems();
    $states = JobItem::getStates();
    $label = t('Active job item: @state', array('@state' => $states[reset($items)->getState()]));

    $this->assertEquals($label, (string)$langstatus_de[0]->getAttribute('title'));

    // Test status: Current
    foreach ($job->getItems() as $job_item) {
      $job_item->acceptTranslation();
    }

    $this->drupalGet('admin/tmgmt/sources/content/node');
    $langstatus_de = $this->xpath('//table[@id="edit-items"]/tbody/tr[1]/td[@class="langstatus-de"]/a/img');

    $this->assertEquals(t('Translation up to date'), $langstatus_de[0]->getAttribute('title'));

    // Test status: Inactive job.
    $job = $this->createJob('en', 'de');
    $job->translator = $this->default_translator->id();
    $job->settings = array();
    $job->save();

    $job->addItem('content', 'node', $this->nodes['article']['en'][0]->id());

    $this->drupalGet('admin/tmgmt/sources/content/node');
    $langstatus_de = $this->xpath('//table[@id="edit-items"]/tbody/tr[1]/td[@class="langstatus-de"]/a/img');

    $items = $job->getItems();
    $states = JobItem::getStates();
    $label = t('Active job item: @state', array('@state' => $states[reset($items)->getState()]));

    $this->assertEquals($label, (string)$langstatus_de[1]->getAttribute('title'));
  }

  function testTranslationSubmissions() {

    // Simple submission.
    $this->drupalGet('admin/tmgmt/sources/content/node');
    $nid = $this->nodes['article']['en'][0]->id();
    $edit = array();
    $edit["items[$nid]"] = 1;
    $this->submitForm($edit, t('Request translation'));
    $this->assertSession()->pageTextContains(t('One job needs to be checked out.'));

    // Submission of two entities of the same original language.
    $this->drupalGet('admin/tmgmt/sources/content/node');
    $nid1 = $this->nodes['article']['en'][0]->id();
    $nid2 = $this->nodes['article']['en'][1]->id();
    $edit = array();
    $edit["items[$nid1]"] = 1;
    $edit["items[$nid2]"] = 1;
    $this->submitForm($edit, t('Request translation'));
    $this->assertSession()->pageTextContains(t('One job needs to be checked out.'));

    // Submission of several entities of different original languages.
    $this->drupalGet('admin/tmgmt/sources/content/node');
    $nid1 = $this->nodes['article']['en'][0]->id();
    $nid2 = $this->nodes['article']['en'][1]->id();
    $nid3 = $this->nodes['article']['en'][2]->id();
    $nid4 = $this->nodes['article']['en'][3]->id();
    $nid5 = $this->nodes['article']['de'][0]->id();
    $nid6 = $this->nodes['article']['fr'][0]->id();
    $edit = array();
    $edit["items[$nid1]"] = 1;
    $edit["items[$nid2]"] = 1;
    $edit["items[$nid3]"] = 1;
    $edit["items[$nid4]"] = 1;
    $edit["items[$nid5]"] = 1;
    $edit["items[$nid6]"] = 1;
    $edit['target_language'] = 'it';
    $this->submitForm($edit, t('Request translation'));
    $this->assertSession()->pageTextContains(t('@count jobs need to be checked out.', array('@count' => '3')));

    // Submission of several entities of different original languages to multiple
    // target languages.
    $this->drupalGet('admin/tmgmt/sources/content/node');
    $edit = array();
    $edit["items[$nid1]"] = 1;
    $edit["items[$nid2]"] = 1;
    $edit["items[$nid3]"] = 1;
    $edit["items[$nid4]"] = 1;
    $edit["items[$nid5]"] = 1;
    $edit["items[$nid6]"] = 1;
    $edit['target_language'] = '_multiple';
    $edit['target_languages[de]'] = TRUE;
    $edit['target_languages[fr]'] = TRUE;

    // This needs to create 4 jobs:
    // EN => DE
    // EN => FR
    // DE => FR
    // FR => DE

    $this->submitForm($edit, t('Request translation'));
    $this->assertSession()->pageTextContains(t('@count jobs need to be checked out.', array('@count' => 4)));

    // Submission of several entities of different original languages to all
    // target languages.
    $this->drupalGet('admin/tmgmt/sources/content/node');
    $edit = array();
    $edit["items[$nid1]"] = 1;
    $edit["items[$nid2]"] = 1;
    $edit["items[$nid3]"] = 1;
    $edit["items[$nid4]"] = 1;
    $edit["items[$nid5]"] = 1;
    $edit["items[$nid6]"] = 1;
    $edit['target_language'] = '_all';

    // This needs to create 9 jobs:
    // EN => DE
    // EN => FR
    // EN => IT
    // DE => FR
    // DE => EN
    // DE => IT
    // FR => DE
    // FR => IT
    // FR => EN

    $this->submitForm($edit, t('Request translation'));
    $this->assertSession()->pageTextContains(t('@count jobs need to be checked out.', array('@count' => 9)));

    // Submission of several entities of different original languages to all
    // target languages and force a source language.
    $this->drupalGet('admin/tmgmt/sources/content/node');
    $edit = array();
    $edit["items[$nid1]"] = 1;
    $edit["items[$nid2]"] = 1;
    $edit["items[$nid3]"] = 1;
    $edit["items[$nid4]"] = 1;
    $edit["items[$nid5]"] = 1;
    $edit["items[$nid6]"] = 1;
    $edit['source_language'] = 'fr';
    $edit['target_language'] = '_all';

    // This needs to create 3 jobs.
    // FR => DE
    // FR => IT
    // FR => EN

    $this->submitForm($edit, t('Request translation'));
    $this->assertSession()->pageTextContains(t('@count jobs need to be checked out.', array('@count' => 3)));
  }

  function testNodeEntityListings() {

    // Turn off the entity translation.
    $content_translation_manager = \Drupal::service('content_translation.manager');
    $content_translation_manager->setEnabled('node', 'article', FALSE);
    $content_translation_manager->setEnabled('node', 'page', FALSE);

    // Check if we have appropriate message in case there are no entity
    // translatable content types.
    $this->drupalGet('admin/tmgmt/sources/content/node');
    $this->assertSession()->pageTextContains(t('Entity translation is not enabled for any of existing content types. To use this functionality go to Content types administration and enable entity translation for desired content types.'));

    // Turn on the entity translation for both - article and page - to test
    // search form.
    $content_translation_manager->setEnabled('node', 'article', TRUE);
    $content_translation_manager->setEnabled('node', 'page', TRUE);

    // Create page node after entity translation is enabled.
    $page_node_translatable = $this->createTranslatableNode('page');

    $this->drupalGet('admin/tmgmt/sources/content/node');
    // We have both listed - one of articles and page.
    $this->assertSession()->pageTextContains($this->nodes['article']['en'][0]->label());
    $this->assertSession()->pageTextContains($page_node_translatable->label());

    // Try the search by content type.
    $edit = array();
    $edit['search[type]'] = 'article';
    $this->submitForm($edit, t('Search'));
    // There should be article present.
    $this->assertSession()->pageTextContains($this->nodes['article']['en'][0]->label());
    // The page node should not be listed.
    $this->assertSession()->pageTextNotContains($page_node_translatable->label());

    // Try cancel button - despite we do post content type search value
    // we should get nodes of botch content types.
    $this->drupalGet('admin/tmgmt/sources/content/node');
    $this->submitForm($edit, t('Cancel'));
    $this->assertSession()->pageTextContains($this->nodes['article']['en'][0]->label());
    $this->assertSession()->pageTextContains($page_node_translatable->label());

    // Ensure that the pager limit works as expected if there are translations
    // and revisions.
    $this->config('tmgmt.settings')
      ->set('source_list_limit', 8)
      ->save();
    $translation = $this->nodes['article']['de'][0]->addTranslation('en', $this->nodes['article']['de'][0]->toArray());
    $translation->setNewRevision(TRUE);
    $translation->save();

    $this->drupalGet('admin/tmgmt/sources/content/node');
    $this->assertSession()->linkNotExists('Next');

    $this->config('tmgmt.settings')
      ->set('source_list_limit', 4)
      ->save();
    $this->drupalGet('admin/tmgmt/sources/content/node');
    $this->assertSession()->linkExists('Next');
    $this->assertSession()->linkExists('Go to page 2');
    $this->assertSession()->linkNotExists('Go to page 3');
  }

  function testEntitySourceListSearch() {

    // We need a node with title composed of several words to test
    // "any words" search.
    $title_part_1 = $this->randomMachineName('4');
    $title_part_2 = $this->randomMachineName('4');
    $title_part_3 = $this->randomMachineName('4');

    $this->nodes['article']['en'][0]->title = "$title_part_1 $title_part_2 $title_part_3";
    $this->nodes['article']['en'][0]->save();

    // Submit partial node title and see if we have a result.
    $this->drupalGet('admin/tmgmt/sources/content/node');
    $edit = array();
    $edit['search[title]'] = "$title_part_1 $title_part_3";
    $this->submitForm($edit, t('Search'));
    $this->assertSession()->pageTextContains("$title_part_1 $title_part_2 $title_part_3");

    // Check if there is only one result in the list.
    $search_result_rows = $this->xpath('//table[@id="edit-items"]/tbody/tr');
    $this->assertCount(1, $search_result_rows, 'The search result must return only one row.');

    // To test if other entity types work go for simple comment search.
    $this->addDefaultCommentField('node', 'article');
    $content_translation_manager = \Drupal::service('content_translation.manager');
    $content_translation_manager->setEnabled('comment', 'comment', TRUE);
    $values = array(
      'entity_type' => 'node',
      'entity_id' => $this->nodes['article']['en'][0]->id(),
      'field_name' => 'comment',
      'comment_type' => 'comment',
      'comment_body' => $this->randomMachineName(),
      'subject' => $this->randomMachineName(),
    );
    $comment = Comment::create($values);
    $comment->save();
    // Do search for the comment.
    $this->drupalGet('admin/tmgmt/sources/content/comment');
    $edit = array();
    $edit['search[subject]'] = $comment->getSubject();
    $this->submitForm($edit, t('Search'));
    $this->assertSession()->pageTextContains($comment->getSubject());

    // Tests that search bundle filter works.
    $this->drupalGet('/admin/tmgmt/sources/content/node');
    $this->submitForm(['search[title]' => $this->nodes['article']['en'][0]->label()], 'Search');
    $this->assertSession()->pageTextContains(t('Content overview'));
    $this->assertSession()->pageTextContains($this->nodes['article']['en'][0]->label());
    $this->submitForm(['search[title]' => 'wrong_value'], 'Search');
    $this->assertSession()->pageTextContains(t('Content overview'));
    $this->assertSession()->pageTextNotContains($this->nodes['article']['en'][0]->label());
    $options = ['query' => ['any_key' => 'any_value']];
    $this->drupalGet('/admin/tmgmt/sources/content/node', $options);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($this->nodes['article']['en'][0]->label());

    // Test combined title and language filter.
    $this->drupalGet('/admin/tmgmt/sources/content/node');
    $edit = [
      'search[target_language]' => 'de',
      'search[title]' => $this->nodes['article']['en'][0]->label(),
    ];
    $this->submitForm($edit, 'Search');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists($this->nodes['article']['en'][0]->label());
  }
}
