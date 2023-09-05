<?php

namespace Drupal\tmgmt_content\Plugin\tmgmt\Source;

use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityReference;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\TranslatableRevisionableStorageInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Url;
use Drupal\entity_reference_revisions\EntityNeedsSaveInterface;
use Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList;
use Drupal\tmgmt\JobItemInterface;
use Drupal\tmgmt\SourcePluginBase;
use Drupal\tmgmt\SourcePreviewInterface;
use Drupal\tmgmt\ContinuousSourceInterface;
use Drupal\tmgmt\TMGMTException;
use Drupal\Core\Render\Element;
use Drupal\Core\Form\FormStateInterface;
use Drupal\tmgmt\Entity\Job;

/**
 * Content entity source plugin controller.
 *
 * @SourcePlugin(
 *   id = "content",
 *   label = @Translation("Content Entity"),
 *   description = @Translation("Source handler for entities."),
 *   ui = "Drupal\tmgmt_content\ContentEntitySourcePluginUi"
 * )
 */
class ContentEntitySource extends SourcePluginBase implements SourcePreviewInterface, ContinuousSourceInterface {

  /**
   * Returns the entity for the given job item.
   *
   * @param \Drupal\tmgmt\JobItemInterface $job_item
   *   The job entity
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity.
   */
  protected function getEntity(JobItemInterface $job_item) {
    return \Drupal::entityTypeManager()->getStorage($job_item->getItemType())->load($job_item->getItemId());
  }

