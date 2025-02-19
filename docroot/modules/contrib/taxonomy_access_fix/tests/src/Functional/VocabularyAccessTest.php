<?php

namespace Drupal\Tests\taxonomy_access_fix\Functional;

use Drupal\Core\Url;
use Drupal\Tests\taxonomy\Functional\TaxonomyTestBase;
use Drupal\Tests\taxonomy_access_fix\Traits\TaxonomyAccessFixTestTrait;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\VocabularyInterface;

/**
 * Tests administrative Taxonomy UI access.
 *
 * @group taxonomy
 */
class VocabularyAccessTest extends TaxonomyTestBase {

  use TaxonomyAccessFixTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['taxonomy_access_fix', 'block'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Users used.
   *
   * @var \Drupal\user\UserInterface[]
   */
  protected $users;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->vocabularies[] = $this->createVocabulary();
    $this->vocabularies[] = $this->createVocabulary();

    $this->users['administer'] = $this->drupalCreateUser([
      'administer taxonomy',
    ]);
    $this->users['administer_permissions'] = $this->drupalCreateUser([
      'administer permissions',
    ]);
    $this->users['overview'] = $this->drupalCreateUser([
      'access taxonomy overview',
    ]);
    $this->users['create_first_vocabulary'] = $this->drupalCreateUser([
      'create terms in ' . $this->vocabularies[0]->id(),
    ]);
    $this->users['overview_and_create_first_vocabulary'] = $this->drupalCreateUser([
      'access taxonomy overview',
      'create terms in ' . $this->vocabularies[0]->id(),
    ]);
    $this->users['create_any_vocabulary'] = $this->drupalCreateUser([
      'create any term',
    ]);
    $this->users['overview_and_create_any_vocabulary'] = $this->drupalCreateUser([
      'access taxonomy overview',
      'create any term',
    ]);
    $this->users['update_first_vocabulary'] = $this->drupalCreateUser([
      'edit terms in ' . $this->vocabularies[0]->id(),
    ]);
    $this->users['overview_and_update_first_vocabulary'] = $this->drupalCreateUser([
      'access taxonomy overview',
      'edit terms in ' . $this->vocabularies[0]->id(),
    ]);
    $this->users['update_any_vocabulary'] = $this->drupalCreateUser([
      'update any term',
    ]);
    $this->users['overview_and_update_any_vocabulary'] = $this->drupalCreateUser([
      'access taxonomy overview',
      'update any term',
    ]);
    $this->users['delete_first_vocabulary'] = $this->drupalCreateUser([
      'delete terms in ' . $this->vocabularies[0]->id(),
    ]);
    $this->users['overview_and_delete_first_vocabulary'] = $this->drupalCreateUser([
      'access taxonomy overview',
      'delete terms in ' . $this->vocabularies[0]->id(),
    ]);
    $this->users['delete_any_vocabulary'] = $this->drupalCreateUser([
      'delete any term',
    ]);
    $this->users['overview_and_delete_any_vocabulary'] = $this->drupalCreateUser([
      'access taxonomy overview',
      'delete any term',
    ]);
    $this->users['view_first_vocabulary'] = $this->drupalCreateUser([
      'view terms in ' . $this->vocabularies[0]->id(),
    ]);
    $this->users['overview_and_view_first_vocabulary'] = $this->drupalCreateUser([
      'access taxonomy overview',
      'view terms in ' . $this->vocabularies[0]->id(),
    ]);
    $this->users['view_any_vocabulary'] = $this->drupalCreateUser([
      'view any term',
    ]);
    $this->users['overview_and_view_any_vocabulary'] = $this->drupalCreateUser([
      'access taxonomy overview',
      'view any term',
    ]);
    $this->users['view_unpublished_first_vocabulary'] = $this->drupalCreateUser([
      'view unpublished terms in ' . $this->vocabularies[0]->id(),
    ]);
    $this->users['overview_and_view_unpublished_first_vocabulary'] = $this->drupalCreateUser([
      'access taxonomy overview',
      'view unpublished terms in ' . $this->vocabularies[0]->id(),
    ]);
    $this->users['view_any_unpublished_vocabulary'] = $this->drupalCreateUser([
      'view any unpublished term',
    ]);
    $this->users['overview_and_view_any_unpublished_vocabulary'] = $this->drupalCreateUser([
      'access taxonomy overview',
      'view any unpublished term',
    ]);
    $this->users['view_name_first_vocabulary'] = $this->drupalCreateUser([
      'view term names in ' . $this->vocabularies[0]->id(),
    ]);
    $this->users['overview_and_view_name_first_vocabulary'] = $this->drupalCreateUser([
      'access taxonomy overview',
      'view term names in ' . $this->vocabularies[0]->id(),
    ]);
    $this->users['view_name_any_vocabulary'] = $this->drupalCreateUser([
      'view any term name',
    ]);
    $this->users['overview_and_view_name_any_vocabulary'] = $this->drupalCreateUser([
      'access taxonomy overview',
      'view any term name',
    ]);
    $this->users['view_name_unpublished_first_vocabulary'] = $this->drupalCreateUser([
      'view unpublished term names in ' . $this->vocabularies[0]->id(),
    ]);
    $this->users['overview_and_view_name_unpublished_first_vocabulary'] = $this->drupalCreateUser([
      'access taxonomy overview',
      'view unpublished term names in ' . $this->vocabularies[0]->id(),
    ]);
    $this->users['view_name_any_unpublished_vocabulary'] = $this->drupalCreateUser([
      'view any unpublished term name',
    ]);
    $this->users['overview_and_view_name_any_unpublished_vocabulary'] = $this->drupalCreateUser([
      'access taxonomy overview',
      'view any unpublished term name',
    ]);
    $this->users['view_vocabulary_name_first_vocabulary'] = $this->drupalCreateUser([
      'view vocabulary name of ' . $this->vocabularies[0]->id(),
    ]);
    $this->users['overview_and_view_vocabulary_name_first_vocabulary'] = $this->drupalCreateUser([
      'access taxonomy overview',
      'view vocabulary name of ' . $this->vocabularies[0]->id(),
    ]);
    $this->users['view_vocabulary_name_any_vocabulary'] = $this->drupalCreateUser([
      'view any vocabulary name',
    ]);
    $this->users['overview_and_view_vocabulary_name_any_vocabulary'] = $this->drupalCreateUser([
      'access taxonomy overview',
      'view any vocabulary name',
    ]);
    $this->users['reorder_first_vocabulary'] = $this->drupalCreateUser([
      'reorder terms in ' . $this->vocabularies[0]->id(),
    ]);
    $this->users['overview_and_reorder_first_vocabulary'] = $this->drupalCreateUser([
      'access taxonomy overview',
      'reorder terms in ' . $this->vocabularies[0]->id(),
    ]);
    $this->users['reorder_any_vocabulary'] = $this->drupalCreateUser([
      'reorder terms in any vocabulary',
    ]);
    $this->users['overview_and_reorder_any_vocabulary'] = $this->drupalCreateUser([
      'access taxonomy overview',
      'reorder terms in any vocabulary',
    ]);
    $this->users['reset_first_vocabulary'] = $this->drupalCreateUser([
      'reset ' . $this->vocabularies[0]->id(),
    ]);
    $this->users['overview_and_reset_first_vocabulary'] = $this->drupalCreateUser([
      'access taxonomy overview',
      'reset ' . $this->vocabularies[0]->id(),
    ]);
    $this->users['reset_any_vocabulary'] = $this->drupalCreateUser([
      'reset any vocabulary',
    ]);
    $this->users['overview_and_reset_any_vocabulary'] = $this->drupalCreateUser([
      'access taxonomy overview',
      'reset any vocabulary',
    ]);
    $this->users['select_first_vocabulary'] = $this->drupalCreateUser([
      'select terms in ' . $this->vocabularies[0]->id(),
    ]);
    $this->users['overview_and_select_first_vocabulary'] = $this->drupalCreateUser([
      'access taxonomy overview',
      'select terms in ' . $this->vocabularies[0]->id(),
    ]);
    $this->users['select_any_vocabulary'] = $this->drupalCreateUser([
      'select any term',
    ]);
    $this->users['overview_and_select_any_vocabulary'] = $this->drupalCreateUser([
      'access taxonomy overview',
      'select any term',
    ]);
    $this->users['select_unpublished_first_vocabulary'] = $this->drupalCreateUser([
      'select unpublished terms in ' . $this->vocabularies[0]->id(),
    ]);
    $this->users['overview_and_select_unpublished_first_vocabulary'] = $this->drupalCreateUser([
      'access taxonomy overview',
      'select unpublished terms in ' . $this->vocabularies[0]->id(),
    ]);
    $this->users['select_any_unpublished_vocabulary'] = $this->drupalCreateUser([
      'select any unpublished term',
    ]);
    $this->users['overview_and_select_any_unpublished_vocabulary'] = $this->drupalCreateUser([
      'access taxonomy overview',
      'select any unpublished term',
    ]);

    $this->drupalPlaceBlock('local_actions_block');
    $this->drupalPlaceBlock('page_title_block');
  }

