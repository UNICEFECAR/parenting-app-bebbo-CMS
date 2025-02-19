<?php

namespace Drupal\Tests\taxonomy_access_fix\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\JSWebAssert;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Tests the output of entity reference autocomplete widgets.
 *
 * @group taxonomy
 */
class TermSelectAccessTest extends WebDriverTestBase {

  use ContentTypeCreationTrait;
  use EntityReferenceFieldCreationTrait;
  use NodeCreationTrait;
  use TaxonomyTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'field_ui', 'taxonomy_access_fix'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The bundle names of the vocabularies used.
   *
   * @var string[]
   */
  protected $bundles;

  /**
   * The vocabularies used.
   *
   * @var \Drupal\taxonomy\VocabularyInterface[]
   */
  protected $vocabularies;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->vocabularies = [
      $this->createVocabulary(),
      $this->createVocabulary(),
    ];

    foreach ($this->vocabularies as $delta => $vocabulary) {
      $published_terms[$delta] = $this->createTerm($vocabulary, [
        'name' => 'Status-1 term ' . $delta,
        'status' => 1,
      ]);
      $published_terms[$delta]->save();
      $unpublished_terms[$delta] = $this->createTerm($vocabulary, [
        'name' => 'Status-0 term ' . $delta,
        'status' => 0,
      ]);
      $unpublished_terms[$delta]->save();

      $this->bundles[$delta] = $vocabulary->id();
    }

    // Create a Content type and two test nodes.
    $this->createContentType(['type' => 'page']);

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');