  /**
   * Loads a list of entities for the given entity type ID.
   *
   * By providing the language code, the latest revisions affecting the
   * specified translation (language code) will be returned.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param array $entity_ids
   *   A list of entity IDs to load.
   * @param string|null $langcode
   *   (optional) The language code. Defaults to source entity language.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Returns a list of entities.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function loadMultiple($entity_type_id, array $entity_ids, $langcode = NULL) {
    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage($entity_type_id);

    $entities = $storage->loadMultiple($entity_ids);

    // Load the latest revision if the entity type is revisionable.
    if ($storage->getEntityType()->isRevisionable() && $storage instanceof TranslatableRevisionableStorageInterface) {
      foreach ($entities as $entity_id => $entity) {
        // Use the specified langcode or fallback to the default language.
        $translation_langcode = $langcode ?: $entity->language()->getId();
        $revision_id = $storage->getLatestTranslationAffectedRevisionId($entity->id(), $translation_langcode);
        // Get the pending revisions. If the returned revision ID is the same as
        // the default one, there is no need for further checks.
        if ($revision_id && $entity->getRevisionId() != $revision_id) {
          $revision = $storage->loadRevision($revision_id);
          // If the affected revision was the default one at some point, then it
          // is an old revision that should be part of the already loaded
          // default so we do not need to replace it here.
          if (!$revision->wasDefaultRevision()) {
            $entities[$entity_id] = $revision;
          }
        }
      }
    }

    return $entities;
  }

  /**
   * Loads a single entity for the given entity type ID.
   *
   * By providing the language code, the latest revisions affecting the
   * specified translation (language code) will be returned.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $id
   *   The entity ID.
   * @param string|null $langcode
   *   (optional) The language code. Defaults to source entity language.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The loaded entity or null if not found.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function load($entity_type_id, $id, $langcode = NULL) {
    $entities = static::loadMultiple($entity_type_id, [$id], $langcode);
    return isset($entities[$id]) ? $entities[$id] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(JobItemInterface $job_item) {
    // Use the source language to a get label for the job item.
    $langcode = $job_item->getJob() ? $job_item->getJob()->getSourceLangcode() : NULL;
    $label = FALSE;
    if ($entity = static::load($job_item->getItemType(), $job_item->getItemId(), $langcode)) {
      $label = $entity->label() ?: $entity->id();
    }
    return is_bool($label) ? $label : (string) $label;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl(JobItemInterface $job_item) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $langcode = $job_item->getJob() ? $job_item->getJob()->getSourceLangcode() : NULL;
    if ($entity = static::load($job_item->getItemType(), $job_item->getItemId(), $langcode)) {
      if ($entity->hasLinkTemplate('canonical')) {
        $anonymous = new AnonymousUserSession();
        $url = $entity->toUrl();
        $anonymous_access = \Drupal::config('tmgmt.settings')->get('anonymous_access');
        if ($url && $anonymous_access && !$entity->access('view', $anonymous)) {
          $url->setOption('query', [
            'key' => \Drupal::service('tmgmt_content.key_access')
              ->getKey($job_item),
          ]);
        }
        return $url;
      }
    }
    return NULL;
  }

  /**
   * Implements TMGMTEntitySourcePluginController::getData().
   *
   * Returns the data from the fields as a structure that can be processed by
   * the Translation Management system.
   */
  public function getData(JobItemInterface $job_item) {
    $langcode = $job_item->getJob() ? $job_item->getJob()->getSourceLangcode() : NULL;
    $entity = static::load($job_item->getItemType(), $job_item->getItemId(), $langcode);
    if (!$entity) {
      throw new TMGMTException(t('Unable to load entity %type with id %id', array('%type' => $job_item->getItemType(), '%id' => $job_item->getItemId())));
    }
    $languages = \Drupal::languageManager()->getLanguages();
    $id = $entity->language()->getId();
    if (!isset($languages[$id])) {
      throw new TMGMTException(t('Entity %entity could not be translated because the language %language is not applicable', array('%entity' => $entity->language()->getId(), '%language' => $entity->language()->getName())));
    }

    if (!$entity->hasTranslation($job_item->getJob()->getSourceLangcode())) {
      throw new TMGMTException(t('The %type entity %id with translation %lang does not exist.', array('%type' => $entity->getEntityTypeId(), '%id' => $entity->id(), '%lang' => $job_item->getJob()->getSourceLangcode())));
    }

    $translation = $entity->getTranslation($job_item->getJob()->getSourceLangcode());
    $data = $this->extractTranslatableData($translation);
    $entity_form_display = \Drupal::service('entity_display.repository')->getFormDisplay($job_item->getItemType(), $entity->bundle(), 'default');
    uksort($data, function ($a, $b) use ($entity_form_display) {
      $a_weight = NULL;
      $b_weight = NULL;
      // Get the weights.
      if ($entity_form_display->getComponent($a) && (isset($entity_form_display->getComponent($a)['weight']) && !is_null($entity_form_display->getComponent($a)['weight']))) {
        $a_weight = (int) $entity_form_display->getComponent($a)['weight'];
      }
      if ($entity_form_display->getComponent($b) && (isset($entity_form_display->getComponent($b)['weight']) && !is_null($entity_form_display->getComponent($b)['weight']))) {
        $b_weight = (int) $entity_form_display->getComponent($b)['weight'];
      }

      // If neither field has a weight, sort alphabetically.
      if ($a_weight === NULL && $b_weight === NULL) {
        return ($a > $b) ? 1 : -1;
      }
      // If one of them has no weight, the other comes first.
      elseif ($a_weight === NULL) {
        return 1;
      }
      elseif ($b_weight === NULL) {
        return -1;
      }
      // If both have a weight, sort by weight.
      elseif ($a_weight == $b_weight) {
        return 0;
      }
      else {
        return ($a_weight > $b_weight) ? 1 : -1;
      }
    });
    return $data;
  }

