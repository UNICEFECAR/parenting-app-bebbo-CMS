<?php

namespace Drupal\KernelTests\Core\Entity;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Plugin\Validation\Constraint\EntityUntranslatableFieldsConstraint;
use Drupal\Core\Form\FormState;
use Drupal\Core\Language\LanguageInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests the constraint used to enforce untranslatable fields as read-only.
 *
 * @group Entity
 */
class EntityUntranslatableFieldsConstraintTest extends EntityKernelTestBase {

  const LANGCODE = 'de';

  const ENTITY_TYPE = 'entity_test_mulrev';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
  ];

  /**
   * The entity storage class for the entity type used in tests.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * A user account used to author entities.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema(self::ENTITY_TYPE);
    $this->storage = $this->container->get('entity_type.manager')
      ->getStorage(self::ENTITY_TYPE);
    ConfigurableLanguage::createFromLangcode(self::LANGCODE)->save();
    $this->state->set('entity_test.translation', 1);
    $this->state->set('entity_test.untranslatable_fields.default_translation_affected', 0);
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();
    $this->user = $this->createUser();
  }

  /**
   * Ensure empty fields with a default value are not changed when translating.
   *
   * @dataProvider accessValues
   */
  public function testEntityUntranslatableFieldsConstraint($access) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->storage->create([
      'name' => $this->randomString(),
      'user_id' => $this->user->id(),
      'langcode' => LanguageInterface::LANGCODE_DEFAULT,
    ]);
    $entity->save();
    $field_name = strtolower($this->randomMachineName());
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => self::ENTITY_TYPE,
      'type' => 'boolean',
      'translatable' => FALSE,
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => self::ENTITY_TYPE,
      'default_value' => [['value' => TRUE]],
    ])->save();
    // Necessary for picking up the new field.
    $entity = $this->storage->loadUnchanged($entity->id());
    $entity = $entity->addTranslation(self::LANGCODE);
    $entity->isDefaultRevision(FALSE);
    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $display */
    // The entity form should only contain the field under testing.
    $display = EntityFormDisplay::create([
      'targetEntityType' => self::ENTITY_TYPE,
      'bundle' => self::ENTITY_TYPE,
    ])->setComponent($field_name, [
      'type' => 'options_buttons',
    ]);
    $form = [];
    $form_state = new FormState();
    $display->buildForm($entity, $form, $form_state);
    // This is what the bug under testing is about, see comment below.
    $form[$field_name]['widget']['#access'] = $access;

    /** @var \Drupal\Core\Entity\ContentEntityFormInterface $form_object */
    // Pretend the form has been built.
    $form_object = $this->entityTypeManager
      ->getFormObject(self::ENTITY_TYPE, 'default')
      ->setEntity($entity);
    $form_state->setFormObject($form_object);

    $form_id = self::ENTITY_TYPE . '_entity_form';
    $form_builder = $this->container->get('form_builder');
    $form_builder->prepareForm($form_id, $form, $form_state);
    $form_builder->processForm($form_id, $form, $form_state);
    // This is an untranslatable field so changing it the translation of a
    // non-default revision should trigger a constraint violation except
    // when the widget is access denied because then the change should not
    // make it into the new entity.
    $form_state->setValueForElement($form[$field_name]['widget'], [['value' => 1]]);

    // Validate the field constraint.
    $violations = $form_object
      ->setFormDisplay($display, $form_state)
      ->buildEntity($form, $form_state)
      ->validate()
      ->getEntityViolations();
    // If access is allowed, we expect one violation, when denied, zero
    // violations is expected.
    $this->assertCount((int) $access, $violations);
    // If there is a violation, make sure it's the right one.
    foreach ($violations as $violation) {
      $this->assertInstanceOf(EntityUntranslatableFieldsConstraint::class, $violation->getConstraint());
    }
  }

  /**
   * Data provider for ::testEntityUntranslatableFieldsConstraint().
   */
  public function accessValues() {
    return [
      [TRUE],
      [FALSE],
    ];
  }

}