    // Create an entity reference field and use the default 'CONTAINS' match
    // operator.
    $field_name = 'field_test';
    $this->createEntityReferenceField('node', 'page', $field_name, $field_name, 'taxonomy_term', 'default', [
      'target_bundles' => $this->bundles,
      'sort' => [
        'field' => 'name',
        'direction' => 'DESC',
      ],
    ]);
    $form_display = $display_repository->getFormDisplay('node', 'page');
    $form_display->setComponent($field_name, [
      'type' => 'entity_reference_autocomplete',
      'settings' => [
        'match_operator' => 'CONTAINS',
      ],
    ]);
    $form_display->save();
  }

  /**
   * Tests that the default autocomplete widget return the correct results.
   */
  public function testEntityReferenceAutocompleteWidget() {
    // Test administrative permission.
    $account_administer = $this->drupalCreateUser([
      'administer taxonomy',
      'create page content',
    ]);
    $this->drupalLogin($account_administer);

    $results = $this->doAutocomplete();
    $this->assertCount(4, $results);
    $this->assertSession()->pageTextContains('Status-1 term 0');
    $this->assertSession()->pageTextContains('Status-0 term 0');
    $this->assertSession()->pageTextContains('Status-1 term 1');
    $this->assertSession()->pageTextContains('Status-0 term 1');

    // Test permissions without access.
    foreach ([
      'access content',
      'create terms in ' . $this->vocabularies[0]->id(),
      'edit terms in ' . $this->vocabularies[0]->id(),
      'delete terms in ' . $this->vocabularies[0]->id(),
      'view terms in ' . $this->vocabularies[0]->id(),
      'view any term',
      'view unpublished terms in ' . $this->vocabularies[0]->id(),
      'view any unpublished term',
      'view term names in ' . $this->vocabularies[0]->id(),
      'view unpublished term names in ' . $this->vocabularies[0]->id(),
      'view any term name',
      'view any unpublished term name',
      'reorder terms in ' . $this->vocabularies[0]->id(),
    ] as $permission) {
      $this->assertEmptyAutocompleteWithPermission($permission);
    }

    // Test per-vocabulary select published terms permission.
    $account_administer = $this->drupalCreateUser([
      'select terms in ' . $this->vocabularies[0]->id(),
      'create page content',
    ]);
    $this->drupalLogin($account_administer);

    $results = $this->doAutocomplete();
    $this->assertCount(1, $results);
    $this->assertSession()->pageTextContains('Status-1 term 0');
    $this->assertSession()->pageTextNotContains('Status-0 term 0');
    $this->assertSession()->pageTextNotContains('Status-1 term 1');
    $this->assertSession()->pageTextNotContains('Status-0 term 1');

    // Test select any published term permission.
    $account_administer = $this->drupalCreateUser([
      'select any term',
      'create page content',
    ]);
    $this->drupalLogin($account_administer);

    $results = $this->doAutocomplete();
    $this->assertCount(2, $results);
    $this->assertSession()->pageTextContains('Status-1 term 0');
    $this->assertSession()->pageTextNotContains('Status-0 term 0');
    $this->assertSession()->pageTextContains('Status-1 term 1');
    $this->assertSession()->pageTextNotContains('Status-0 term 1');

    // Test per-vocabulary select unpublished terms permission.
    $account_administer = $this->drupalCreateUser([
      'select unpublished terms in ' . $this->vocabularies[0]->id(),
      'create page content',
    ]);
    $this->drupalLogin($account_administer);

    $results = $this->doAutocomplete();
    $this->assertCount(1, $results);
    $this->assertSession()->pageTextNotContains('Status-1 term 0');
    $this->assertSession()->pageTextContains('Status-0 term 0');
    $this->assertSession()->pageTextNotContains('Status-1 term 1');
    $this->assertSession()->pageTextNotContains('Status-0 term 1');

    // Test select any unpublished terms permission.
    $account_administer = $this->drupalCreateUser([
      'select any unpublished term',
      'create page content',
    ]);
    $this->drupalLogin($account_administer);

    $results = $this->doAutocomplete();
    $this->assertCount(2, $results);
    $this->assertSession()->pageTextNotContains('Status-1 term 0');
    $this->assertSession()->pageTextContains('Status-0 term 0');
    $this->assertSession()->pageTextNotContains('Status-1 term 1');
    $this->assertSession()->pageTextContains('Status-0 term 1');

    // Test published terms in 1st and unpublished terms in 2nd vocabulary.
    $account_administer = $this->drupalCreateUser([
      'select unpublished terms in ' . $this->vocabularies[0]->id(),
      'select terms in ' . $this->vocabularies[1]->id(),
      'create page content',
    ]);
    $this->drupalLogin($account_administer);

    $results = $this->doAutocomplete();
    $this->assertCount(2, $results);
    $this->assertSession()->pageTextNotContains('Status-1 term 0');
    $this->assertSession()->pageTextContains('Status-0 term 0');
    $this->assertSession()->pageTextContains('Status-1 term 1');
    $this->assertSession()->pageTextNotContains('Status-0 term 1');
  }

  /**
   * Asserts that the autocomplete is empty.
   *
   * @param string $permission
   *   Permission to test.
   */
  protected function assertEmptyAutocompleteWithPermission(string $permission) {
    $account = $this->drupalCreateUser([
      $permission,
      'create page content',
    ]);
    $this->drupalLogin($account);

    $results = $this->doAutocomplete();
    $this->assertCount(0, $results, "No results for permission '{$permission}'");
    $this->assertSession()->pageTextNotContains('Status-1 term 0');
    $this->assertSession()->pageTextNotContains('Status-0 term 0');
    $this->assertSession()->pageTextNotContains('Status-1 term 1');
    $this->assertSession()->pageTextNotContains('Status-0 term 1');
  }

  /**
   * Executes the autocomplete and waits for it to finish.
   *
   * @param string $text
   *   The text to search for.
   */
  protected function doAutocomplete(string $text = 'term') {
    $this->drupalGet('node/add/page');
    $autocomplete_field = $this->getSession()->getPage()->findField('field_test' . '[0][target_id]');
    $autocomplete_field->setValue($text);
    $this->getSession()->getDriver()->keyDown($autocomplete_field->getXpath(), ' ');
    assert(is_a($this->assertSession(), JSWebAssert::class));
    $this->assertSession()->waitOnAutocomplete();
    return $this->getSession()->getPage()->findAll('css', '.ui-autocomplete li');
  }

}
