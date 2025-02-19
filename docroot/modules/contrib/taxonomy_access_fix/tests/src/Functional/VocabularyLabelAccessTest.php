<?php

namespace Drupal\Tests\taxonomy_access_fix\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\Tests\taxonomy_access_fix\Traits\TaxonomyAccessFixTestTrait;

/**
 * Tests taxonomy vocabulary label access.
 *
 * @group taxonomy
 */
class VocabularyLabelAccessTest extends BrowserTestBase {

  use TaxonomyTestTrait;
  use TaxonomyAccessFixTestTrait;

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
  public function testVocabularyLabelAccess() {
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

    // The view is from taxonomy_access_fix_test module which lists all term and
    // vocabulary names (both linked) in a table including unpublished terms.
    $view_path = 'vocabulary-labels-test';
    $view_title = 'vocabulary_labels_test';
    $vocabulary1_id = $vocabulary1->id();
    $vocabulary2_id = $vocabulary2->id();

    $assert_session = $this->assertSession();

    $vocabulary1_label = $vocabulary1->label();
    $vocabulary1_path = $vocabulary1->toUrl()->toString();
    $vocabulary2_label = $vocabulary2->label();
    $vocabulary2_path = $vocabulary2->toUrl()->toString();

    // Test anonymous access.
    $this->drupalGet($view_path);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains($view_title);
    $assert_session->pageTextNotContains($vocabulary1_label);
    $assert_session->pageTextNotContains($vocabulary2_label);
    $this->assertNoLinkByEndOfHref($vocabulary1_path);
    $this->assertNoLinkByEndOfHref($vocabulary2_path);
    $this->drupalGet($vocabulary1_path);
    $assert_session->statusCodeEquals(403);
    $this->drupalGet($vocabulary2_path);
    $assert_session->statusCodeEquals(403);

    // Test view vocabulary name of vocabulary 1.
    $this->drupalLogin($this->createUser(["view vocabulary name of $vocabulary1_id"]));
    $this->drupalGet($view_path);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains($view_title);
    $assert_session->pageTextContains($vocabulary1_label);
    $assert_session->pageTextNotContains($vocabulary2_label);
    $this->drupalGet($vocabulary1_path);
    $assert_session->statusCodeEquals(403);
    $this->drupalGet($vocabulary2_path);
    $assert_session->statusCodeEquals(403);
    $this->drupalLogout();

    // Test view any vocabulary name.
    $this->drupalLogin($this->createUser(['view any vocabulary name']));
    $this->drupalGet($view_path);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains($view_title);
    $assert_session->pageTextContains($vocabulary1_label);
    $assert_session->pageTextContains($vocabulary2_label);
    $this->drupalGet($vocabulary1_path);
    $assert_session->statusCodeEquals(403);
    $this->drupalGet($vocabulary2_path);
    $assert_session->statusCodeEquals(403);
    $this->drupalLogout();

    // Test view vocabulary name of vocabulary 1 and edit terms in vocabulary 2.
    $this->drupalLogin($this->createUser([
      "view vocabulary name of $vocabulary1_id",
      "edit terms in $vocabulary2_id",
    ]));
    $this->drupalGet($view_path);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains($view_title);
    $assert_session->pageTextContains($vocabulary1_label);
    $assert_session->pageTextNotContains($vocabulary2_label);
    $this->drupalGet($vocabulary1_path);
    $assert_session->statusCodeEquals(403);
    $this->drupalGet($vocabulary2_path);
    $assert_session->statusCodeEquals(403);
    $this->drupalLogout();

    // Test view vocabulary name of vocabulary 1 and edit terms in vocabulary 2
    // and access taxonomy overview.
    $this->drupalLogin($this->createUser([
      "view vocabulary name of $vocabulary1_id",
      "edit terms in $vocabulary2_id",
      "access taxonomy overview",
    ]));
    $this->drupalGet($view_path);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains($view_title);
    $assert_session->pageTextContains($vocabulary1_label);
    $assert_session->pageTextContains($vocabulary2_label);
    $this->drupalGet($vocabulary1_path);
    $assert_session->statusCodeEquals(403);
    $this->drupalGet($vocabulary2_path);
    $assert_session->statusCodeEquals(403);
    $this->drupalLogout();

    // Test view vocabulary name of vocabulary 2 and view terms in vocabulary 1.
    $this->drupalLogin($this->createUser([
      "view vocabulary name of $vocabulary2_id",
      "view terms in $vocabulary1_id",
    ]));
    $this->drupalGet($view_path);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains($view_title);
    $assert_session->pageTextNotContains($vocabulary1_label);
    $assert_session->pageTextContains($vocabulary2_label);
    $this->drupalGet($vocabulary1_path);
    $assert_session->statusCodeEquals(403);
    $this->drupalGet($vocabulary2_path);
    $assert_session->statusCodeEquals(403);
    $this->drupalLogout();

    // Tests user with `administer taxonomy` permission.
    $this->drupalLogin($this->createUser([
      'administer taxonomy',
    ]));
    $this->drupalGet($view_path);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains($view_title);
    $assert_session->pageTextContains($vocabulary1_label);
    $assert_session->pageTextContains($vocabulary2_label);
    $this->assertLinkByEndOfHref($vocabulary1_path);
    $this->assertLinkByEndOfHref($vocabulary2_path);
    $this->drupalGet($vocabulary1_path);
    $assert_session->statusCodeEquals(200);
    $this->drupalGet($vocabulary2_path);
    $assert_session->statusCodeEquals(200);
    $this->drupalLogout();
  }

}
