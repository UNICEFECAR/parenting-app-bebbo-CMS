<?php

namespace Drupal\taxonomy_permissions\Tests;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\taxonomy\Functional\TaxonomyTestTrait;
use Drupal\Core\Session\AccountInterface;

/**
 * General setup and helper function for testing taxonomy permissions module.
 *
 * @group taxonomy_permissions
 */
class TaxonomyPermissionsTestBase extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  use TaxonomyTestTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'field_ui',
    'taxonomy',
    'taxonomy_permissions',
  ];

  /**
   * Entity form display.
   *
   * @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface
   */
  protected $formDisplay;

  /**
   * Entity view display.
   *
   * @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface
   */
  protected $viewDisplay;

  /**
   * User with permission to view terms.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $authorizedUser;

  /**
   * Basic User without permissions to view terms.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $basicUser;

  /**
   * A node created.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $article1;

  /**
   * A node created.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $article2;

  /**
   * A vocabulary created.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected $vocabulary;

  /**
   * A term created.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  protected $term1;

  /**
   * A term created.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  protected $term2;

  /**
   * Setup.
   */
  public function setUp() {
    parent::setUp();

    // Create vocabulary and terms.
    $this->vocabulary = $this->createVocabulary();
    $this->term1 = $this->createTerm($this->vocabulary);

    // We remove standard permission provided by default.
    $perms[] = 'view terms in ' . $this->vocabulary->id();
    user_role_revoke_permissions(AccountInterface::ANONYMOUS_ROLE, $perms);
    user_role_revoke_permissions(AccountInterface::AUTHENTICATED_ROLE, $perms);

    $this->drupalCreateContentType(['type' => 'article']);

    $this->authorizedUser = $this->drupalCreateUser([
      'create article content',
      'edit any article content',
      'access content',
      'view terms in ' . $this->vocabulary->id(),
    ]);

    $this->basicUser = $this->drupalCreateUser([
      'create article content',
      'edit any article content',
      'access content',
    ]);

    $field_name = 'field_term';
    $this->attachFields($field_name);

    $this->article1 = $this->createSimpleArticle('Article 1', $field_name, $this->term1->id());
  }

  /**
   * Tests if a user without permissions can view terms on node.
   */
  public function testViewTerm() {
    $this->drupalLogin($this->authorizedUser);
    $this->drupalGet("node/{$this->article1->id()}");
    $this->assertSession()->statusCodeEquals(200);
    // TODO: Drupal Rector Notice: Please delete the following comment after you've made any necessary changes.
    // Verify the assertion: pageTextContains() for HTML responses, responseContains() for non-HTML responses.
    // The passed text should be HTML decoded, exactly as a human sees it in the browser.
    $this->assertSession()->pageTextContains($this->term1->getName());
    $this->assertSession()->linkExists($this->term1->getName());

    $this->drupalLogin($this->basicUser);
    $this->drupalGet("node/{$this->article1->id()}");
    $this->assertSession()->statusCodeEquals(200);
    // TODO: Drupal Rector Notice: Please delete the following comment after you've made any necessary changes.
    // Verify the assertion: pageTextNotContains() for HTML responses, responseNotContains() for non-HTML responses.
    // The passed text should be HTML decoded, exactly as a human sees it in the browser.
    $this->assertSession()->pageTextNotContains($this->term1->getName());
    $this->assertSession()->linkNotExists($this->term1->getName());
  }

  /**
   * Tests if a user without permissions can access to term's page.
   */
  public function testAccessTermPage() {
    $this->drupalLogin($this->authorizedUser);
    $this->drupalGet("taxonomy/term/{$this->term1->id()}");
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalLogin($this->basicUser);
    $this->drupalGet("taxonomy/term/{$this->term1->id()}");
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests if a user without permissions can access to the field form.
   */
  public function testAccessFormTerm() {
    $this->drupalLogin($this->authorizedUser);
    $this->drupalGet("node/{$this->article1->id()}/edit");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals('field_term[0][target_id]', $this->term1->getName() . ' (' . $this->term1->id() . ')');

    $this->drupalLogin($this->basicUser);
    $this->drupalGet("node/{$this->article1->id()}/edit");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueNotEquals('field_term[0][target_id]', $this->term1->getName() . ' (' . $this->term1->id() . ')', 'The input field taxonomy reference is not accessible');
  }

  /**
   * Creates a taxonomy term reference field on the specified bundle.
   *
   * @param string $entity_type
   *   The type of entity the field will be attached to.
   * @param string $bundle
   *   The bundle name of the entity the field will be attached to.
   * @param string $field_name
   *   The name of the field; if it already exists, a new instance of existing
   *   field will be created.
   * @param string $field_label
   *   The label of the field.
   * @param string $target_entity_type
   *   The type of the referenced entity.
   * @param string $selection_handler
   *   The selection handler used by this field.
   * @param array $selection_handler_settings
   *   An array of settings supported by the selection handler specified above.
   *   (e.g. 'target_bundles', 'sort', 'auto_create', etc).
   * @param int $cardinality
   *   The cardinality of the field.
   * @param string $user_method
   *   The method used for granting access to user.
   * @param int $priority
   *   The priority access.
   *
   * @return \Drupal\field\Entity\FieldConfig
   *   The field created or loaded.
   *
   * @see \Drupal\Core\Entity\Plugin\EntityReferenceSelection\SelectionBase::buildConfigurationForm()
   */
  protected function createField($entity_type, $bundle, $field_name, $field_label, $target_entity_type, $selection_handler = 'default', array $selection_handler_settings = [], $cardinality = 1, $user_method = 'user', $priority = 0) {
    // Look for or add the specified field to the requested entity bundle.
    if (!FieldStorageConfig::loadByName($entity_type, $field_name)) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'type' => 'entity_reference',
        'entity_type' => $entity_type,
        'cardinality' => $cardinality,
        'settings' => [
          'target_type' => $target_entity_type,
        ],
      ])->save();
    }
    if (!FieldConfig::loadByName($entity_type, $bundle, $field_name)) {
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => $entity_type,
        'bundle' => $bundle,
        'label' => $field_label,
        'settings' => [
          'handler' => $selection_handler,
          'handler_settings' => $selection_handler_settings,
        ],
      ])->save();
    }
    $field = FieldConfig::loadByName($entity_type, $bundle, $field_name);
    return $field;
  }

  /**
   * Set the widget for a component in a form display.
   *
   * @param string $form_display_id
   *   The form display id.
   * @param string $entity_type
   *   The entity type name.
   * @param string $bundle
   *   The bundle name.
   * @param string $field_name
   *   The field name to set.
   * @param string $widget_id
   *   The widget id to set.
   * @param array $settings
   *   The settings of widget.
   * @param string $mode
   *   The mode name.
   */
  protected function setFormDisplay($form_display_id, $entity_type, $bundle, $field_name, $widget_id, array $settings, $mode = 'default') {
    // Set article's form display.
    $this->formDisplay = EntityFormDisplay::load($form_display_id);

    if (!$this->formDisplay) {
      EntityFormDisplay::create([
        'targetEntityType' => $entity_type,
        'bundle' => $bundle,
        'mode' => $mode,
        'status' => TRUE,
      ])->save();
      $this->formDisplay = EntityFormDisplay::load($form_display_id);
    }
    if ($this->formDisplay instanceof EntityFormDisplayInterface) {
      $this->formDisplay->setComponent($field_name, [
        'type' => $widget_id,
        'settings' => $settings,
      ])->save();
    }
  }

  /**
   * Set the widget for a component in a View display.
   *
   * @param string $form_display_id
   *   The form display id.
   * @param string $entity_type
   *   The entity type name.
   * @param string $bundle
   *   The bundle name.
   * @param string $field_name
   *   The field name to set.
   * @param string $formatter_id
   *   The formatter id to set.
   * @param array $settings
   *   The settings of widget.
   * @param string $mode
   *   The mode name.
   */
  protected function setViewDisplay($form_display_id, $entity_type, $bundle, $field_name, $formatter_id, array $settings, $mode = 'default') {
    // Set article's view display.
    $this->viewDisplay = EntityViewDisplay::load($form_display_id);
    if (!$this->viewDisplay) {
      EntityViewDisplay::create([
        'targetEntityType' => $entity_type,
        'bundle' => $bundle,
        'mode' => $mode,
        'status' => TRUE,
      ])->save();
      $this->viewDisplay = EntityViewDisplay::load($form_display_id);
    }
    if ($this->viewDisplay instanceof EntityViewDisplayInterface) {
      $this->viewDisplay->setComponent($field_name, [
        'type' => $formatter_id,
        'settings' => $settings,
      ])->save();
    }
  }

  /**
   * Helper function to create and attach a field to a node.
   *
   * @param string $field_name
   *   The field name to create and attach.
   */
  protected function attachFields($field_name) {
    // Add a field to the article content type which reference term.
    $handler_settings = [
      'target_bundles' => [
        $this->vocabulary->id() => $this->vocabulary->id(),
      ],
      'auto_create' => FALSE,
    ];

    $this->createField('node', 'article', $field_name, 'Term referenced', 'taxonomy_term', 'default', $handler_settings);

    // Set the form display.
    $settings = [
      'match_operator' => 'CONTAINS',
      'size' => 30,
      'placeholder' => '',
    ];
    $this->setFormDisplay('node.article.default', 'node', 'article', $field_name, 'entity_reference_autocomplete', $settings);

    // Set the view display.
    $settings = [
      'link' => TRUE,
    ];
    $this->setViewDisplay('node.article.default', 'node', 'article', $field_name, 'entity_reference_label', $settings);

  }

  /**
   * Create an article with value for field field_term.
   *
   * @param string $title
   *   The content title.
   * @param string $field_name
   *   The Pbf field name to populate.
   * @param int|string $target_id
   *   The target id of taxonomy term.
   *
   * @return \Drupal\node\NodeInterface
   *   The node created.
   */
  protected function createSimpleArticle($title, $field_name = '', $target_id = NULL) {
    $values = [
      'type' => 'article',
      'title' => $title,
      'body' => [
        'value' => 'Content body for ' . $title,
      ],
    ];
    if ($field_name) {
      $values[$field_name] = [
        'target_id' => $target_id,
      ];
    }
    return $this->drupalCreateNode($values);
  }

}