  /**
   * Tests access to administrative Taxonomy Vocabulary collection.
   */
  public function testTaxonomyVocabularyCollection() {
    $assert_session = $this->assertSession();

    // Test the 'administer taxonomy' permission.
    $this->drupalLogin($this->users['administer']);

    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(200);

    $assert_session->pageTextContains('Add vocabulary');
    $this->assertLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.add_form')->toString());
    $this->assertSortableTable(FALSE);

    foreach ($this->vocabularies as $delta => $vocabulary) {
      $assert_session->pageTextContains($vocabulary->label());
      $assert_session->pageTextContains($vocabulary->getDescription());
      $this->assertLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
      $this->assertLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.edit_form', ['taxonomy_vocabulary' => $vocabulary->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString(),
      ])->toString());
      $add_terms_url = Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString();
      $this->assertLinkByEndOfHref($add_terms_url);
    }

    // Test the 'access taxonomy overview' permission.
    $this->drupalLogin($this->users['overview']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(200);

    $assert_session->pageTextNotContains('Add vocabulary');
    $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.add_form')->toString());
    $this->assertNoSortableTable(FALSE);
    $assert_session->pageTextContains('No vocabularies available.');

    foreach ($this->vocabularies as $delta => $vocabulary) {
      $assert_session->pageTextNotContains($vocabulary->label());
      $assert_session->pageTextNotContains($vocabulary->getDescription());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.edit_form', ['taxonomy_vocabulary' => $vocabulary->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString(),
      ])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
    }

    // Test the per vocabulary 'view terms in' permission.
    $this->drupalLogin($this->users['view_first_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(403);

    $this->drupalLogin($this->users['overview_and_view_first_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(200);

    $assert_session->pageTextNotContains('Add vocabulary');
    $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.add_form')->toString());
    $this->assertNoSortableTable(FALSE);
    $assert_session->pageTextContains('No vocabularies available.');

    foreach ($this->vocabularies as $delta => $vocabulary) {
      $assert_session->pageTextNotContains($vocabulary->label());
      $assert_session->pageTextNotContains($vocabulary->getDescription());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.edit_form', ['taxonomy_vocabulary' => $vocabulary->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString(),
      ])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
    }

    // Test the 'view any term' permission.
    $this->drupalLogin($this->users['view_any_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(403);

    $this->drupalLogin($this->users['overview_and_view_any_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(200);

    $assert_session->pageTextNotContains('Add vocabulary');
    $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.add_form')->toString());
    $this->assertNoSortableTable(FALSE);
    $assert_session->pageTextContains('No vocabularies available.');

    foreach ($this->vocabularies as $delta => $vocabulary) {
      $assert_session->pageTextNotContains($vocabulary->label());
      $assert_session->pageTextNotContains($vocabulary->getDescription());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.edit_form', ['taxonomy_vocabulary' => $vocabulary->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString(),
      ])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
    }

    // Test the per vocabulary 'view unpublished terms in' permission.
    $this->drupalLogin($this->users['view_unpublished_first_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(403);

    $this->drupalLogin($this->users['overview_and_view_unpublished_first_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(200);

    $assert_session->pageTextNotContains('Add vocabulary');
    $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.add_form')->toString());
    $this->assertNoSortableTable(FALSE);
    $assert_session->pageTextContains('No vocabularies available.');

    foreach ($this->vocabularies as $delta => $vocabulary) {
      $assert_session->pageTextNotContains($vocabulary->label());
      $assert_session->pageTextNotContains($vocabulary->getDescription());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.edit_form', ['taxonomy_vocabulary' => $vocabulary->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString(),
      ])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
    }

    // Test the 'view any unpublished term' permission.
    $this->drupalLogin($this->users['view_any_unpublished_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(403);

    $this->drupalLogin($this->users['overview_and_view_any_unpublished_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(200);

    $assert_session->pageTextNotContains('Add vocabulary');
    $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.add_form')->toString());
    $this->assertNoSortableTable(FALSE);
    $assert_session->pageTextContains('No vocabularies available.');

    foreach ($this->vocabularies as $delta => $vocabulary) {
      $assert_session->pageTextNotContains($vocabulary->label());
      $assert_session->pageTextNotContains($vocabulary->getDescription());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.edit_form', ['taxonomy_vocabulary' => $vocabulary->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString(),
      ])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
    }

    // Test the per vocabulary 'view term names in' permission.
    $this->drupalLogin($this->users['view_name_first_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(403);

    $this->drupalLogin($this->users['overview_and_view_name_first_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(200);

    $assert_session->pageTextNotContains('Add vocabulary');
    $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.add_form')->toString());
    $this->assertNoSortableTable(FALSE);
    $assert_session->pageTextContains('No vocabularies available.');

    foreach ($this->vocabularies as $delta => $vocabulary) {
      $assert_session->pageTextNotContains($vocabulary->label());
      $assert_session->pageTextNotContains($vocabulary->getDescription());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.edit_form', ['taxonomy_vocabulary' => $vocabulary->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString(),
      ])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
    }

    // Test the 'view any term name' permission.
    $this->drupalLogin($this->users['view_name_any_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(403);

    $this->drupalLogin($this->users['overview_and_view_name_any_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(200);

    $assert_session->pageTextNotContains('Add vocabulary');
    $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.add_form')->toString());
    $this->assertNoSortableTable(FALSE);
    $assert_session->pageTextContains('No vocabularies available.');

    foreach ($this->vocabularies as $delta => $vocabulary) {
      $assert_session->pageTextNotContains($vocabulary->label());
      $assert_session->pageTextNotContains($vocabulary->getDescription());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.edit_form', ['taxonomy_vocabulary' => $vocabulary->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString(),
      ])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
    }

    // Test the per vocabulary 'view unpublished term names in' permission.
    $this->drupalLogin($this->users['view_name_unpublished_first_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(403);

    $this->drupalLogin($this->users['overview_and_view_name_unpublished_first_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(200);

    $assert_session->pageTextNotContains('Add vocabulary');
    $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.add_form')->toString());
    $this->assertNoSortableTable(FALSE);
    $assert_session->pageTextContains('No vocabularies available.');

    foreach ($this->vocabularies as $delta => $vocabulary) {
      $assert_session->pageTextNotContains($vocabulary->label());
      $assert_session->pageTextNotContains($vocabulary->getDescription());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.edit_form', ['taxonomy_vocabulary' => $vocabulary->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString(),
      ])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
    }

    // Test the 'view any unpublished term name' permission.
    $this->drupalLogin($this->users['view_name_any_unpublished_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(403);

    $this->drupalLogin($this->users['overview_and_view_name_any_unpublished_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(200);

    $assert_session->pageTextNotContains('Add vocabulary');
    $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.add_form')->toString());
    $this->assertNoSortableTable(FALSE);
    $assert_session->pageTextContains('No vocabularies available.');

    foreach ($this->vocabularies as $delta => $vocabulary) {
      $assert_session->pageTextNotContains($vocabulary->label());
      $assert_session->pageTextNotContains($vocabulary->getDescription());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.edit_form', ['taxonomy_vocabulary' => $vocabulary->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString(),
      ])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
    }

    // Test the per vocabulary 'view vocabulary name of' permission.
    $this->drupalLogin($this->users['view_vocabulary_name_first_vocabulary']);

    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(403);

    $this->drupalLogin($this->users['overview_and_view_vocabulary_name_first_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(200);

    $assert_session->pageTextNotContains('Add vocabulary');
    $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.add_form')->toString());
    $this->assertNoSortableTable(FALSE);
    $assert_session->pageTextContains('No vocabularies available.');

    foreach ($this->vocabularies as $delta => $vocabulary) {
      $assert_session->pageTextNotContains($vocabulary->label());
      $assert_session->pageTextNotContains($vocabulary->getDescription());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.edit_form', ['taxonomy_vocabulary' => $vocabulary->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString(),
      ])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
    }

    // Test the 'view any vocabulary name' permission.
    $this->drupalLogin($this->users['view_vocabulary_name_any_vocabulary']);

    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(403);

    $this->drupalLogin($this->users['overview_and_view_vocabulary_name_any_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(200);

    $assert_session->pageTextNotContains('Add vocabulary');
    $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.add_form')->toString());
    $this->assertNoSortableTable(FALSE);
    $assert_session->pageTextContains('No vocabularies available.');

    foreach ($this->vocabularies as $delta => $vocabulary) {
      $assert_session->pageTextNotContains($vocabulary->label());
      $assert_session->pageTextNotContains($vocabulary->getDescription());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.edit_form', ['taxonomy_vocabulary' => $vocabulary->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString(),
      ])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
    }

    // Test the per vocabulary 'create terms in' permission.
    $this->drupalLogin($this->users['create_first_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(403);

    $this->drupalLogin($this->users['overview_and_create_first_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(200);

    $assert_session->pageTextNotContains('Add vocabulary');
    $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.add_form')->toString());
    $this->assertNoSortableTable(FALSE);

    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.edit_form', ['taxonomy_vocabulary' => $vocabulary->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString(),
      ])->toString());
      $overview_url = Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString();
      $add_terms_url = Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString();
      if ($delta === 0) {
        $assert_session->pageTextContains($vocabulary->label());
        $assert_session->pageTextContains($vocabulary->getDescription());
        $this->assertLinkByEndOfHref($overview_url);
        $this->assertLinkByEndOfHref($add_terms_url);
      }
      else {
        $assert_session->pageTextNotContains($vocabulary->label());
        $assert_session->pageTextNotContains($vocabulary->getDescription());
        $this->assertNoLinkByEndOfHref($overview_url);
        $this->assertNoLinkByEndOfHref($add_terms_url);
      }
    }

    // Test the 'create any term' permission.
    $this->drupalLogin($this->users['create_any_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(403);

    $this->drupalLogin($this->users['overview_and_create_any_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(200);

    $assert_session->pageTextNotContains('Add vocabulary');
    $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.add_form')->toString());
    $this->assertNoSortableTable(FALSE);

    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.edit_form', ['taxonomy_vocabulary' => $vocabulary->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString(),
      ])->toString());
      $overview_url = Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString();
      $add_terms_url = Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString();
      $assert_session->pageTextContains($vocabulary->label());
      $assert_session->pageTextContains($vocabulary->getDescription());
      $this->assertLinkByEndOfHref($overview_url);
      $this->assertLinkByEndOfHref($add_terms_url);
    }

    // Test the per vocabulary 'edit terms in' permission.
    $this->drupalLogin($this->users['update_first_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(403);

    $this->drupalLogin($this->users['overview_and_update_first_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(200);

    $assert_session->pageTextNotContains('Add vocabulary');
    $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.add_form')->toString());
    $this->assertNoSortableTable(FALSE);

    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.edit_form', ['taxonomy_vocabulary' => $vocabulary->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString(),
      ])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
      $overview_url = Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString();
      if ($delta === 0) {
        $assert_session->pageTextContains($vocabulary->label());
        $assert_session->pageTextContains($vocabulary->getDescription());
        $this->assertLinkByEndOfHref($overview_url);
      }
      else {
        $assert_session->pageTextNotContains($vocabulary->label());
        $assert_session->pageTextNotContains($vocabulary->getDescription());
        $this->assertNoLinkByEndOfHref($overview_url);
      }
    }

    // Test the 'update any term' permission.
    $this->drupalLogin($this->users['update_any_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(403);

    $this->drupalLogin($this->users['overview_and_update_any_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(200);

    $assert_session->pageTextNotContains('Add vocabulary');
    $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.add_form')->toString());
    $this->assertNoSortableTable(FALSE);

    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.edit_form', ['taxonomy_vocabulary' => $vocabulary->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString(),
      ])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
      $overview_url = Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString();
      $assert_session->pageTextContains($vocabulary->label());
      $assert_session->pageTextContains($vocabulary->getDescription());
      $this->assertLinkByEndOfHref($overview_url);
    }

    // Test the per vocabulary 'delete terms in' permission.
    $this->drupalLogin($this->users['delete_first_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(403);

    $this->drupalLogin($this->users['overview_and_delete_first_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(200);

    $assert_session->pageTextNotContains('Add vocabulary');
    $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.add_form')->toString());
    $this->assertNoSortableTable(FALSE);

    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.edit_form', ['taxonomy_vocabulary' => $vocabulary->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString(),
      ])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
      $overview_url = Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString();
      if ($delta === 0) {
        $assert_session->pageTextContains($vocabulary->label());
        $assert_session->pageTextContains($vocabulary->getDescription());
        $this->assertLinkByEndOfHref($overview_url);
      }
      else {
        $assert_session->pageTextNotContains($vocabulary->label());
        $assert_session->pageTextNotContains($vocabulary->getDescription());
        $this->assertNoLinkByEndOfHref($overview_url);
      }
    }

    // Test the 'delete any term' permission.
    $this->drupalLogin($this->users['delete_any_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(403);

    $this->drupalLogin($this->users['overview_and_delete_any_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(200);

    $assert_session->pageTextNotContains('Add vocabulary');
    $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.add_form')->toString());
    $this->assertNoSortableTable(FALSE);

    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.edit_form', ['taxonomy_vocabulary' => $vocabulary->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString(),
      ])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
      $overview_url = Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString();
      $assert_session->pageTextContains($vocabulary->label());
      $assert_session->pageTextContains($vocabulary->getDescription());
      $this->assertLinkByEndOfHref($overview_url);
    }

    // Test the per vocabulary 'reorder terms in' permission.
    $this->drupalLogin($this->users['reorder_first_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(403);

    $this->drupalLogin($this->users['overview_and_reorder_first_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(200);

    $assert_session->pageTextNotContains('Add vocabulary');
    $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.add_form')->toString());
    $this->assertNoSortableTable(FALSE);

    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.edit_form', ['taxonomy_vocabulary' => $vocabulary->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString(),
      ])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
      $overview_url = Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString();
      if ($delta === 0) {
        $assert_session->pageTextContains($vocabulary->label());
        $assert_session->pageTextContains($vocabulary->getDescription());
        $this->assertLinkByEndOfHref($overview_url);
      }
      else {
        $assert_session->pageTextNotContains($vocabulary->label());
        $assert_session->pageTextNotContains($vocabulary->getDescription());
        $this->assertNoLinkByEndOfHref($overview_url);
      }
    }

    // Test the 'reorder terms in any vocabulary' permission.
    $this->drupalLogin($this->users['reorder_any_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(403);

    $this->drupalLogin($this->users['overview_and_reorder_any_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(200);

    $assert_session->pageTextNotContains('Add vocabulary');
    $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.add_form')->toString());
    $this->assertNoSortableTable(FALSE);

    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.edit_form', ['taxonomy_vocabulary' => $vocabulary->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString(),
      ])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
      $overview_url = Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString();
      $assert_session->pageTextContains($vocabulary->label());
      $assert_session->pageTextContains($vocabulary->getDescription());
      $this->assertLinkByEndOfHref($overview_url);
    }

    // Test the per vocabulary 'reset' permission.
    $this->drupalLogin($this->users['reset_first_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(403);

    $this->drupalLogin($this->users['overview_and_reset_first_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(200);

    $assert_session->pageTextNotContains('Add vocabulary');
    $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.add_form')->toString());
    $this->assertNoSortableTable(FALSE);

    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.edit_form', ['taxonomy_vocabulary' => $vocabulary->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString(),
      ])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
      $overview_url = Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString();
      if ($delta === 0) {
        $assert_session->pageTextContains($vocabulary->label());
        $assert_session->pageTextContains($vocabulary->getDescription());
        $this->assertLinkByEndOfHref($overview_url);
      }
      else {
        $assert_session->pageTextNotContains($vocabulary->label());
        $assert_session->pageTextNotContains($vocabulary->getDescription());
        $this->assertNoLinkByEndOfHref($overview_url);
      }
    }

    // Test the 'reset any vocabulary' permission.
    $this->drupalLogin($this->users['reset_any_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(403);

    $this->drupalLogin($this->users['overview_and_reset_any_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(200);

    $assert_session->pageTextNotContains('Add vocabulary');
    $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.add_form')->toString());
    $this->assertNoSortableTable(FALSE);

    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.edit_form', ['taxonomy_vocabulary' => $vocabulary->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString(),
      ])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
      $overview_url = Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString();
      $assert_session->pageTextContains($vocabulary->label());
      $assert_session->pageTextContains($vocabulary->getDescription());
      $this->assertLinkByEndOfHref($overview_url);
    }

    // Test the per vocabulary 'select terms in' permission.
    $this->drupalLogin($this->users['select_first_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(403);

    $this->drupalLogin($this->users['overview_and_select_first_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(200);

    $assert_session->pageTextNotContains('Add vocabulary');
    $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.add_form')->toString());
    $this->assertNoSortableTable(FALSE);
    $assert_session->pageTextContains('No vocabularies available.');

    foreach ($this->vocabularies as $delta => $vocabulary) {
      $assert_session->pageTextNotContains($vocabulary->label());
      $assert_session->pageTextNotContains($vocabulary->getDescription());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.edit_form', ['taxonomy_vocabulary' => $vocabulary->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString(),
      ])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
    }

    // Test the 'select any term' permission.
    $this->drupalLogin($this->users['select_any_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(403);

    $this->drupalLogin($this->users['overview_and_select_any_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(200);

    $assert_session->pageTextNotContains('Add vocabulary');
    $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.add_form')->toString());
    $this->assertNoSortableTable(FALSE);
    $assert_session->pageTextContains('No vocabularies available.');

    foreach ($this->vocabularies as $delta => $vocabulary) {
      $assert_session->pageTextNotContains($vocabulary->label());
      $assert_session->pageTextNotContains($vocabulary->getDescription());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.edit_form', ['taxonomy_vocabulary' => $vocabulary->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString(),
      ])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
    }

    // Test the per vocabulary 'select unpublished terms in' permission.
    $this->drupalLogin($this->users['select_unpublished_first_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(403);

    $this->drupalLogin($this->users['overview_and_select_unpublished_first_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(200);

    $assert_session->pageTextNotContains('Add vocabulary');
    $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.add_form')->toString());
    $this->assertNoSortableTable(FALSE);
    $assert_session->pageTextContains('No vocabularies available.');

    foreach ($this->vocabularies as $delta => $vocabulary) {
      $assert_session->pageTextNotContains($vocabulary->label());
      $assert_session->pageTextNotContains($vocabulary->getDescription());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.edit_form', ['taxonomy_vocabulary' => $vocabulary->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString(),
      ])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
    }

    // Test the 'select any unpublished term' permission.
    $this->drupalLogin($this->users['select_any_unpublished_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(403);

    $this->drupalLogin($this->users['overview_and_select_any_unpublished_vocabulary']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(200);

    $assert_session->pageTextNotContains('Add vocabulary');
    $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.add_form')->toString());
    $this->assertNoSortableTable(FALSE);
    $assert_session->pageTextContains('No vocabularies available.');

    foreach ($this->vocabularies as $delta => $vocabulary) {
      $assert_session->pageTextNotContains($vocabulary->label());
      $assert_session->pageTextNotContains($vocabulary->getDescription());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_vocabulary.edit_form', ['taxonomy_vocabulary' => $vocabulary->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString(),
      ])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
    }
  }

  /**
   * Tests bundle dependencies for permissions provided by Taxonomy Access Fix.
   */
  public function testTaxonomyVocabularyBundlePermissions() {
    $assert_session = $this->assertSession();
    $this->drupalLogin($this->users['administer_permissions']);

    $this->drupalGet('admin/structure/taxonomy/manage/' . $this->vocabularies[0]->id() . '/overview/permissions');
    $assert_session->statusCodeEquals(200);

    $assert_session->pageTextContains('Taxonomy Access Fix');
    $assert_session->pageTextContains(': View published terms');
  }

  /**
   * Tests access to Taxonomy Vocabulary overview page.
   */
  public function testTaxonomyVocabularyOverview() {
    $assert_session = $this->assertSession();

    $published_terms = [];
    $unpublished_terms = [];

    foreach ($this->vocabularies as $delta => $vocabulary) {
      $published_terms[$delta] = $this->createTerm($vocabulary, [
        'name' => 'Published term',
        'status' => 1,
      ]);
      $published_terms[$delta]->save();
      $unpublished_terms[$delta] = $this->createTerm($vocabulary, [
        'name' => 'Unpublished term',
        'status' => 0,
      ]);
      $unpublished_terms[$delta]->save();
    }

    // Test the 'administer taxonomy' permission.
    $this->drupalLogin($this->users['administer']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(200);

      $this->assertElementByCssSelector('#edit-reset-alphabetical');
      $this->assertSortableTable();

      $this->assertLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.edit_form', ['taxonomy_term' => $published_terms[$delta]->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
      ])->toString());
      $this->assertLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.delete_form', ['taxonomy_term' => $published_terms[$delta]->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
      ])->toString());

      $this->assertLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.edit_form', ['taxonomy_term' => $unpublished_terms[$delta]->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
      ])->toString());
      $this->assertLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.delete_form', ['taxonomy_term' => $unpublished_terms[$delta]->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
      ])->toString());

      $assert_session->pageTextContains('Add term');
      $this->assertLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(200);
    }

    // Test the 'access taxonomy overview' permission.
    $this->drupalLogin($this->users['overview']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }

    // Test the per vocabulary 'create terms in' permission.
    $this->drupalLogin($this->users['create_first_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }

    $this->drupalLogin($this->users['overview_and_create_first_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      if ($delta === 0) {
        $assert_session->statusCodeEquals(200);

        $this->assertNoElementByCssSelector('#edit-reset-alphabetical');
        $this->assertNoSortableTable();

        $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.edit_form', ['taxonomy_term' => $published_terms[$delta]->id()])->setOption('query', [
          'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
        ])->toString());
        $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.delete_form', ['taxonomy_term' => $published_terms[$delta]->id()])->setOption('query', [
          'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
        ])->toString());

        $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.edit_form', ['taxonomy_term' => $unpublished_terms[$delta]->id()])->setOption('query', [
          'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
        ])->toString());
        $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.delete_form', ['taxonomy_term' => $unpublished_terms[$delta]->id()])->setOption('query', [
          'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
        ])->toString());

        $assert_session->pageTextContains('Add term');
        $this->assertLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
      }
      else {
        $assert_session->statusCodeEquals(403);
      }

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }

    // Test the 'create any term' permission.
    $this->drupalLogin($this->users['create_any_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }

    $this->drupalLogin($this->users['overview_and_create_any_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(200);

      $this->assertNoElementByCssSelector('#edit-reset-alphabetical');
      $this->assertNoSortableTable();

      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.edit_form', ['taxonomy_term' => $published_terms[$delta]->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
      ])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.delete_form', ['taxonomy_term' => $published_terms[$delta]->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
      ])->toString());

      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.edit_form', ['taxonomy_term' => $unpublished_terms[$delta]->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
      ])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.delete_form', ['taxonomy_term' => $unpublished_terms[$delta]->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
      ])->toString());

      $assert_session->pageTextContains('Add term');
      $this->assertLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }

    // Test the per vocabulary 'edit terms in' permission.
    $this->drupalLogin($this->users['update_first_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }

    $this->drupalLogin($this->users['overview_and_update_first_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      if ($delta === 0) {
        $assert_session->statusCodeEquals(200);

        $this->assertNoElementByCssSelector('#edit-reset-alphabetical');
        $this->assertNoSortableTable();

        $this->assertLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.edit_form', ['taxonomy_term' => $published_terms[$delta]->id()])->setOption('query', [
          'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
        ])->toString());
        $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.delete_form', ['taxonomy_term' => $published_terms[$delta]->id()])->setOption('query', [
          'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
        ])->toString());

        $this->assertLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.edit_form', ['taxonomy_term' => $unpublished_terms[$delta]->id()])->setOption('query', [
          'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
        ])->toString());
        $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.delete_form', ['taxonomy_term' => $unpublished_terms[$delta]->id()])->setOption('query', [
          'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
        ])->toString());

        $assert_session->pageTextNotContains('Add term');
        $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
      }
      else {
        $assert_session->statusCodeEquals(403);
      }

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }

    // Test the 'update any term' permission.
    $this->drupalLogin($this->users['update_any_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }

    $this->drupalLogin($this->users['overview_and_update_any_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(200);

      $this->assertNoElementByCssSelector('#edit-reset-alphabetical');
      $this->assertNoSortableTable();

      $this->assertLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.edit_form', ['taxonomy_term' => $published_terms[$delta]->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
      ])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.delete_form', ['taxonomy_term' => $published_terms[$delta]->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
      ])->toString());

      $this->assertLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.edit_form', ['taxonomy_term' => $unpublished_terms[$delta]->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
      ])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.delete_form', ['taxonomy_term' => $unpublished_terms[$delta]->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
      ])->toString());

      $assert_session->pageTextNotContains('Add term');
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }

    // Test the per vocabulary 'delete terms in' permission.
    $this->drupalLogin($this->users['delete_first_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }

    $this->drupalLogin($this->users['overview_and_delete_first_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      if ($delta === 0) {
        $assert_session->statusCodeEquals(200);

        $this->assertNoElementByCssSelector('#edit-reset-alphabetical');
        $this->assertNoSortableTable();

        $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.edit_form', ['taxonomy_term' => $published_terms[$delta]->id()])->setOption('query', [
          'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
        ])->toString());
        $this->assertLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.delete_form', ['taxonomy_term' => $published_terms[$delta]->id()])->setOption('query', [
          'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
        ])->toString());

        $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.edit_form', ['taxonomy_term' => $unpublished_terms[$delta]->id()])->setOption('query', [
          'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
        ])->toString());
        $this->assertLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.delete_form', ['taxonomy_term' => $unpublished_terms[$delta]->id()])->setOption('query', [
          'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
        ])->toString());

        $assert_session->pageTextNotContains('Add term');
        $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
      }
      else {
        $assert_session->statusCodeEquals(403);
      }

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }

    // Test the per vocabulary 'delete any term' permission.
    $this->drupalLogin($this->users['delete_any_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }

    $this->drupalLogin($this->users['overview_and_delete_any_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(200);

      $this->assertNoElementByCssSelector('#edit-reset-alphabetical');
      $this->assertNoSortableTable();

      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.edit_form', ['taxonomy_term' => $published_terms[$delta]->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
      ])->toString());
      $this->assertLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.delete_form', ['taxonomy_term' => $published_terms[$delta]->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
      ])->toString());

      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.edit_form', ['taxonomy_term' => $unpublished_terms[$delta]->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
      ])->toString());
      $this->assertLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.delete_form', ['taxonomy_term' => $unpublished_terms[$delta]->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
      ])->toString());

      $assert_session->pageTextNotContains('Add term');
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }

    // Test the per vocabulary 'reorder terms in' permission.
    $this->drupalLogin($this->users['reorder_first_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }

    $this->drupalLogin($this->users['overview_and_reorder_first_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      if ($delta === 0) {
        $assert_session->statusCodeEquals(200);

        $this->assertNoElementByCssSelector('#edit-reset-alphabetical');
        // @todo Enable once Issue 2958658 has been fixed.
        // $this->assertSortableTable();
        $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.edit_form', ['taxonomy_term' => $published_terms[$delta]->id()])->setOption('query', [
          'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
        ])->toString());
        $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.delete_form', ['taxonomy_term' => $published_terms[$delta]->id()])->setOption('query', [
          'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
        ])->toString());

        $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.edit_form', ['taxonomy_term' => $unpublished_terms[$delta]->id()])->setOption('query', [
          'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
        ])->toString());
        $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.delete_form', ['taxonomy_term' => $unpublished_terms[$delta]->id()])->setOption('query', [
          'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
        ])->toString());

        $assert_session->pageTextNotContains('Add term');
        $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());
      }
      else {
        $assert_session->statusCodeEquals(403);
      }

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }

    // Test the 'reorder terms in any vocabulary' permission.
    $this->drupalLogin($this->users['reorder_any_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }

    $this->drupalLogin($this->users['overview_and_reorder_any_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(200);

      $this->assertNoElementByCssSelector('#edit-reset-alphabetical');
      // @todo Enable once Issue 2958658 has been fixed.
      // $this->assertSortableTable();
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.edit_form', ['taxonomy_term' => $published_terms[$delta]->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
      ])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.delete_form', ['taxonomy_term' => $published_terms[$delta]->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
      ])->toString());

      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.edit_form', ['taxonomy_term' => $unpublished_terms[$delta]->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
      ])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.delete_form', ['taxonomy_term' => $unpublished_terms[$delta]->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
      ])->toString());

      $assert_session->pageTextNotContains('Add term');
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }

    // Test the per vocabulary 'reset' permission.
    $this->drupalLogin($this->users['reset_first_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      if ($delta === 0) {
        $assert_session->statusCodeEquals(200);
      }
      else {
        $assert_session->statusCodeEquals(403);
      }
    }

    $this->drupalLogin($this->users['overview_and_reset_first_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      if ($delta === 0) {
        $assert_session->statusCodeEquals(200);

        $this->assertElementByCssSelector('#edit-reset-alphabetical');
        $this->assertNoSortableTable();
        $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.edit_form', ['taxonomy_term' => $published_terms[$delta]->id()])->setOption('query', [
          'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
        ])->toString());
        $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.delete_form', ['taxonomy_term' => $published_terms[$delta]->id()])->setOption('query', [
          'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
        ])->toString());

        $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.edit_form', ['taxonomy_term' => $unpublished_terms[$delta]->id()])->setOption('query', [
          'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
        ])->toString());
        $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.delete_form', ['taxonomy_term' => $unpublished_terms[$delta]->id()])->setOption('query', [
          'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
        ])->toString());

        $assert_session->pageTextNotContains('Add term');
        $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());

        $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
        $assert_session->statusCodeEquals(200);
      }
      else {
        $assert_session->statusCodeEquals(403);

        $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
        $assert_session->statusCodeEquals(403);
      }
    }

    // Test the 'reset any vocabulary' permission.
    $this->drupalLogin($this->users['reset_any_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(200);
    }

    $this->drupalLogin($this->users['overview_and_reset_any_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(200);

      $this->assertElementByCssSelector('#edit-reset-alphabetical');
      $this->assertNoSortableTable();
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.edit_form', ['taxonomy_term' => $published_terms[$delta]->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
      ])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.delete_form', ['taxonomy_term' => $published_terms[$delta]->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
      ])->toString());

      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.edit_form', ['taxonomy_term' => $unpublished_terms[$delta]->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
      ])->toString());
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.delete_form', ['taxonomy_term' => $unpublished_terms[$delta]->id()])->setOption('query', [
        'destination' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString(),
      ])->toString());

      $assert_session->pageTextNotContains('Add term');
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()])->toString());

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(200);
    }

    // Test the per vocabulary 'view terms in' permission.
    $this->drupalLogin($this->users['view_first_vocabulary']);
    foreach ($this->vocabularies as $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }
    $this->drupalLogin($this->users['overview_and_view_first_vocabulary']);
    foreach ($this->vocabularies as $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }

    // Test the 'view any term' permission.
    $this->drupalLogin($this->users['view_any_vocabulary']);
    foreach ($this->vocabularies as $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }
    $this->drupalLogin($this->users['overview_and_view_any_vocabulary']);
    foreach ($this->vocabularies as $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }

    // Test the per vocabulary 'view unpublished terms in' permission.
    $this->drupalLogin($this->users['view_unpublished_first_vocabulary']);
    foreach ($this->vocabularies as $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }
    $this->drupalLogin($this->users['overview_and_view_unpublished_first_vocabulary']);
    foreach ($this->vocabularies as $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }

    // Test the 'view any unpublished term' permission.
    $this->drupalLogin($this->users['view_any_unpublished_vocabulary']);
    foreach ($this->vocabularies as $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }
    $this->drupalLogin($this->users['overview_and_view_any_unpublished_vocabulary']);
    foreach ($this->vocabularies as $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }

    // Test the per vocabulary 'view term names in' permission.
    $this->drupalLogin($this->users['view_name_first_vocabulary']);
    foreach ($this->vocabularies as $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }
    $this->drupalLogin($this->users['overview_and_view_name_first_vocabulary']);
    foreach ($this->vocabularies as $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }

    // Test the 'view any term name' permission.
    $this->drupalLogin($this->users['view_name_any_vocabulary']);
    foreach ($this->vocabularies as $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }
    $this->drupalLogin($this->users['overview_and_view_name_any_vocabulary']);
    foreach ($this->vocabularies as $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }

    // Test the per vocabulary 'view unpublished term names in' permission.
    $this->drupalLogin($this->users['view_name_unpublished_first_vocabulary']);
    foreach ($this->vocabularies as $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }
    $this->drupalLogin($this->users['overview_and_view_name_unpublished_first_vocabulary']);
    foreach ($this->vocabularies as $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }

    // Test the 'view any unpublished term name' permission.
    $this->drupalLogin($this->users['view_name_any_unpublished_vocabulary']);
    foreach ($this->vocabularies as $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }
    $this->drupalLogin($this->users['overview_and_view_name_any_unpublished_vocabulary']);
    foreach ($this->vocabularies as $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }

    // Test the per vocabulary 'view vocabulary name of' permission.
    $this->drupalLogin($this->users['view_vocabulary_name_first_vocabulary']);
    foreach ($this->vocabularies as $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }
    $this->drupalLogin($this->users['overview_and_view_vocabulary_name_first_vocabulary']);
    foreach ($this->vocabularies as $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }

    // Test the 'view any vocabulary name' permission.
    $this->drupalLogin($this->users['view_vocabulary_name_any_vocabulary']);
    foreach ($this->vocabularies as $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }
    $this->drupalLogin($this->users['overview_and_view_vocabulary_name_any_vocabulary']);
    foreach ($this->vocabularies as $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }

    // Test the per vocabulary 'select terms in' permission.
    $this->drupalLogin($this->users['select_first_vocabulary']);
    foreach ($this->vocabularies as $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }
    $this->drupalLogin($this->users['overview_and_select_first_vocabulary']);
    foreach ($this->vocabularies as $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }

    // Test the per vocabulary 'select any term' permission.
    $this->drupalLogin($this->users['select_any_vocabulary']);
    foreach ($this->vocabularies as $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }
    $this->drupalLogin($this->users['overview_and_select_any_vocabulary']);
    foreach ($this->vocabularies as $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }

    // Test the per vocabulary 'select unpublished terms in' permission.
    $this->drupalLogin($this->users['select_unpublished_first_vocabulary']);
    foreach ($this->vocabularies as $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }
    $this->drupalLogin($this->users['overview_and_select_unpublished_first_vocabulary']);
    foreach ($this->vocabularies as $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }

    // Test the per vocabulary 'select any unpublished term' permission.
    $this->drupalLogin($this->users['select_any_unpublished_vocabulary']);
    foreach ($this->vocabularies as $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }
    $this->drupalLogin($this->users['overview_and_select_any_unpublished_vocabulary']);
    foreach ($this->vocabularies as $vocabulary) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/overview');
      $assert_session->statusCodeEquals(403);
      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/reset');
      $assert_session->statusCodeEquals(403);
    }
  }

  /**
   * Test the vocabulary overview with no vocabularies.
   */
  public function testTaxonomyVocabularyNoVocabularies() {
    $assert_session = $this->assertSession();

    $vocabularies = Vocabulary::loadMultiple();
    foreach ($vocabularies as $vocabulary) {
      $vocabulary->delete();
    }
    $this->assertEmpty(Vocabulary::loadMultiple(), 'No vocabularies found.');
    $this->drupalLogin($this->users['administer']);
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('No vocabularies available.');
  }

  /**
   * Tests access to entity operations on Taxonomy Vocabulary entities.
   */
  public function testTaxonomyVocabularyOperations() {
    $assert_session = $this->assertSession();

    // Test the 'administer taxonomy' permission.
    $this->drupalLogin($this->users['administer']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertVocabularyAccess($vocabulary, 'view label', TRUE);
      $this->assertVocabularyAccess($vocabulary, 'view', TRUE);
      $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', TRUE);
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', TRUE);
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', TRUE);
      $this->assertVocabularyAccess($vocabulary, 'create', TRUE);
      $this->assertVocabularyAccess($vocabulary, 'update', TRUE);
      $this->assertVocabularyAccess($vocabulary, 'delete', TRUE);
    }

    // Test the per vocabulary 'access taxonomy overview' permission.
    $this->drupalLogin($this->users['overview']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertVocabularyAccess($vocabulary, 'view label', FALSE, "The 'view vocabulary name of {$vocabulary->id()}' OR 'view any vocabulary name' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'view', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }

    // Test the per vocabulary 'create terms in' permission.
    $this->drupalLogin($this->users['create_first_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertVocabularyAccess($vocabulary, 'view label', FALSE, "The 'view vocabulary name of {$vocabulary->id()}' OR 'view any vocabulary name' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'view', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }
    $this->drupalLogin($this->users['overview_and_create_first_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      if ($delta === 0) {
        $this->assertVocabularyAccess($vocabulary, 'view label', TRUE);
        $this->assertVocabularyAccess($vocabulary, 'view', TRUE);
        $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', TRUE);
      }
      else {
        $this->assertVocabularyAccess($vocabulary, 'view label', FALSE, "The 'view vocabulary name of {$vocabulary->id()}' OR 'view any vocabulary name' OR 'administer taxonomy' permission is required.");
        $this->assertVocabularyAccess($vocabulary, 'view', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
        $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      }
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }

    // Test the 'create any term' permission.
    $this->drupalLogin($this->users['create_any_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertVocabularyAccess($vocabulary, 'view label', FALSE, "The 'view vocabulary name of {$vocabulary->id()}' OR 'view any vocabulary name' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'view', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }
    $this->drupalLogin($this->users['overview_and_create_any_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertVocabularyAccess($vocabulary, 'view label', TRUE);
      $this->assertVocabularyAccess($vocabulary, 'view', TRUE);
      $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', TRUE);
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }

    // Test the per vocabulary 'update terms in' permission.
    $this->drupalLogin($this->users['update_first_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertVocabularyAccess($vocabulary, 'view label', FALSE, "The 'view vocabulary name of {$vocabulary->id()}' OR 'view any vocabulary name' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'view', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }
    $this->drupalLogin($this->users['overview_and_update_first_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      if ($delta === 0) {
        $this->assertVocabularyAccess($vocabulary, 'view label', TRUE);
        $this->assertVocabularyAccess($vocabulary, 'view', TRUE);
        $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', TRUE);
      }
      else {
        $this->assertVocabularyAccess($vocabulary, 'view label', FALSE, "The 'view vocabulary name of {$vocabulary->id()}' OR 'view any vocabulary name' OR 'administer taxonomy' permission is required.");
        $this->assertVocabularyAccess($vocabulary, 'view', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
        $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      }
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }

    // Test the 'update any term' permission.
    $this->drupalLogin($this->users['update_any_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertVocabularyAccess($vocabulary, 'view label', FALSE, "The 'view vocabulary name of {$vocabulary->id()}' OR 'view any vocabulary name' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'view', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }
    $this->drupalLogin($this->users['overview_and_update_any_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertVocabularyAccess($vocabulary, 'view label', TRUE);
      $this->assertVocabularyAccess($vocabulary, 'view', TRUE);
      $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', TRUE);
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }

    // Test the per vocabulary 'delete terms in' permission.
    $this->drupalLogin($this->users['delete_first_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertVocabularyAccess($vocabulary, 'view label', FALSE, "The 'view vocabulary name of {$vocabulary->id()}' OR 'view any vocabulary name' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'view', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }
    $this->drupalLogin($this->users['overview_and_delete_first_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      if ($delta === 0) {
        $this->assertVocabularyAccess($vocabulary, 'view label', TRUE);
        $this->assertVocabularyAccess($vocabulary, 'view', TRUE);
        $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', TRUE);
      }
      else {
        $this->assertVocabularyAccess($vocabulary, 'view label', FALSE, "The 'view vocabulary name of {$vocabulary->id()}' OR 'view any vocabulary name' OR 'administer taxonomy' permission is required.");
        $this->assertVocabularyAccess($vocabulary, 'view', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
        $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      }
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }

    // Test the 'delete any term' permission.
    $this->drupalLogin($this->users['delete_any_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertVocabularyAccess($vocabulary, 'view label', FALSE, "The 'view vocabulary name of {$vocabulary->id()}' OR 'view any vocabulary name' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'view', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }
    $this->drupalLogin($this->users['overview_and_delete_any_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertVocabularyAccess($vocabulary, 'view label', TRUE);
      $this->assertVocabularyAccess($vocabulary, 'view', TRUE);
      $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', TRUE);
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }

    // Test the per vocabulary 'view terms in' permission.
    $this->drupalLogin($this->users['view_first_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertVocabularyAccess($vocabulary, 'view label', FALSE, "The 'view vocabulary name of {$vocabulary->id()}' OR 'view any vocabulary name' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'view', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }
    $this->drupalLogin($this->users['overview_and_view_first_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertVocabularyAccess($vocabulary, 'view label', FALSE, "The 'view vocabulary name of {$vocabulary->id()}' OR 'view any vocabulary name' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'view', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }

    // Test the per vocabulary 'view any term' permission.
    $this->drupalLogin($this->users['view_any_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertVocabularyAccess($vocabulary, 'view label', FALSE, "The 'view vocabulary name of {$vocabulary->id()}' OR 'view any vocabulary name' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'view', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }
    $this->drupalLogin($this->users['overview_and_view_any_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertVocabularyAccess($vocabulary, 'view label', FALSE, "The 'view vocabulary name of {$vocabulary->id()}' OR 'view any vocabulary name' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'view', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }

    // Test the per vocabulary 'view unpublished terms in' permission.
    $this->drupalLogin($this->users['view_unpublished_first_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertVocabularyAccess($vocabulary, 'view label', FALSE, "The 'view vocabulary name of {$vocabulary->id()}' OR 'view any vocabulary name' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'view', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }
    $this->drupalLogin($this->users['overview_and_view_unpublished_first_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertVocabularyAccess($vocabulary, 'view label', FALSE, "The 'view vocabulary name of {$vocabulary->id()}' OR 'view any vocabulary name' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'view', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }

    // Test the per vocabulary 'view any unpublished term' permission.
    $this->drupalLogin($this->users['view_any_unpublished_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertVocabularyAccess($vocabulary, 'view label', FALSE, "The 'view vocabulary name of {$vocabulary->id()}' OR 'view any vocabulary name' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'view', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }
    $this->drupalLogin($this->users['overview_and_view_any_unpublished_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertVocabularyAccess($vocabulary, 'view label', FALSE, "The 'view vocabulary name of {$vocabulary->id()}' OR 'view any vocabulary name' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'view', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }

    // Test the per vocabulary 'reorder terms in' permission.
    $this->drupalLogin($this->users['reorder_first_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertVocabularyAccess($vocabulary, 'view label', FALSE, "The 'view vocabulary name of {$vocabulary->id()}' OR 'view any vocabulary name' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'view', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      if ($delta === 0) {
        $this->assertVocabularyAccess($vocabulary, 'reorder_terms', TRUE);
      }
      else {
        $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      }
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }
    $this->drupalLogin($this->users['overview_and_reorder_first_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      if ($delta === 0) {
        $this->assertVocabularyAccess($vocabulary, 'view label', TRUE);
        $this->assertVocabularyAccess($vocabulary, 'view', TRUE);
        $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', TRUE);
        $this->assertVocabularyAccess($vocabulary, 'reorder_terms', TRUE);
      }
      else {
        $this->assertVocabularyAccess($vocabulary, 'view label', FALSE, "The 'view vocabulary name of {$vocabulary->id()}' OR 'view any vocabulary name' OR 'administer taxonomy' permission is required.");
        $this->assertVocabularyAccess($vocabulary, 'view', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
        $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
        $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      }
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }

    // Test the 'reorder terms in any vocabulary' permission.
    $this->drupalLogin($this->users['reorder_any_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertVocabularyAccess($vocabulary, 'view label', FALSE, "The 'view vocabulary name of {$vocabulary->id()}' OR 'view any vocabulary name' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'view', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', TRUE);
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }
    $this->drupalLogin($this->users['overview_and_reorder_any_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertVocabularyAccess($vocabulary, 'view label', TRUE);
      $this->assertVocabularyAccess($vocabulary, 'view', TRUE);
      $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', TRUE);
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', TRUE);
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }

    // Test the per vocabulary 'reset' permission.
    $this->drupalLogin($this->users['reset_first_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertVocabularyAccess($vocabulary, 'view label', FALSE, "The 'view vocabulary name of {$vocabulary->id()}' OR 'view any vocabulary name' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'view', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      if ($delta === 0) {
        $this->assertVocabularyAccess($vocabulary, 'reset all weights', TRUE);
      }
      else {
        $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      }
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }
    $this->drupalLogin($this->users['overview_and_reset_first_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      if ($delta === 0) {
        $this->assertVocabularyAccess($vocabulary, 'view label', TRUE);
        $this->assertVocabularyAccess($vocabulary, 'view', TRUE);
        $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', TRUE);
        $this->assertVocabularyAccess($vocabulary, 'reset all weights', TRUE);
      }
      else {
        $this->assertVocabularyAccess($vocabulary, 'view label', FALSE, "The 'view vocabulary name of {$vocabulary->id()}' OR 'view any vocabulary name' OR 'administer taxonomy' permission is required.");
        $this->assertVocabularyAccess($vocabulary, 'view', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
        $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
        $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      }
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }

    // Test the 'reset any vocabulary' permission.
    $this->drupalLogin($this->users['reset_any_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertVocabularyAccess($vocabulary, 'view label', FALSE, "The 'view vocabulary name of {$vocabulary->id()}' OR 'view any vocabulary name' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'view', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', TRUE);
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }
    $this->drupalLogin($this->users['overview_and_reset_any_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertVocabularyAccess($vocabulary, 'view label', TRUE);
      $this->assertVocabularyAccess($vocabulary, 'view', TRUE);
      $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', TRUE);
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', TRUE);
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }

    // Test the per vocabulary 'select terms in' permission.
    $this->drupalLogin($this->users['select_first_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertVocabularyAccess($vocabulary, 'view label', FALSE, "The 'view vocabulary name of {$vocabulary->id()}' OR 'view any vocabulary name' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'view', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }
    $this->drupalLogin($this->users['overview_and_select_first_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertVocabularyAccess($vocabulary, 'view label', FALSE, "The 'view vocabulary name of {$vocabulary->id()}' OR 'view any vocabulary name' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'view', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }

    // Test the per vocabulary 'select any term' permission.
    $this->drupalLogin($this->users['select_any_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertVocabularyAccess($vocabulary, 'view label', FALSE, "The 'view vocabulary name of {$vocabulary->id()}' OR 'view any vocabulary name' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'view', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }
    $this->drupalLogin($this->users['overview_and_select_any_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertVocabularyAccess($vocabulary, 'view label', FALSE, "The 'view vocabulary name of {$vocabulary->id()}' OR 'view any vocabulary name' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'view', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }

    // Test the per vocabulary 'select unpublished terms in' permission.
    $this->drupalLogin($this->users['select_unpublished_first_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertVocabularyAccess($vocabulary, 'view label', FALSE, "The 'view vocabulary name of {$vocabulary->id()}' OR 'view any vocabulary name' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'view', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }
    $this->drupalLogin($this->users['overview_and_select_unpublished_first_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertVocabularyAccess($vocabulary, 'view label', FALSE, "The 'view vocabulary name of {$vocabulary->id()}' OR 'view any vocabulary name' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'view', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }

    // Test the per vocabulary 'select any unpublished term' permission.
    $this->drupalLogin($this->users['select_any_unpublished_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertVocabularyAccess($vocabulary, 'view label', FALSE, "The 'view vocabulary name of {$vocabulary->id()}' OR 'view any vocabulary name' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'view', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }
    $this->drupalLogin($this->users['overview_and_select_any_unpublished_vocabulary']);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertVocabularyAccess($vocabulary, 'view label', FALSE, "The 'view vocabulary name of {$vocabulary->id()}' OR 'view any vocabulary name' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'view', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'access taxonomy overview', FALSE, "The 'access taxonomy overview' and one of the 'create terms in {$vocabulary->id()}', 'create any term', 'delete terms in {$vocabulary->id()}', 'delete any term', 'edit terms in {$vocabulary->id()}', 'update any term', 'reorder terms in {$vocabulary->id()}', 'reorder terms in any vocabulary', 'reset {$vocabulary->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
      $this->assertVocabularyAccess($vocabulary, 'reorder_terms', FALSE, "The 'reorder terms in {$vocabulary->id()}' OR 'reorder terms in any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'reset all weights', FALSE, "The 'reset {$vocabulary->id()}' OR 'reset any vocabulary' OR 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'create', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'update', FALSE, "The 'administer taxonomy' permission is required.");
      $this->assertVocabularyAccess($vocabulary, 'delete', FALSE, "The 'administer taxonomy' permission is required.");
    }
  }

  /**
   * Checks access to Taxonomy Vocabulary entity.
   *
   * @param \Drupal\taxonomy\VocabularyInterface $vocabulary
   *   A Taxonomy Vocabulary entity.
   * @param string $access_operation
   *   The entity operation, e.g. 'view', 'edit', 'delete', etc.
   * @param bool $access_allowed
   *   Whether the current user has access to the given operation or not.
   * @param string $access_reason
   *   (optional) The reason of the access result.
   */
  protected function assertVocabularyAccess(VocabularyInterface $vocabulary, string $access_operation, bool $access_allowed, string $access_reason = '') {
    $access_result = $vocabulary->access($access_operation, NULL, TRUE);
    $this->assertSame($access_allowed, $access_result->isAllowed());
    if ($access_reason) {
      /** @var \Drupal\Core\Access\AccessResultReasonInterface $access_result */
      $this->assertSame($access_reason, $access_result->getReason());
    }
  }

}