  /**
   * Extracts translatable data from an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to get the translatable data from.
   *
   * @return array $data
   *   Translatable data.
   */
  public function extractTranslatableData(ContentEntityInterface $entity) {
    $field_definitions = $entity->getFieldDefinitions();
    $exclude_field_types = ['language'];
    $exclude_field_names = ['moderation_state'];

    /** @var \Drupal\content_translation\ContentTranslationManagerInterface $content_translation_manager */
    $content_translation_manager = \Drupal::service('content_translation.manager');
    $is_bundle_translatable = $content_translation_manager->isEnabled($entity->getEntityTypeId(), $entity->bundle());

    // Exclude field types from translation.
    $translatable_fields = array_filter($field_definitions, function (FieldDefinitionInterface $field_definition) use ($exclude_field_types, $exclude_field_names, $is_bundle_translatable) {

      if ($is_bundle_translatable) {
        // Field is not translatable.
        if (!$field_definition->isTranslatable()) {
          return FALSE;
        }
      }
      elseif (!$field_definition->getFieldStorageDefinition()->isTranslatable()) {
        return FALSE;
      }

      // Field type matches field types to exclude.
      if (in_array($field_definition->getType(), $exclude_field_types)) {
        return FALSE;
      }

      // Field name matches field names to exclude.
      if (in_array($field_definition->getName(), $exclude_field_names)) {
        return FALSE;
      }

      // User marked the field to be excluded.
      if ($field_definition instanceof ThirdPartySettingsInterface) {
        $is_excluded = $field_definition->getThirdPartySetting('tmgmt_content', 'excluded', FALSE);
        if ($is_excluded) {
          return FALSE;
        }
      }
      return TRUE;
    });

    \Drupal::moduleHandler()->alter('tmgmt_translatable_fields', $entity, $translatable_fields);

    $data = array();
    foreach ($translatable_fields as $field_name => $field_definition) {
      $field = $entity->get($field_name);
      $data[$field_name] = $this->getFieldProcessor($field_definition->getType())->extractTranslatableData($field);
    }

    $embeddable_fields = static::getEmbeddableFields($entity);
    foreach ($embeddable_fields as $field_name => $field_definition) {
      $field = $entity->get($field_name);

      /* @var \Drupal\Core\Field\FieldItemInterface $field_item */
      foreach ($field as $delta => $field_item) {
        foreach ($field_item->getProperties(TRUE) as $property_key => $property) {
          // If the property is a content entity reference and it's value is
          // defined, than we call this method again to get all the data.
          if ($property instanceof EntityReference && $property->getValue() instanceof ContentEntityInterface) {
            // All the labels are here, to make sure we don't have empty
            // labels in the UI because of no data.
            $data[$field_name]['#label'] = $field_definition->getLabel();
            if (count($field) > 1) {
              // More than one item, add a label for the delta.
              $data[$field_name][$delta]['#label'] = t('Delta #@delta', array('@delta' => $delta));
            }
            // Get the referenced entity.
            $referenced_entity = $property->getValue();
            // Get the source language code.
            $langcode = $entity->language()->getId();
            // If the referenced entity is translatable and has a translation
            // use it instead of the default entity translation.
            if ($content_translation_manager->isEnabled($referenced_entity->getEntityTypeId(), $referenced_entity->bundle()) && $referenced_entity->hasTranslation($langcode)) {
              $referenced_entity = $referenced_entity->getTranslation($langcode);
            }
            $data[$field_name][$delta][$property_key] = $this->extractTranslatableData($referenced_entity);
            // Use the ID of the entity to identify it later, do not rely on the
            // UUID as content entities are not required to have one.
            $data[$field_name][$delta][$property_key]['#id'] = $property->getValue()->id();
          }
        }

      }
    }
    return $data;
  }

  /**
   * Determines whether an entity is moderated.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return bool
   *   TRUE if the entity is moderated. Otherwise, FALSE.
   */
  public static function isModeratedEntity(EntityInterface $entity) {
    if (!\Drupal::moduleHandler()->moduleExists('content_moderation')) {
      return FALSE;
    }

    return \Drupal::service('content_moderation.moderation_information')->isModeratedEntity($entity);
  }

  /**
   * Returns fields that should be embedded into the data for the given entity.
   *
   * Includes explicitly enabled fields and composite entities that are
   * implicitly included to the translatable data.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to get the translatable data from.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[] $embeddable_fields
   *   A list of field definitions that can be embedded.
   */
  public static function getEmbeddableFields(ContentEntityInterface $entity) {
    // Get the configurable embeddable references.
    $field_definitions = $entity->getFieldDefinitions();
    $embeddable_field_names = \Drupal::config('tmgmt_content.settings')->get('embedded_fields');
    $embeddable_fields = array_filter($field_definitions, function (FieldDefinitionInterface $field_definition) use ($embeddable_field_names) {
      return isset($embeddable_field_names[$field_definition->getTargetEntityTypeId()][$field_definition->getName()]);
    });

    // Get always embedded references.
    $content_translation_manager = \Drupal::service('content_translation.manager');
    foreach ($field_definitions as $field_name => $field_definition) {
      $storage_definition = $field_definition->getFieldStorageDefinition();

      $property_definitions = $storage_definition->getPropertyDefinitions();
      foreach ($property_definitions as $property_definition) {
        // Look for entity_reference properties where the storage definition
        // has a target type setting.
        if (in_array($property_definition->getDataType(), ['entity_reference', 'entity_revision_reference']) && ($target_type_id = $storage_definition->getSetting('target_type'))) {
          $is_target_type_enabled = $content_translation_manager->isEnabled($target_type_id);
          $target_entity_type = \Drupal::entityTypeManager()->getDefinition($target_type_id);

          // Include current entity reference field that is considered a
          // composite and translatable or if the parent entity is considered a
          // composite as well. This allows to embed nested untranslatable
          // fields (For example: Paragraphs).
          if ($target_entity_type->get('entity_revision_parent_type_field') && ($is_target_type_enabled || $entity->getEntityType()->get('entity_revision_parent_type_field'))) {
            $embeddable_fields[$field_name] = $field_definition;
          }
        }
      }
    }

    return $embeddable_fields;
  }

