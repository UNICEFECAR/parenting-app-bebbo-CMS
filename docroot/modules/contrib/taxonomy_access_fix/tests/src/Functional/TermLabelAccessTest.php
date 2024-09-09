<?php

namespace Drupal\Tests\taxonomy_access_fix\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Tests taxonomy term label access.
 *
 * @group taxonomy
 */
class TermLabelAccessTest extends BrowserTestBase {

  use TaxonomyTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['taxonomy_access_fix_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public function testTermLabelAccess() {
    // Add 2 vocabularies.
    $vocabulary1 = $this->createVocabulary();
    $vocabulary2 = $this->createVocabulary();
    // Add a published term and an unpublished term into both vocabulary 1 and
    // vocabulary 2.
    $term1_in_vocabulary1_published = $this->createTerm($vocabulary1, [
      'name' => $this->randomMachineName(),
      'status' => 1,
    ]);
    $term2_in_vocabulary1_unpublished = $this->createTerm($vocabulary1, [
      'name' => $this->randomMachineName(),
      'status' => 0,
    ]);
    $term3_in_vocabulary2_published = $this->createTerm($vocabulary2, [
      'name' => $this->randomMachineName(),
      'status' => 1,
    ]);
    $term4_in_vocabulary2_unpublished = $this->createTerm($vocabulary2, [
      'name' => $this->randomMachineName(),
      'status' => 0,
    ]);

    // The view is from the taxonomy_access_fix_test module which lists all
    // term names and a view link in a table including unpublished terms.
    $view_path = 'term-labels-test';
    $view_title = 'Term labels test';
    $vocabulary1_id = $vocabulary1->id();
    $vocabulary2_id = $vocabulary2->id();

    $assert_session = $this->assertSession();

    $term1_label = $term1_in_vocabulary1_published->label();
    $term1_path = $term1_in_vocabulary1_published->toUrl()->toString();
    $term2_label = $term2_in_vocabulary1_unpublished->label();
    $term2_path = $term2_in_vocabulary1_unpublished->toUrl()->toString();
    $term3_label = $term3_in_vocabulary2_published->label();
    $term3_path = $term3_in_vocabulary2_published->toUrl()->toString();
    $term4_label = $term4_in_vocabulary2_unpublished->label();
    $term4_path = $term4_in_vocabulary2_unpublished->toUrl()->toString();

    // 1. Test anonymous access.
    $this->drupalGet($view_path);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains($view_title);
    $assert_session->pageTextNotContains($term1_label);
    $assert_session->pageTextNotContains($term2_label);
    $assert_session->pageTextNotContains($term3_label);
    $assert_session->pageTextNotContains($term4_label);
    $this->drupalGet($term1_path);
    $assert_session->statusCodeEquals(404);
    $this->drupalGet($term2_path);
    $assert_session->statusCodeEquals(404);
    $this->drupalGet($term3_path);
    $assert_session->statusCodeEquals(404);
    $this->drupalGet($term4_path);
    $assert_session->statusCodeEquals(404);

    // Test user with permission to view term names in vocabulary 1.
    $this->drupalLogin($this->createUser(["view term names in $vocabulary1_id"]));
    $this->drupalGet($view_path);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains($view_title);
    $assert_session->pageTextContains($term1_label);
    // The term 2 in vocabulary 1 is unpublished.
    $assert_session->pageTextNotContains($term2_label);
    $assert_session->pageTextNotContains($term3_label);
    $assert_session->pageTextNotContains($term4_label);
    $this->drupalGet($term1_path);
    $assert_session->statusCodeEquals(404);
    $this->drupalGet($term2_path);
    $assert_session->statusCodeEquals(404);
    $this->drupalGet($term3_path);
    $assert_session->statusCodeEquals(404);
    $this->drupalGet($term4_path);
    $assert_session->statusCodeEquals(404);
    $this->drupalLogout();

    // Test user with `view any term name` permission.
    $this->drupalLogin($this->createUser(['view any term name']));
    $this->drupalGet($view_path);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains($view_title);
    $assert_session->pageTextContains($term1_label);
    $assert_session->pageTextNotContains($term2_label);
    $assert_session->pageTextContains($term3_label);
    $assert_session->pageTextNotContains($term4_label);
    $this->drupalGet($term1_path);
    $assert_session->statusCodeEquals(404);
    $this->drupalGet($term2_path);
    $assert_session->statusCodeEquals(404);
    $this->drupalGet($term3_path);
    $assert_session->statusCodeEquals(404);
    $this->drupalGet($term4_path);
    $assert_session->statusCodeEquals(404);
    $this->drupalLogout();

    // Test terms in vocabulary 1 term names viewable and terms in vocabulary 2
    // terms viewable.
    $this->drupalLogin($this->createUser([
      "view term names in $vocabulary1_id",
      "view terms in $vocabulary2_id",
    ]));
    $this->drupalGet($view_path);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains($view_title);
    $assert_session->pageTextContains($term1_label);
    // This term 2 in vocabulary 1 is unpublished.
    $assert_session->pageTextNotContains($term2_label);
    $assert_session->pageTextNotContains($term3_label);
    $assert_session->pageTextNotContains($term4_label);
    $this->drupalGet($term1_path);
    $assert_session->statusCodeEquals(404);
    $this->drupalGet($term2_path);
    $assert_session->statusCodeEquals(404);
    $this->drupalGet($term3_path);
    $assert_session->statusCodeEquals(200);
    $this->drupalGet($term4_path);
    // This term 4 in vocabulary 2 is unpublished.
    $assert_session->statusCodeEquals(404);
    $this->drupalLogout();

    // Test unpublished term names in vocabulary 1 and terms in vocabulary 2.
    $this->drupalLogin($this->createUser([
      "view unpublished term names in $vocabulary1_id",
      "view terms in $vocabulary2_id",
    ]));
    $this->drupalGet($view_path);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains($view_title);
    $assert_session->pageTextNotContains($term1_label);
    $assert_session->pageTextContains($term2_label);
    // We have permission to view the term, but not the term label.
    $assert_session->pageTextNotContains($term3_label);
    $assert_session->pageTextNotContains($term4_label);
    $this->drupalGet($term1_path);
    $assert_session->statusCodeEquals(404);
    $this->drupalGet($term2_path);
    $assert_session->statusCodeEquals(404);
    $this->drupalGet($term3_path);
    $assert_session->statusCodeEquals(200);
    $this->drupalGet($term4_path);
    $assert_session->statusCodeEquals(404);
    $this->drupalLogout();

    // Test term names in vocabulary 1 and unpublished terms in vocabulary 2.
    $this->drupalLogin($this->createUser([
      "view term names in $vocabulary1_id",
      "view unpublished terms in $vocabulary2_id",
    ]));
    $this->drupalGet($view_path);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains($view_title);
    $assert_session->pageTextContains($term1_label);
    $assert_session->pageTextNotContains($term2_label);
    $assert_session->pageTextNotContains($term3_label);
    // We have permission to view the term, but not the term label.
    $assert_session->pageTextNotContains($term4_label);
    $this->drupalGet($term1_path);
    $assert_session->statusCodeEquals(404);
    $this->drupalGet($term2_path);
    $assert_session->statusCodeEquals(404);
    $this->drupalGet($term3_path);
    $assert_session->statusCodeEquals(404);
    $this->drupalGet($term4_path);
    $assert_session->statusCodeEquals(200);
    $this->drupalLogout();

    // Test any unpublished term name.
    $this->drupalLogin($this->createUser([
      "view any unpublished term name",
    ]));
    $this->drupalGet($view_path);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains($view_title);
    $assert_session->pageTextNotContains($term1_label);
    $assert_session->pageTextContains($term2_label);
    $assert_session->pageTextNotContains($term3_label);
    $assert_session->pageTextContains($term4_label);
    $this->drupalGet($term1_path);
    $assert_session->statusCodeEquals(404);
    $this->drupalGet($term2_path);
    $assert_session->statusCodeEquals(404);
    $this->drupalGet($term3_path);
    $assert_session->statusCodeEquals(404);
    $this->drupalGet($term4_path);
    $assert_session->statusCodeEquals(404);
    $this->drupalLogout();

    // Tests user with `administer taxonomy` permission.
    $this->drupalLogin($this->createUser([
      'administer taxonomy',
    ]));
    $this->drupalGet($view_path);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains($view_title);
    $assert_session->pageTextContains($term1_label);
    $assert_session->pageTextContains($term2_label);
    $assert_session->pageTextContains($term3_label);
    $assert_session->pageTextContains($term4_label);
    $this->drupalGet($term1_path);
    $assert_session->statusCodeEquals(200);
    $this->drupalGet($term2_path);
    $assert_session->statusCodeEquals(200);
    $this->drupalGet($term3_path);
    $assert_session->statusCodeEquals(200);
    $this->drupalGet($term4_path);
    $assert_session->statusCodeEquals(200);
  }

}
