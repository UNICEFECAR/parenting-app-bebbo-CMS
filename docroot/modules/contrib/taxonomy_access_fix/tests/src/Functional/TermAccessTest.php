<?php

namespace Drupal\Tests\taxonomy_access_fix\Functional;

use Drupal\Core\Url;
use Drupal\Tests\taxonomy\Functional\TermAccessTest as OriginalTermAccessTest;
use Drupal\Tests\taxonomy_access_fix\Traits\TaxonomyAccessFixTestTrait;

/**
 * Tests taxonomy term access.
 *
 * @group taxonomy
 */
class TermAccessTest extends OriginalTermAccessTest {

  use TaxonomyAccessFixTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['taxonomy', 'block'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->vocabularies = [
      $this->createVocabulary(),
      $this->createVocabulary(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function testTermAccess(): void {
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

    // Try to view, edit and delete an unpublished taxonomy term so that we can
    // assert the denial reasons given by Taxonomy module. Access checks for
    // those operations are altered or reimplemented in our replacement access
    // control handlers. Asserting the original reasons should flag unexpected
    // changes in Core's implementation.
    // @see \Drupal\taxonomy_access_fix\TermAccessFixTermControlHandler::checkAccess()
    // @see \Drupal\taxonomy_access_fix\VocabularyAccessControlHandler::checkAccess()
    $account_access_content = $this->drupalCreateUser(['access content']);
    $this->drupalLogin($account_access_content);
    $this->drupalGet('taxonomy/term/' . $unpublished_terms[0]->id());
    $assert_session->statusCodeEquals(403);
    $this->assertTermAccess($unpublished_terms[0], 'view', FALSE, "The 'access content' permission is required and the taxonomy term must be published.");
    $this->drupalGet('taxonomy/term/' . $unpublished_terms[0]->id() . '/delete');
    $assert_session->statusCodeEquals(403);
    $this->assertTermAccess($unpublished_terms[0], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[0]->id()}' OR 'administer taxonomy'.");
    $this->drupalGet('taxonomy/term/' . $unpublished_terms[0]->id() . '/edit');
    $assert_session->statusCodeEquals(403);
    $this->assertTermAccess($unpublished_terms[0], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[0]->id()}' OR 'administer taxonomy'.");

    // Install Taxonomy Access Fix.
    $this->installModules(['taxonomy_access_fix']);

    // Test 'access content' permission.
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertTermAccess($published_terms[$delta], 'view label', FALSE, "The 'view term names in {$this->vocabularies[$delta]->id()}' OR 'view any term name' OR 'administer taxonomy' permission is required.");
      $this->assertTermAccess($unpublished_terms[$delta], 'view label', FALSE, "The 'view unpublished term names in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term name' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'view', FALSE, "The 'view terms in {$this->vocabularies[$delta]->id()}' OR 'view any term' OR 'administer taxonomy' permission is required.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'view', FALSE, "The 'view unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->assertTermAccess($published_terms[$delta], 'select', FALSE, "The 'select terms in {$this->vocabularies[$delta]->id()}' OR 'select any term' OR 'administer taxonomy' permission is required.");
      $this->assertTermAccess($unpublished_terms[$delta], 'select', FALSE, "The 'select unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'select any unpublished term' OR 'administer taxonomy' permission is required.");
    }

    // Test the 'administer taxonomy' permission.
    $account_administer = $this->drupalCreateUser(['administer taxonomy']);
    $this->drupalLogin($account_administer);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertTermAccess($published_terms[$delta], 'view label', TRUE);
      $this->assertTermAccess($unpublished_terms[$delta], 'view label', TRUE);

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id());
      $assert_session->statusCodeEquals(200);
      $this->assertTermAccess($published_terms[$delta], 'view', TRUE);
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id());
      $assert_session->statusCodeEquals(200);
      $this->assertTermAccess($unpublished_terms[$delta], 'view', TRUE);

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(200);
      $this->assertSession()->pageTextContains('Delete');
      $this->assertLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.delete_form', ['taxonomy_term' => $published_terms[$delta]->id()])->toString());
      $this->assertTermAccess($published_terms[$delta], 'update', TRUE);
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(200);
      $this->assertSession()->pageTextContains('Delete');
      $this->assertLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.delete_form', ['taxonomy_term' => $unpublished_terms[$delta]->id()])->toString());
      $this->assertTermAccess($unpublished_terms[$delta], 'update', TRUE);

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(200);
      $this->assertTermAccess($published_terms[$delta], 'delete', TRUE);
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(200);
      $this->assertTermAccess($unpublished_terms[$delta], 'delete', TRUE);

      $this->assertTermAccess($published_terms[$delta], 'select', TRUE);
      $this->assertTermAccess($unpublished_terms[$delta], 'select', TRUE);
    }

    // Test the per vocabulary 'create terms in' permission.
    $account_create_first_vocabulary = $this->drupalCreateUser(['create terms in ' . $this->vocabularies[0]->id()]);
    $this->drupalLogin($account_create_first_vocabulary);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertTermAccess($published_terms[$delta], 'view label', FALSE, "The 'view term names in {$this->vocabularies[$delta]->id()}' OR 'view any term name' OR 'administer taxonomy' permission is required.");
      $this->assertTermAccess($unpublished_terms[$delta], 'view label', FALSE, "The 'view unpublished term names in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term name' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'view', FALSE, "The 'view terms in {$this->vocabularies[$delta]->id()}' OR 'view any term' OR 'administer taxonomy' permission is required.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'view', FALSE, "The 'view unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/add');
      if ($delta === 0) {
        $assert_session->statusCodeEquals(200);
        $this->assertTermAccess($published_terms[$delta], 'create', TRUE);
      }
      else {
        $assert_session->statusCodeEquals(403);
        $this->assertTermAccess($published_terms[$delta], 'create', FALSE, "The following permissions are required: 'create terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      }

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->assertTermAccess($published_terms[$delta], 'select', FALSE, "The 'select terms in {$this->vocabularies[$delta]->id()}' OR 'select any term' OR 'administer taxonomy' permission is required.");
      $this->assertTermAccess($unpublished_terms[$delta], 'select', FALSE, "The 'select unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'select any unpublished term' OR 'administer taxonomy' permission is required.");
    }

    // Test the 'create any term' permission.
    $account_create_any = $this->drupalCreateUser(['create any term']);
    $this->drupalLogin($account_create_any);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertTermAccess($published_terms[$delta], 'view label', FALSE, "The 'view term names in {$this->vocabularies[$delta]->id()}' OR 'view any term name' OR 'administer taxonomy' permission is required.");
      $this->assertTermAccess($unpublished_terms[$delta], 'view label', FALSE, "The 'view unpublished term names in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term name' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'view', FALSE, "The 'view terms in {$this->vocabularies[$delta]->id()}' OR 'view any term' OR 'administer taxonomy' permission is required.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'view', FALSE, "The 'view unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/add');
      $assert_session->statusCodeEquals(200);
      $this->assertTermAccess($published_terms[$delta], 'create', TRUE);

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->assertTermAccess($published_terms[$delta], 'select', FALSE, "The 'select terms in {$this->vocabularies[$delta]->id()}' OR 'select any term' OR 'administer taxonomy' permission is required.");
      $this->assertTermAccess($unpublished_terms[$delta], 'select', FALSE, "The 'select unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'select any unpublished term' OR 'administer taxonomy' permission is required.");
    }

    // Test the per vocabulary 'edit terms in' permission.
    $account_update_first_vocabulary = $this->drupalCreateUser(['edit terms in ' . $this->vocabularies[0]->id()]);
    $this->drupalLogin($account_update_first_vocabulary);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertTermAccess($published_terms[$delta], 'view label', FALSE, "The 'view term names in {$this->vocabularies[$delta]->id()}' OR 'view any term name' OR 'administer taxonomy' permission is required.");
      $this->assertTermAccess($unpublished_terms[$delta], 'view label', FALSE, "The 'view unpublished term names in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term name' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'view', FALSE, "The 'view terms in {$this->vocabularies[$delta]->id()}' OR 'view any term' OR 'administer taxonomy' permission is required.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'view', FALSE, "The 'view unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/add');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'create', FALSE, "The following permissions are required: 'create terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/edit');
      if ($delta === 0) {
        $assert_session->statusCodeEquals(200);
        $this->assertSession()->pageTextNotContains('Delete');
        $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.delete_form', ['taxonomy_term' => $published_terms[$delta]->id()])->toString());
        $this->assertTermAccess($published_terms[$delta], 'update', TRUE);
      }
      else {
        $assert_session->statusCodeEquals(403);
        $this->assertTermAccess($published_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      }
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/edit');
      if ($delta === 0) {
        $assert_session->statusCodeEquals(200);
        $this->assertSession()->pageTextNotContains('Delete');
        $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.delete_form', ['taxonomy_term' => $published_terms[$delta]->id()])->toString());
        $this->assertTermAccess($unpublished_terms[$delta], 'update', TRUE);
      }
      else {
        $assert_session->statusCodeEquals(403);
        $this->assertTermAccess($unpublished_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      }

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->assertTermAccess($published_terms[$delta], 'select', FALSE, "The 'select terms in {$this->vocabularies[$delta]->id()}' OR 'select any term' OR 'administer taxonomy' permission is required.");
      $this->assertTermAccess($unpublished_terms[$delta], 'select', FALSE, "The 'select unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'select any unpublished term' OR 'administer taxonomy' permission is required.");
    }

    // Test the 'update any term' permission.
    $account_update_any = $this->drupalCreateUser(['update any term']);
    $this->drupalLogin($account_update_any);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertTermAccess($published_terms[$delta], 'view label', FALSE, "The 'view term names in {$this->vocabularies[$delta]->id()}' OR 'view any term name' OR 'administer taxonomy' permission is required.");
      $this->assertTermAccess($unpublished_terms[$delta], 'view label', FALSE, "The 'view unpublished term names in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term name' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'view', FALSE, "The 'view terms in {$this->vocabularies[$delta]->id()}' OR 'view any term' OR 'administer taxonomy' permission is required.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'view', FALSE, "The 'view unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/add');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'create', FALSE, "The following permissions are required: 'create terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(200);
      $this->assertSession()->pageTextNotContains('Delete');
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.delete_form', ['taxonomy_term' => $published_terms[$delta]->id()])->toString());
      $this->assertTermAccess($published_terms[$delta], 'update', TRUE);
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(200);
      $this->assertSession()->pageTextNotContains('Delete');
      $this->assertNoLinkByEndOfHref(Url::fromRoute('entity.taxonomy_term.delete_form', ['taxonomy_term' => $published_terms[$delta]->id()])->toString());
      $this->assertTermAccess($unpublished_terms[$delta], 'update', TRUE);

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->assertTermAccess($published_terms[$delta], 'select', FALSE, "The 'select terms in {$this->vocabularies[$delta]->id()}' OR 'select any term' OR 'administer taxonomy' permission is required.");
      $this->assertTermAccess($unpublished_terms[$delta], 'select', FALSE, "The 'select unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'select any unpublished term' OR 'administer taxonomy' permission is required.");
    }

    // Test the per vocabulary 'delete terms in' permission.
    $account_delete_first_vocabulary = $this->drupalCreateUser(['delete terms in ' . $this->vocabularies[0]->id()]);
    $this->drupalLogin($account_delete_first_vocabulary);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertTermAccess($published_terms[$delta], 'view label', FALSE, "The 'view term names in {$this->vocabularies[$delta]->id()}' OR 'view any term name' OR 'administer taxonomy' permission is required.");
      $this->assertTermAccess($unpublished_terms[$delta], 'view label', FALSE, "The 'view unpublished term names in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term name' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'view', FALSE, "The 'view terms in {$this->vocabularies[$delta]->id()}' OR 'view any term' OR 'administer taxonomy' permission is required.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'view', FALSE, "The 'view unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/add');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'create', FALSE, "The following permissions are required: 'create terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/delete');
      if ($delta === 0) {
        $assert_session->statusCodeEquals(200);
        $this->assertTermAccess($published_terms[$delta], 'delete', TRUE);
      }
      else {
        $assert_session->statusCodeEquals(403);
        $this->assertTermAccess($published_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      }
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/delete');
      if ($delta === 0) {
        $assert_session->statusCodeEquals(200);
        $this->assertTermAccess($unpublished_terms[$delta], 'delete', TRUE);
      }
      else {
        $assert_session->statusCodeEquals(403);
        $this->assertTermAccess($unpublished_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      }

      $this->assertTermAccess($published_terms[$delta], 'select', FALSE, "The 'select terms in {$this->vocabularies[$delta]->id()}' OR 'select any term' OR 'administer taxonomy' permission is required.");
      $this->assertTermAccess($unpublished_terms[$delta], 'select', FALSE, "The 'select unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'select any unpublished term' OR 'administer taxonomy' permission is required.");
    }

    // Test the 'delete any term' permission.
    $account_delete_any = $this->drupalCreateUser(['delete any term']);
    $this->drupalLogin($account_delete_any);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertTermAccess($published_terms[$delta], 'view label', FALSE, "The 'view term names in {$this->vocabularies[$delta]->id()}' OR 'view any term name' OR 'administer taxonomy' permission is required.");
      $this->assertTermAccess($unpublished_terms[$delta], 'view label', FALSE, "The 'view unpublished term names in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term name' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'view', FALSE, "The 'view terms in {$this->vocabularies[$delta]->id()}' OR 'view any term' OR 'administer taxonomy' permission is required.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'view', FALSE, "The 'view unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/add');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'create', FALSE, "The following permissions are required: 'create terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(200);
      $this->assertTermAccess($published_terms[$delta], 'delete', TRUE);
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(200);
      $this->assertTermAccess($unpublished_terms[$delta], 'delete', TRUE);

      $this->assertTermAccess($published_terms[$delta], 'select', FALSE, "The 'select terms in {$this->vocabularies[$delta]->id()}' OR 'select any term' OR 'administer taxonomy' permission is required.");
      $this->assertTermAccess($unpublished_terms[$delta], 'select', FALSE, "The 'select unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'select any unpublished term' OR 'administer taxonomy' permission is required.");
    }

    // Test the per vocabulary 'view terms in' permission.
    $account_view_first_vocabulary = $this->drupalCreateUser(['view terms in ' . $this->vocabularies[0]->id()]);
    $this->drupalLogin($account_view_first_vocabulary);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertTermAccess($published_terms[$delta], 'view label', FALSE, "The 'view term names in {$this->vocabularies[$delta]->id()}' OR 'view any term name' OR 'administer taxonomy' permission is required.");
      $this->assertTermAccess($unpublished_terms[$delta], 'view label', FALSE, "The 'view unpublished term names in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term name' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id());
      if ($delta === 0) {
        $assert_session->statusCodeEquals(200);
        $this->assertTermAccess($published_terms[$delta], 'view', TRUE);
      }
      else {
        $assert_session->statusCodeEquals(403);
        $this->assertTermAccess($published_terms[$delta], 'view', FALSE, "The 'view terms in {$this->vocabularies[$delta]->id()}' OR 'view any term' OR 'administer taxonomy' permission is required.");
      }
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'view', FALSE, "The 'view unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/add');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'create', FALSE, "The following permissions are required: 'create terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->assertTermAccess($published_terms[$delta], 'select', FALSE, "The 'select terms in {$this->vocabularies[$delta]->id()}' OR 'select any term' OR 'administer taxonomy' permission is required.");
      $this->assertTermAccess($unpublished_terms[$delta], 'select', FALSE, "The 'select unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'select any unpublished term' OR 'administer taxonomy' permission is required.");
    }

    // Test the 'view any term' permission.
    $account_view_any_published = $this->drupalCreateUser(['view any term']);
    $this->drupalLogin($account_view_any_published);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertTermAccess($published_terms[$delta], 'view label', FALSE, "The 'view term names in {$this->vocabularies[$delta]->id()}' OR 'view any term name' OR 'administer taxonomy' permission is required.");
      $this->assertTermAccess($unpublished_terms[$delta], 'view label', FALSE, "The 'view unpublished term names in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term name' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id());
      $assert_session->statusCodeEquals(200);
      $this->assertTermAccess($published_terms[$delta], 'view', TRUE);

      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'view', FALSE, "The 'view unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/add');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'create', FALSE, "The following permissions are required: 'create terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->assertTermAccess($published_terms[$delta], 'select', FALSE, "The 'select terms in {$this->vocabularies[$delta]->id()}' OR 'select any term' OR 'administer taxonomy' permission is required.");
      $this->assertTermAccess($unpublished_terms[$delta], 'select', FALSE, "The 'select unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'select any unpublished term' OR 'administer taxonomy' permission is required.");
    }

    // Test the per vocabulary 'view unpublished terms in' permission.
    $account_view_unpublished_first_vocabulary = $this->drupalCreateUser(['view unpublished terms in ' . $this->vocabularies[0]->id()]);
    $this->drupalLogin($account_view_unpublished_first_vocabulary);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertTermAccess($published_terms[$delta], 'view label', FALSE, "The 'view term names in {$this->vocabularies[$delta]->id()}' OR 'view any term name' OR 'administer taxonomy' permission is required.");
      $this->assertTermAccess($unpublished_terms[$delta], 'view label', FALSE, "The 'view unpublished term names in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term name' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'view', FALSE, "The 'view terms in {$this->vocabularies[$delta]->id()}' OR 'view any term' OR 'administer taxonomy' permission is required.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id());
      if ($delta === 0) {
        $assert_session->statusCodeEquals(200);
        $this->assertTermAccess($unpublished_terms[$delta], 'view', TRUE);
      }
      else {
        $assert_session->statusCodeEquals(403);
        $this->assertTermAccess($unpublished_terms[$delta], 'view', FALSE, "The 'view unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term' OR 'administer taxonomy' permission is required.");
      }

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/add');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'create', FALSE, "The following permissions are required: 'create terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->assertTermAccess($published_terms[$delta], 'select', FALSE, "The 'select terms in {$this->vocabularies[$delta]->id()}' OR 'select any term' OR 'administer taxonomy' permission is required.");
      $this->assertTermAccess($unpublished_terms[$delta], 'select', FALSE, "The 'select unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'select any unpublished term' OR 'administer taxonomy' permission is required.");
    }

    // Test the 'view any unpublished terms' permission.
    $account_view_any_unpublished = $this->drupalCreateUser(['view any unpublished term']);
    $this->drupalLogin($account_view_any_unpublished);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertTermAccess($published_terms[$delta], 'view label', FALSE, "The 'view term names in {$this->vocabularies[$delta]->id()}' OR 'view any term name' OR 'administer taxonomy' permission is required.");
      $this->assertTermAccess($unpublished_terms[$delta], 'view label', FALSE, "The 'view unpublished term names in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term name' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'view', FALSE, "The 'view terms in {$this->vocabularies[$delta]->id()}' OR 'view any term' OR 'administer taxonomy' permission is required.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id());
      $assert_session->statusCodeEquals(200);
      $this->assertTermAccess($unpublished_terms[$delta], 'view', TRUE);

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/add');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'create', FALSE, "The following permissions are required: 'create terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->assertTermAccess($published_terms[$delta], 'select', FALSE, "The 'select terms in {$this->vocabularies[$delta]->id()}' OR 'select any term' OR 'administer taxonomy' permission is required.");
      $this->assertTermAccess($unpublished_terms[$delta], 'select', FALSE, "The 'select unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'select any unpublished term' OR 'administer taxonomy' permission is required.");
    }

    // Test the per vocabulary 'view term names in' permission.
    $account_view_name_first_vocabulary = $this->drupalCreateUser(['view term names in ' . $this->vocabularies[0]->id()]);
    $this->drupalLogin($account_view_name_first_vocabulary);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      if ($delta === 0) {
        $this->assertTermAccess($published_terms[$delta], 'view label', TRUE);
      }
      else {
        $this->assertTermAccess($published_terms[$delta], 'view label', FALSE, "The 'view term names in {$this->vocabularies[$delta]->id()}' OR 'view any term name' OR 'administer taxonomy' permission is required.");
      }
      $this->assertTermAccess($unpublished_terms[$delta], 'view label', FALSE, "The 'view unpublished term names in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term name' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'view', FALSE, "The 'view terms in {$this->vocabularies[$delta]->id()}' OR 'view any term' OR 'administer taxonomy' permission is required.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'view', FALSE, "The 'view unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/add');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'create', FALSE, "The following permissions are required: 'create terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->assertTermAccess($published_terms[$delta], 'select', FALSE, "The 'select terms in {$this->vocabularies[$delta]->id()}' OR 'select any term' OR 'administer taxonomy' permission is required.");
      $this->assertTermAccess($unpublished_terms[$delta], 'select', FALSE, "The 'select unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'select any unpublished term' OR 'administer taxonomy' permission is required.");
    }

    // Test the 'view any term name' permission.
    $account_view_any_name_published = $this->drupalCreateUser(['view any term name']);
    $this->drupalLogin($account_view_any_name_published);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertTermAccess($published_terms[$delta], 'view label', TRUE);
      $this->assertTermAccess($unpublished_terms[$delta], 'view label', FALSE, "The 'view unpublished term names in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term name' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'view', FALSE, "The 'view terms in {$this->vocabularies[$delta]->id()}' OR 'view any term' OR 'administer taxonomy' permission is required.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'view', FALSE, "The 'view unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/add');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'create', FALSE, "The following permissions are required: 'create terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->assertTermAccess($published_terms[$delta], 'select', FALSE, "The 'select terms in {$this->vocabularies[$delta]->id()}' OR 'select any term' OR 'administer taxonomy' permission is required.");
      $this->assertTermAccess($unpublished_terms[$delta], 'select', FALSE, "The 'select unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'select any unpublished term' OR 'administer taxonomy' permission is required.");
    }

    // Test the per vocabulary 'view unpublished term names in' permission.
    $account_view_unpublished_name_first_vocabulary = $this->drupalCreateUser(['view unpublished term names in ' . $this->vocabularies[0]->id()]);
    $this->drupalLogin($account_view_unpublished_name_first_vocabulary);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertTermAccess($published_terms[$delta], 'view label', FALSE, "The 'view term names in {$this->vocabularies[$delta]->id()}' OR 'view any term name' OR 'administer taxonomy' permission is required.");
      if ($delta === 0) {
        $this->assertTermAccess($unpublished_terms[$delta], 'view label', TRUE);
      }
      else {
        $this->assertTermAccess($unpublished_terms[$delta], 'view label', FALSE, "The 'view unpublished term names in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term name' OR 'administer taxonomy' permission is required.");
      }

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'view', FALSE, "The 'view terms in {$this->vocabularies[$delta]->id()}' OR 'view any term' OR 'administer taxonomy' permission is required.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'view', FALSE, "The 'view unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/add');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'create', FALSE, "The following permissions are required: 'create terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->assertTermAccess($published_terms[$delta], 'select', FALSE, "The 'select terms in {$this->vocabularies[$delta]->id()}' OR 'select any term' OR 'administer taxonomy' permission is required.");
      $this->assertTermAccess($unpublished_terms[$delta], 'select', FALSE, "The 'select unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'select any unpublished term' OR 'administer taxonomy' permission is required.");
    }

    // Test the 'view any unpublished term name' permission.
    $account_view_any_name_unpublished = $this->drupalCreateUser(['view any unpublished term name']);
    $this->drupalLogin($account_view_any_name_unpublished);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertTermAccess($published_terms[$delta], 'view label', FALSE, "The 'view term names in {$this->vocabularies[$delta]->id()}' OR 'view any term name' OR 'administer taxonomy' permission is required.");
      $this->assertTermAccess($unpublished_terms[$delta], 'view label', TRUE);

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'view', FALSE, "The 'view terms in {$this->vocabularies[$delta]->id()}' OR 'view any term' OR 'administer taxonomy' permission is required.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'view', FALSE, "The 'view unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/add');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'create', FALSE, "The following permissions are required: 'create terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->assertTermAccess($published_terms[$delta], 'select', FALSE, "The 'select terms in {$this->vocabularies[$delta]->id()}' OR 'select any term' OR 'administer taxonomy' permission is required.");
      $this->assertTermAccess($unpublished_terms[$delta], 'select', FALSE, "The 'select unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'select any unpublished term' OR 'administer taxonomy' permission is required.");
    }

    // Test the per vocabulary 'select terms in' permission.
    $account_select_first_vocabulary = $this->drupalCreateUser(['select terms in ' . $this->vocabularies[0]->id()]);
    $this->drupalLogin($account_select_first_vocabulary);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertTermAccess($published_terms[$delta], 'view label', FALSE, "The 'view term names in {$this->vocabularies[$delta]->id()}' OR 'view any term name' OR 'administer taxonomy' permission is required.");
      $this->assertTermAccess($unpublished_terms[$delta], 'view label', FALSE, "The 'view unpublished term names in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term name' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'view', FALSE, "The 'view terms in {$this->vocabularies[$delta]->id()}' OR 'view any term' OR 'administer taxonomy' permission is required.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'view', FALSE, "The 'view unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/add');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'create', FALSE, "The following permissions are required: 'create terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      if ($delta === 0) {
        $this->assertTermAccess($published_terms[$delta], 'select', TRUE);
      }
      else {
        $this->assertTermAccess($published_terms[$delta], 'select', FALSE, "The 'select terms in {$this->vocabularies[$delta]->id()}' OR 'select any term' OR 'administer taxonomy' permission is required.");
      }
      $this->assertTermAccess($unpublished_terms[$delta], 'select', FALSE, "The 'select unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'select any unpublished term' OR 'administer taxonomy' permission is required.");
    }

    // Test the 'select any term' permission.
    $account_select_any_published = $this->drupalCreateUser(['select any term']);
    $this->drupalLogin($account_select_any_published);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertTermAccess($published_terms[$delta], 'view label', FALSE, "The 'view term names in {$this->vocabularies[$delta]->id()}' OR 'view any term name' OR 'administer taxonomy' permission is required.");
      $this->assertTermAccess($unpublished_terms[$delta], 'view label', FALSE, "The 'view unpublished term names in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term name' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'view', FALSE, "The 'view terms in {$this->vocabularies[$delta]->id()}' OR 'view any term' OR 'administer taxonomy' permission is required.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'view', FALSE, "The 'view unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/add');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'create', FALSE, "The following permissions are required: 'create terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->assertTermAccess($published_terms[$delta], 'select', TRUE);
      $this->assertTermAccess($unpublished_terms[$delta], 'select', FALSE, "The 'select unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'select any unpublished term' OR 'administer taxonomy' permission is required.");
    }

    // Test the per vocabulary 'select unpublished terms in' permission.
    $account_select_unpublished_first_vocabulary = $this->drupalCreateUser(['select unpublished terms in ' . $this->vocabularies[0]->id()]);
    $this->drupalLogin($account_select_unpublished_first_vocabulary);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertTermAccess($published_terms[$delta], 'view label', FALSE, "The 'view term names in {$this->vocabularies[$delta]->id()}' OR 'view any term name' OR 'administer taxonomy' permission is required.");
      $this->assertTermAccess($unpublished_terms[$delta], 'view label', FALSE, "The 'view unpublished term names in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term name' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'view', FALSE, "The 'view terms in {$this->vocabularies[$delta]->id()}' OR 'view any term' OR 'administer taxonomy' permission is required.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'view', FALSE, "The 'view unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/add');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'create', FALSE, "The following permissions are required: 'create terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->assertTermAccess($published_terms[$delta], 'select', FALSE, "The 'select terms in {$this->vocabularies[$delta]->id()}' OR 'select any term' OR 'administer taxonomy' permission is required.");
      if ($delta === 0) {
        $this->assertTermAccess($unpublished_terms[$delta], 'select', TRUE);
      }
      else {
        $this->assertTermAccess($unpublished_terms[$delta], 'select', FALSE, "The 'select unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'select any unpublished term' OR 'administer taxonomy' permission is required.");
      }
    }

    // Test the 'select any unpublished terms' permission.
    $account_select_any_unpublished = $this->drupalCreateUser(['select any unpublished term']);
    $this->drupalLogin($account_select_any_unpublished);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->assertTermAccess($published_terms[$delta], 'view label', FALSE, "The 'view term names in {$this->vocabularies[$delta]->id()}' OR 'view any term name' OR 'administer taxonomy' permission is required.");
      $this->assertTermAccess($unpublished_terms[$delta], 'view label', FALSE, "The 'view unpublished term names in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term name' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'view', FALSE, "The 'view terms in {$this->vocabularies[$delta]->id()}' OR 'view any term' OR 'administer taxonomy' permission is required.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id());
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'view', FALSE, "The 'view unpublished terms in {$this->vocabularies[$delta]->id()}' OR 'view any unpublished term' OR 'administer taxonomy' permission is required.");

      $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/add');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'create', FALSE, "The following permissions are required: 'create terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/edit');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'update', FALSE, "The following permissions are required: 'edit terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($published_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id() . '/delete');
      $assert_session->statusCodeEquals(403);
      $this->assertTermAccess($unpublished_terms[$delta], 'delete', FALSE, "The following permissions are required: 'delete terms in {$this->vocabularies[$delta]->id()}' OR 'administer taxonomy'.");

      $this->assertTermAccess($published_terms[$delta], 'select', FALSE, "The 'select terms in {$this->vocabularies[$delta]->id()}' OR 'select any term' OR 'administer taxonomy' permission is required.");
      $this->assertTermAccess($unpublished_terms[$delta], 'select', TRUE);
    }

    // Install Views module and repeat the view related checks.
    $this->installModules(['views']);

    $this->drupalLogin($account_view_first_vocabulary);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id());
      if ($delta === 0) {
        $assert_session->statusCodeEquals(200);
      }
      else {
        // @todo Change this assertion to expect a 403 status code when
        //   https://www.drupal.org/project/drupal/issues/2983070 is fixed.
        $assert_session->statusCodeEquals(404);
      }

      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id());
      // @todo Change this assertion to expect a 403 status code when
      //   https://www.drupal.org/project/drupal/issues/2983070 is fixed.
      $assert_session->statusCodeEquals(404);
    }

    $this->drupalLogin($account_view_any_published);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id());
      $assert_session->statusCodeEquals(200);

      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id());
      // @todo Change this assertion to expect a 403 status code when
      //   https://www.drupal.org/project/drupal/issues/2983070 is fixed.
      $assert_session->statusCodeEquals(404);
    }

    $this->drupalLogin($account_view_unpublished_first_vocabulary);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id());
      // @todo Change this assertion to expect a 403 status code when
      //   https://www.drupal.org/project/drupal/issues/2983070 is fixed.
      $assert_session->statusCodeEquals(404);

      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id());
      if ($delta === 0) {
        $assert_session->statusCodeEquals(200);
      }
      else {
        // @todo Change this assertion to expect a 403 status code when
        //   https://www.drupal.org/project/drupal/issues/2983070 is fixed.
        $assert_session->statusCodeEquals(404);
      }
    }

    $this->drupalLogin($account_view_any_unpublished);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id());
      // @todo Change this assertion to expect a 403 status code when
      //   https://www.drupal.org/project/drupal/issues/2983070 is fixed.
      $assert_session->statusCodeEquals(404);

      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id());
      $assert_session->statusCodeEquals(200);
    }

    $this->drupalLogin($account_access_content);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id());
      // @todo Change this assertion to expect a 403 status code when
      //   https://www.drupal.org/project/drupal/issues/2983070 is fixed.
      $assert_session->statusCodeEquals(404);

      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id());
      // @todo Change this assertion to expect a 403 status code when
      //   https://www.drupal.org/project/drupal/issues/2983070 is fixed.
      $assert_session->statusCodeEquals(404);
    }

    $this->drupalLogin($account_administer);
    foreach ($this->vocabularies as $delta => $vocabulary) {
      $this->drupalGet('taxonomy/term/' . $published_terms[$delta]->id());
      $assert_session->statusCodeEquals(200);
      $this->drupalGet('taxonomy/term/' . $unpublished_terms[$delta]->id());
      $assert_session->statusCodeEquals(200);
    }
  }

}