  /**
   * {@inheritdoc}
   */
  public function saveTranslation(JobItemInterface $job_item, $target_langcode) {
    /* @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->getEntity($job_item);
    if (!$entity) {
      $job_item->addMessage('The entity %id of type %type does not exist, the job can not be completed.', array(
        '%id' => $job_item->getItemId(),
        '%type' => $job_item->getItemType(),
      ), 'error');
      return FALSE;
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    if ($entity_revision = $this->getPendingRevisionWithCompositeReferenceField($job_item)) {
      $title = $entity_revision->hasLinkTemplate('latest-version') ? $entity_revision->toLink(NULL, 'latest-version')->toString() : $entity_revision->label();
      $job_item->addMessage('This translation cannot be accepted as there is a pending revision in the default translation. You must publish %title first before saving this translation.', [
        '%title' => $title,
      ], 'error');
      return FALSE;
    }

    $data = $job_item->getData();

    $this->doSaveTranslations($entity, $data, $target_langcode, $job_item);
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemTypes() {
    $entity_types = \Drupal::entityTypeManager()->getDefinitions();
    $types = array();
    $content_translation_manager = \Drupal::service('content_translation.manager');
    foreach ($entity_types as $entity_type_name => $entity_type) {
      // Entity types with this key set are considered composite entities and
      // always embedded in others. Do not expose them as their own item type.
      if ($entity_type->get('entity_revision_parent_type_field')) {
        continue;
      }
      if ($content_translation_manager->isEnabled($entity_type->id())) {
        $types[$entity_type_name] = $entity_type->getLabel();
      }
    }
    return $types;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemTypeLabel($type) {
    return \Drupal::entityTypeManager()->getDefinition($type)->getLabel();
  }

  /**
   * {@inheritdoc}
   */
  public function getType(JobItemInterface $job_item) {
    if ($entity = $this->getEntity($job_item)) {
      $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo($job_item->getItemType());
      $entity_type = $entity->getEntityType();
      $bundle = $entity->bundle();
      // Display entity type and label if we have one and the bundle isn't
      // the same as the entity type.
      if (isset($bundles[$bundle]) && $bundle != $job_item->getItemType()) {
        return t('@type (@bundle)', array('@type' => $entity_type->getLabel(), '@bundle' => $bundles[$bundle]['label']));
      }
      // Otherwise just display the entity type label.
      return $entity_type->getLabel();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceLangCode(JobItemInterface $job_item) {
    if (!$entity = $this->getEntity($job_item)) {
      return FALSE;
    }
    return $entity->getUntranslated()->language()->getId();
  }

  /**
   * {@inheritdoc}
   */
  public function getExistingLangCodes(JobItemInterface $job_item) {
    if ($entity = $this->getEntity($job_item)) {
      return array_keys($entity->getTranslationLanguages());
    }

    return array();
  }

  /**
   * Saves translation data in an entity translation.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity for which the translation should be saved.
   * @param array $data
   *   The translation data for the fields.
   * @param string $target_langcode
   *   The target language.
   * @param \Drupal\tmgmt\JobItemInterface $item
   *   The job item.
   * @param bool $save
   *   (optional) Whether to save the translation or not.
   *
   * @throws \Exception
   *   Thrown when a field or field offset is missing.
   */
  protected function doSaveTranslations(ContentEntityInterface $entity, array $data, $target_langcode, JobItemInterface $item, $save = TRUE) {
   // If the translation for this language does not exist yet, initialize it.
    if (!$entity->hasTranslation($target_langcode)) {
      $entity->addTranslation($target_langcode, $entity->toArray());
    }

    $translation = $entity->getTranslation($target_langcode);
    $manager = \Drupal::service('content_translation.manager');
    if ($manager->isEnabled($translation->getEntityTypeId(), $translation->bundle())) {
      $manager->getTranslationMetadata($translation)->setSource($entity->language()->getId());
    }

    foreach (Element::children($data) as $field_name) {
      $field_data = $data[$field_name];

      if (!$translation->hasField($field_name)) {
        throw new \Exception("Field '$field_name' does not exist on entity " . $translation->getEntityTypeId() . '/' . $translation->id());
      }

      $field = $translation->get($field_name);
      $field_processor = $this->getFieldProcessor($field->getFieldDefinition()->getType());
      $field_processor->setTranslations($field_data, $field);
    }

    $embeddable_fields = static::getEmbeddableFields($entity);
    foreach ($embeddable_fields as $field_name => $field_definition) {

      if (!isset($data[$field_name])) {
        continue;
      }

      $field = $translation->get($field_name);
      $target_type = $field->getFieldDefinition()->getFieldStorageDefinition()->getSetting('target_type');
      $is_target_type_translatable = $manager->isEnabled($target_type);
      // In case the target type is not translatable, the referenced entity will
      // be duplicated. As a consequence, remove all the field items from the
      // translation, update the field value to use the field object from the
      // source language.
      if (!$is_target_type_translatable) {
        $field = clone $entity->get($field_name);

        if (!$translation->get($field_name)->isEmpty()) {
          $translation->set($field_name, NULL);
        }
      }

      foreach (Element::children($data[$field_name]) as $delta) {
        $field_item = $data[$field_name][$delta];
        foreach (Element::children($field_item) as $property) {
          // Find the referenced entity. In case we are dealing with
          // untranslatable target types, the source entity will be returned.
          if ($target_entity = $this->findReferencedEntity($field, $field_item, $delta, $property, $is_target_type_translatable)) {
            if ($is_target_type_translatable) {
              // If the field is an embeddable reference and the property is a
              // content entity, process it recursively.

              // If the field is ERR and the target entity supports
              // the needs saving interface, do not save it immediately to avoid
              // creating two versions when content moderation is used but just
              // ensure it will be saved.
              $target_save = TRUE;
              if ($field->getFieldDefinition()->getType() == 'entity_reference_revisions' && $target_entity instanceof EntityNeedsSaveInterface) {
                $target_save = FALSE;
                $target_entity->needsSave();
              }

              $this->doSaveTranslations($target_entity, $field_item[$property], $target_langcode, $item, $target_save);
            }
            else {
              $duplicate = $this->createTranslationDuplicate($target_entity, $target_langcode);
              // Do not save the duplicate as it's going to be saved with the
              // main entity.
              $this->doSaveTranslations($duplicate, $field_item[$property], $target_langcode, $item, FALSE);
              $translation->get($field_name)->set($delta, $duplicate);
            }
          }
        }
      }
    }

    if (static::isModeratedEntity($translation)) {
      // Use the given moderation status if set. Otherwise, fallback to the
      // configured one in TMGMT settings.
      if (isset($data['#moderation_state'][0])) {
        $moderation_state = $data['#moderation_state'][0];
      }
      else {
        $moderation_info = \Drupal::service('content_moderation.moderation_information');
        $workflow = $moderation_info->getWorkflowForEntity($entity);
        $moderation_state = \Drupal::config('tmgmt_content.settings')->get('default_moderation_states.' . $workflow->id());
      }
      if ($moderation_state) {
        $translation->set('moderation_state', $moderation_state);
      }
    }
    // Otherwise, try to set a published status.
    elseif (isset($data['#published'][0]) && $translation instanceof EntityPublishedInterface) {
      if ($data['#published'][0]) {
        $translation->setPublished();
      }
      else {
        $translation->setUnpublished();
      }
    }

    if ($entity->getEntityType()->isRevisionable()) {
      /** @var \Drupal\Core\Entity\TranslatableRevisionableStorageInterface $storage */
      $storage = \Drupal::entityTypeManager()->getStorage($entity->getEntityTypeId());

      if ($storage instanceof TranslatableRevisionableStorageInterface) {
        // Always create a new revision of the translation.
        $translation = $storage->createRevision($translation, $translation->isDefaultRevision());

        if ($entity instanceof RevisionLogInterface) {
          $translation->setRevisionLogMessage($this->t('Created by translation job <a href=":url">@label</a>.', [
            ':url' => $item->getJob()->toUrl()->toString(),
            '@label' => $item->label(),
          ]));
        }
      }
    }

    if ($save) {
      $translation->save();
    }
  }

  /**
   * Creates a translation duplicate of the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $target_entity
   *   The target entity to clone.
   * @param string $langcode
   *   Language code for all the clone entities created.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   New entity object with the data from the original entity. Not
   *   saved. No sub-entities are cloned.
   */
  protected function createTranslationDuplicate(ContentEntityInterface $target_entity, $langcode) {
    $duplicate = $target_entity->createDuplicate();

    // Change the original language.
    if ($duplicate->getEntityType()->hasKey('langcode')) {
      $duplicate->set($duplicate->getEntityType()->getKey('langcode'), $langcode);
    }

    return $duplicate;
  }

  /**
   * {@inheritdoc}
   */
  public function getPreviewUrl(JobItemInterface $job_item) {
    if ($job_item->getJob()->isActive() && !($job_item->isAborted() || $job_item->isAccepted())) {
      return new Url('tmgmt_content.job_item_preview', ['tmgmt_job_item' => $job_item->id()], ['query' => ['key' => \Drupal::service('tmgmt_content.key_access')->getKey($job_item)]]);
    }
    else {
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function continuousSettingsForm(array &$form, FormStateInterface $form_state, Job $job) {
    $continuous_settings = $job->getContinuousSettings();
    $element = array();
    $item_types = $this->getItemTypes();
    asort($item_types);
    $entity_type_manager = \Drupal::entityTypeManager();
    foreach ($item_types as $item_type => $item_type_label) {
      $entity_type = $entity_type_manager->getDefinition($item_type);
      $element[$entity_type->id()]['enabled'] = array(
        '#type' => 'checkbox',
        '#title' => $item_type_label,
        '#default_value' => isset($continuous_settings[$this->getPluginId()][$entity_type->id()]) ? $continuous_settings[$this->getPluginId()][$entity_type->id()]['enabled'] : FALSE,
      );
      if ($entity_type->hasKey('bundle')) {
        $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo($item_type);
        $element[$entity_type->id()]['bundles'] = array(
          '#title' => $this->getBundleLabel($entity_type),
          '#type' => 'details',
          '#open' => TRUE,
          '#states' => array(
            'invisible' => array(
              'input[name="continuous_settings[' . $this->getPluginId() . '][' . $entity_type->id() . '][enabled]"]' => array('checked' => FALSE),
            ),
          ),
        );
        foreach ($bundles as $bundle => $bundle_label) {
          if (\Drupal::service('content_translation.manager')->isEnabled($entity_type->id(), $bundle)) {
            $element[$entity_type->id()]['bundles'][$bundle] = array(
              '#type' => 'checkbox',
              '#title' => $bundle_label['label'],
              '#default_value' => isset($continuous_settings[$this->getPluginId()][$entity_type->id()]['bundles'][$bundle]) ? $continuous_settings[$this->getPluginId()][$entity_type->id()]['bundles'][$bundle] : FALSE,
            );
          }
        }
      }
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function shouldCreateContinuousItem(Job $job, $plugin, $item_type, $item_id) {
    $continuous_settings = $job->getContinuousSettings();
    $entity = static::load($item_type, $item_id, $job->getSourceLangcode());
    $translation_manager = \Drupal::service('content_translation.manager');
    $translation = $entity->hasTranslation($job->getTargetLangcode()) ? $entity->getTranslation($job->getTargetLangcode()) : NULL;
    $metadata = isset($translation) ? $translation_manager->getTranslationMetadata($translation) : NULL;

    // If a translation exists and is not marked as outdated, no new job items
    // needs to be created.
    if (isset($translation) && !$metadata->isOutdated()) {
      return FALSE;
    }
    else {
      if ($entity && $entity->getEntityType()->hasKey('bundle')) {
        // The entity type has bundles, check both the entity type setting and
        // the bundle.
        if (!empty($continuous_settings[$plugin][$item_type]['bundles'][$entity->bundle()]) && !empty($continuous_settings[$plugin][$item_type]['enabled'])) {
          return TRUE;
        }
      }
      // No bundles, only check entity type setting.
      elseif (!empty($continuous_settings[$plugin][$item_type]['enabled'])) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Returns the bundle label for a given entity type.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return string
   *   The bundle label.
   */
  protected function getBundleLabel(EntityTypeInterface $entity_type) {
    if ($entity_type->getBundleLabel()) {
      return $entity_type->getBundleLabel();
    }
    if ($entity_type->getBundleEntityType()) {
      return \Drupal::entityTypeManager()
        ->getDefinition($entity_type->getBundleEntityType())
        ->getLabel();
    }
    return $this->t('@label type', ['@label' => $entity_type->getLabel()]);
  }

  /**
   * Returns the field processor for a given field type.
   *
   * @param string $field_type
   *   The field type.
   *
   * @return \Drupal\tmgmt_content\FieldProcessorInterface $field_processor
   *   The field processor for this field type.
   */
  protected function getFieldProcessor($field_type) {
    $definition = \Drupal::service('plugin.manager.field.field_type')->getDefinition($field_type);

    return \Drupal::service('class_resolver')->getInstanceFromDefinition($definition['tmgmt_field_processor']);
  }

  /**
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   * @param array $field_item
   * @param $delta
   * @param $property
   * @param bool $is_target_type_translatable
   *   (optional) Whether the target entity type is translatable.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   */
  protected function findReferencedEntity(FieldItemListInterface $field, array $field_item, $delta, $property, $is_target_type_translatable = TRUE) {
    // If an id is provided, loop over the field item deltas until we find the
    // matching entity. In case of untranslatable target types return the
    // source target entity as it will be duplicated.
    if (isset($field_item[$property]['#id'])) {
      foreach ($field as $item_delta => $item) {
        if ($item->$property instanceof ContentEntityInterface) {
          /** @var ContentEntityInterface $referenced_entity */
          $referenced_entity = $item->$property;
          if ($referenced_entity->id() == $field_item[$property]['#id'] || ($item_delta === $delta && !$is_target_type_translatable)) {
            return $referenced_entity;
          }
        }
      }

      // @todo Support loading an entity, throw an exception or log a warning?
    }
    // For backwards compatiblity, also support matching based on the delta.
    elseif ($field->offsetExists($delta) && $field->offsetGet($delta)->$property instanceof ContentEntityInterface) {
      return $field->offsetGet($delta)->$property;
    }
  }

  /**
   * Returns the source revision if it is a pending revision with an ERR field.
   *
   * @param \Drupal\tmgmt\JobItemInterface $job_item
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The source revision entity if it is a pending revision with an ERR field.
   */
  public function getPendingRevisionWithCompositeReferenceField(JobItemInterface $job_item) {
    // Get the latest revision of the default translation.
    /** \Drupal\Core\Entity\ContentEntityInterface|null $entity */
    $entity = static::load($job_item->getItemType(), $job_item->getItemId());
    if (!$entity) {
      return NULL;
    }

    // If the given revision is not the default revision, check if there is at
    // least one untranslatable composite entity reference revisions field and
    // fail the validation.
    if (!$entity->isDefaultRevision()) {
      foreach ($entity->getFieldDefinitions() as $definition) {
        if (in_array($definition->getType(), ['entity_reference', 'entity_reference_revisions']) && !$definition->isTranslatable()) {
          $target_type_id = $definition->getSetting('target_type');
          $entity_type_manager = \Drupal::entityTypeManager();
          if (!$entity_type_manager->hasDefinition($target_type_id)) {
            continue;
          }
          // Check if the target entity type is considered a composite.
          if ($entity_type_manager->getDefinition($target_type_id)->get('entity_revision_parent_type_field')) {
            return $entity;
          }
        }
      }
    }

    return NULL;
  }

}
