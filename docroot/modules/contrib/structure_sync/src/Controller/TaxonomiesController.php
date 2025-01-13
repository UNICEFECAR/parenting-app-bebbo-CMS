<?php

namespace Drupal\structure_sync\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\structure_sync\StructureSyncHelper;
use Drupal\taxonomy\Entity\Term;
use Drush\Drush;

/**
 * Controller for syncing taxonomy terms.
 */
class TaxonomiesController extends ControllerBase {

  /**
   * An editable structure_sync.data configuration object.
   *
   * @var \Drupal\Core\Config\Config
   */
  private $config;

  /**
   * Constructor for taxonomies controller.
   */
  public function __construct() {
    $this->config = $this->getEditableConfig();
    $this->entityTypeManager();
  }

  /**
   * Gets the editable version of the config.
   */
  private function getEditableConfig() {
    $this->config('structure_sync.data');

    return $this->configFactory->getEditable('structure_sync.data');
  }

  /**
   * Function to export taxonomy terms.
   */
  public function exportTaxonomies(array $form = NULL, FormStateInterface $form_state = NULL) {
    StructureSyncHelper::logMessage('Taxonomies export started');

    if (is_object($form_state) && $form_state->hasValue('export_voc_list')) {
      $vocabulary_list = $form_state->getValue('export_voc_list');
      $vocabulary_list = array_filter($vocabulary_list, 'is_string');
    }

    // Get a list of all vocabularies (their machine names).
    if (!isset($vocabulary_list)) {
      $vocabulary_list = [];
      $vocabularies = $this->entityTypeManager
        ->getStorage('taxonomy_vocabulary')->loadMultiple();
      foreach ($vocabularies as $vocabulary) {
        $vocabulary_list[] = $vocabulary->id();
      }
    }
    if (!count($vocabulary_list)) {
      StructureSyncHelper::logMessage('No vocabularies available', 'warning');

      $this->messenger()->addWarning($this->t('No vocabularies selected/available'));
      return;
    }

    // Clear the (previous) taxonomies data in the config.
    $this->config->clear('taxonomies')->save();

    // Get all taxonomies from each (previously retrieved) vocabulary.
    foreach ($vocabulary_list as $vocabulary) {
      $query = StructureSyncHelper::getEntityQuery('taxonomy_term');
      $query->condition('vid', $vocabulary);
      $tids = $query->execute();
      $controller = $this->entityTypeManager
        ->getStorage('taxonomy_term');
      $entities = $controller->loadMultiple($tids);

      $parents = [];
      foreach ($tids as $tid) {
        $parent = $this->entityTypeManager
          ->getStorage('taxonomy_term')->loadParents($tid);
        $parent = reset($parent);

        if (is_object($parent)) {
          $parents[$tid] = $parent->id();
        }
      }

      // Build array of taxonomy terms and associated field values.
      $taxonomies = [];
      foreach ($entities as $entity) {
        $entity_properties = [
          'vid' => $vocabulary,
          'tid' => $entity->id(),
          'langcode' => $entity->langcode->value,
          'name' => $entity->name->value,
          'description__value' => $entity->get('description')->value,
          'description__format' => $entity->get('description')->format,
          'weight' => $entity->weight->value,
          'parent' => $parents[$entity->id()] ?? '0',
          'uuid' => $entity->uuid(),
        ];

        // Identify and build array of any custom fields attached to terms.
        $entity_fields = [];
        $entity_field_names = [];
        $all_term_fields = $entity->getFields();
        foreach ($all_term_fields as $field_name => $field) {
          $is_custom_field = 'field_' === substr($field_name, 0, 6);
          if ($is_custom_field) {
            $entity_field_names[] = $field_name;
          }
        }
        if ($entity_field_names) {
          foreach ($entity_field_names as $field_name) {
            $field_definition = $entity->$field_name->getFieldDefinition();
            $is_entity_reference = 'entity_reference' === $field_definition->getType();
            $is_term_reference = 'default:taxonomy_term' === $field_definition->getSetting('handler');

            if (!$is_entity_reference && !$is_term_reference) {
              $entity_fields[$field_name] = $entity->$field_name->getValue();
            }

            // If exporting entity reference field that references other
            // taxonomy terms, export term name/VID pair in place of TID:
            // Because TIDs aren't synced and may get altered using this module,
            // we need to look up TIDs from the name/VID pair during the import
            // step, otherwise term reference fields may lose data.
            else {
              $entity_reference_field_value = $entity->$field_name->getValue();
              foreach ($entity_reference_field_value as $field_item) {
                $target_term_entity = StructureSyncHelper::getEntityManager()
                  ->getStorage('taxonomy_term')->load($field_item['target_id']);
                if ($target_term_entity) {
                  $entity_fields[$field_name][] = [
                    'name' => $target_term_entity->getName(),
                    'vid' => $target_term_entity->bundle(),
                  ];
                }
              }
            }
          }
        }

        $taxonomies[] = $entity_properties + $entity_fields;
      }

      // Save the retrieved taxonomies to the config.
      $this->config->set('taxonomies.' . $vocabulary, $taxonomies)->save();

      StructureSyncHelper::logMessage('Exported ' . $vocabulary);
    }

    $this->messenger()->addStatus($this->t('The taxonomies have been successfully exported.'));
    StructureSyncHelper::logMessage('Taxonomies exported');
  }

  /**
   * Function to import taxonomy terms.
   *
   * When this function is used without the designated form, you should assign
   * an array with a key value pair for form with key 'style' and value 'full',
   * 'safe' or 'force' to apply that import style.
   */
  public function importTaxonomies(array $form, FormStateInterface $form_state = NULL) {
    StructureSyncHelper::logMessage('Taxonomy import started');

    // Check if the import style has been defined in the form (state) and else
    // get it from the form array.
    if (is_object($form_state) && $form_state->hasValue('import_voc_list')) {
      $taxonomiesSelected = $form_state->getValue('import_voc_list');
      $taxonomiesSelected = array_filter($taxonomiesSelected, 'is_string');
    }
    if (array_key_exists('style', $form)) {
      $style = $form['style'];
    }
    else {
      StructureSyncHelper::logMessage('No style defined on taxonomy import', 'error');
      return;
    }

    StructureSyncHelper::logMessage('Using "' . $style . '" style for taxonomy import');

    // Get taxonomies from config.
    $taxonomiesConfig = $this->config->get('taxonomies');

    $taxonomies = $taxonomiesConfig ? $taxonomiesConfig : [];

    if (isset($taxonomiesSelected)) {
      foreach ($taxonomiesConfig as $taxKey => $taxValue) {
        if (in_array($taxKey, $taxonomiesSelected)) {
          $taxonomies[$taxKey] = $taxValue;
        }
      }
    }

    // Sorts taxonomies so that all parent terms come before -- and therefore
    // are created before -- their respective child terms.
    foreach ($taxonomies as $taxonomy => $terms) {
      $parents = [];
      foreach ($terms as $key => $term_data) {
        $parents[$key] = $term_data['parent'];
      }
      array_multisort($parents, SORT_ASC, $taxonomies[$taxonomy]);
    }

    if (array_key_exists('drush', $form) && $form['drush'] === TRUE) {
      $context = [];
      $context['drush'] = TRUE;

      switch ($style) {
        case 'full':
          self::deleteDeletedTaxonomies($taxonomies, $context);
          self::importTaxonomiesFull($taxonomies, $context);
          self::taxonomiesImportFinishedCallback(NULL, NULL, NULL);
          break;

        case 'safe':
          self::importTaxonomiesSafe($taxonomies, $context);
          self::taxonomiesImportFinishedCallback(NULL, NULL, NULL);
          break;

        case 'force':
          self::deleteTaxonomies($context);
          self::importTaxonomiesForce($taxonomies, $context);
          self::taxonomiesImportFinishedCallback(NULL, NULL, NULL);
          break;
      }

      return;
    }

    // Import the taxonomies with the chosen style of importing.
    switch ($style) {
      case 'full':
        $batch = [
          'title' => $this->t('Importing taxonomies...'),
          'operations' => [
            [
              '\Drupal\structure_sync\Controller\TaxonomiesController::deleteDeletedTaxonomies',
              [$taxonomies],
            ],
            [
              '\Drupal\structure_sync\Controller\TaxonomiesController::importTaxonomiesFull',
              [$taxonomies],
            ],
          ],
          'finished' => '\Drupal\structure_sync\Controller\TaxonomiesController::taxonomiesImportFinishedCallback',
        ];
        batch_set($batch);
        break;

      case 'safe':
        $batch = [
          'title' => $this->t('Importing taxonomies...'),
          'operations' => [
            [
              '\Drupal\structure_sync\Controller\TaxonomiesController::importTaxonomiesSafe',
              [$taxonomies],
            ],
          ],
          'finished' => '\Drupal\structure_sync\Controller\TaxonomiesController::taxonomiesImportFinishedCallback',
        ];
        batch_set($batch);
        break;

      case 'force':
        $batch = [
          'title' => $this->t('Importing taxonomies...'),
          'operations' => [
            [
              '\Drupal\structure_sync\Controller\TaxonomiesController::deleteTaxonomies',
              [],
            ],
            [
              '\Drupal\structure_sync\Controller\TaxonomiesController::importTaxonomiesForce',
              [$taxonomies],
            ],
          ],
          'finished' => '\Drupal\structure_sync\Controller\TaxonomiesController::taxonomiesImportFinishedCallback',
        ];
        batch_set($batch);
        break;

      default:
        StructureSyncHelper::logMessage('Style not recognized', 'error');
        break;
    }
  }

  /**
   * Function to delete the taxonomies that should be removed in this import.
   */
  public static function deleteDeletedTaxonomies($taxonomies, &$context) {
    $uuidsInConfig = [];
    foreach ($taxonomies as $voc) {
      foreach ($voc as $taxonomy) {
        $uuidsInConfig[] = $taxonomy['uuid'];
      }
    }

    if (!empty($uuidsInConfig)) {
      $query = StructureSyncHelper::getEntityQuery('taxonomy_term');
      $query->condition('uuid', $uuidsInConfig, 'NOT IN');
      $tids = $query->execute();
      $controller = StructureSyncHelper::getEntityManager()
        ->getStorage('taxonomy_term');
      $entities = $controller->loadMultiple($tids);
      $controller->delete($entities);
    }

    if (array_key_exists('drush', $context) && $context['drush'] === TRUE) {
      Drush::logger()->notice('Deleted taxonomies that were not in config');
    }
    StructureSyncHelper::logMessage('Deleted taxonomies that were not in config');
  }

  /**
   * Function to fully import the taxonomies.
   *
   * Basically a safe import with update actions for already existing taxonomy
   * terms.
   */
  public static function importTaxonomiesFull($taxonomies, &$context) {
    $uuidsInConfig = [];
    foreach ($taxonomies as $voc) {
      foreach ($voc as $taxonomy) {
        $uuidsInConfig[] = $taxonomy['uuid'];
      }
    }
    $entities = [];
    if (!empty($uuidsInConfig)) {
      $query = StructureSyncHelper::getEntityQuery('taxonomy_term');
      $query->condition('uuid', $uuidsInConfig, 'IN');
      $tids = $query->execute();
      $controller = StructureSyncHelper::getEntityManager()
        ->getStorage('taxonomy_term');
      $entities = $controller->loadMultiple($tids);
    }

    $tidsDone = [];
    $tidsLeft = [];
    $newTids = [];
    $firstRun = TRUE;
    $runAgain = FALSE;
    $context['sandbox']['max'] = count($taxonomies);
    $context['sandbox']['progress'] = 0;
    while ($firstRun || count($tidsLeft) > 0) {
      foreach ($taxonomies as $vid => $vocabulary) {
        foreach ($vocabulary as $taxonomy) {
          $query = StructureSyncHelper::getEntityQuery('taxonomy_term');
          $query->condition('uuid', $taxonomy['uuid']);
          $tids = $query->execute();

          if (!in_array($taxonomy['tid'], $tidsDone) && ($taxonomy['parent'] === '0' || in_array($taxonomy['parent'], $tidsDone))) {
            $parent = $taxonomy['parent'];
            if (isset($newTids[$taxonomy['parent']])) {
              $parent = $newTids[$taxonomy['parent']];
            }

            // Identify and build array of any custom fields attached to
            // terms.
            $entity_fields = [];
            foreach ($taxonomy as $field_name => $field_value) {
              $is_custom_field = 'field_' === substr($field_name, 0, 6);
              if ($is_custom_field) {
                $not_term_reference = empty($field_value[0]['vid']);

                if ($not_term_reference) {
                  $entity_fields[$field_name] = $field_value;
                }
                // If importing entity reference field that references other
                // taxonomy terms, look up associated TID from name/VID value
                // pair provided during export: Because TIDs aren't synced and
                // may get altered using this module, we need to look up TIDs
                // from the name/VID pair during import, otherwise term
                // reference fields may lose data.
                else {
                  foreach ($field_value as $field_properties) {
                    $tid = StructureSyncHelper::getEntityManager()
                      ->getStorage('taxonomy_term')
                      ->getQuery()
                      ->accessCheck(FALSE)
                      ->condition('vid', $field_properties['vid'])
                      ->condition('name', $field_properties['name'])
                      ->execute();

                    if ($tid) {
                      $entity_fields[$field_name][] = [
                        'target_id' => reset($tid),
                      ];
                    }
                    else {
                      // If we encounter a term reference field referencing a
                      // term that hasn't been imported again, trigger re-import
                      // following current import to update term reference
                      // fields once all terms are available.
                      $runAgain = TRUE;
                    }
                  }
                }
              }
            }

            if (count($tids) <= 0) {
              $entity_properties = [
                'vid' => $vid,
                'langcode' => $taxonomy['langcode'],
                'name' => $taxonomy['name'],
                'description' => [
                  'value' => $taxonomy['description__value'],
                  'format' => $taxonomy['description__format'],
                ],
                'weight' => $taxonomy['weight'],
                'parent' => [$parent],
                'uuid' => $taxonomy['uuid'],
              ];

              Term::create($entity_properties + $entity_fields)->save();
            }
            else {
              foreach ($entities as $entity) {
                if ($taxonomy['uuid'] === $entity->uuid()) {
                  $term = Term::load($entity->id());
                  if (!empty($term)) {
                    $term->parent = [$parent];

                    $term
                      ->setName($taxonomy['name'])
                      ->setDescription($taxonomy['description__value'])
                      ->setFormat($taxonomy['description__format'])
                      ->setWeight($taxonomy['weight']);

                    if ($entity_fields) {
                      foreach ($entity_fields as $field_name => $field_value) {
                        $term->$field_name->setValue($field_value);
                      }
                    }

                    $term->save();
                  }
                }
              }
            }

            $query = StructureSyncHelper::getEntityQuery('taxonomy_term');
            $query->condition('vid', $vid);
            $query->condition('name', $taxonomy['name']);
            $tids = $query->execute();
            if (count($tids) > 0) {
              $terms = Term::loadMultiple($tids);
            }

            if (isset($terms) && count($terms) > 0) {
              reset($terms);
              $newTid = key($terms);
              $newTids[$taxonomy['tid']] = $newTid;
            }

            $tidsDone[] = $taxonomy['tid'];

            if (in_array($taxonomy['tid'], $tidsLeft)) {
              unset($tidsLeft[array_search($taxonomy['tid'], $tidsLeft)]);
            }

            StructureSyncHelper::logMessage('Imported "' . $taxonomy['name'] . '" into ' . $vid);

            $context['sandbox']['progress']++;
            if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
              $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
            }
          }
          else {
            if (!in_array($taxonomy['tid'], $tidsLeft)) {
              $tidsLeft[] = $taxonomy['tid'];
            }
          }
        }
      }

      if ($runAgain) {
        StructureSyncHelper::logMessage('Running additional full import'
          . ' after all terms have been created in order to identify missing '
          . ' TIDs for term reference fields.');
        self::importTaxonomiesFull($taxonomies, $context);
      }

      $firstRun = FALSE;
    }

    StructureSyncHelper::logMessage('Flushing all caches');
    if (array_key_exists('drush', $context) && $context['drush'] === TRUE) {
      Drush::logger()->notice('Flushing all caches');
    }

    drupal_flush_all_caches();

    StructureSyncHelper::logMessage('Successfully flushed caches');

    $context['finished'] = 1;
  }

  /**
   * Function to safely import taxonomies.
   *
   * Safely meaning that it should only add what isn't already there and not
   * delete and/or update any terms.
   */
  public static function importTaxonomiesSafe($taxonomies, &$context) {
    $tidsDone = [];
    $tidsLeft = [];
    $newTids = [];
    $firstRun = TRUE;
    $runAgain = FALSE;
    $context['sandbox']['max'] = count($taxonomies);
    $context['sandbox']['progress'] = 0;
    while ($firstRun || count($tidsLeft) > 0) {
      foreach ($taxonomies as $vid => $vocabulary) {
        foreach ($vocabulary as $taxonomy) {
          $query = StructureSyncHelper::getEntityQuery('taxonomy_term');
          $query->condition('vid', $vid);
          $query->condition('name', $taxonomy['name']);
          $tids = $query->execute();

          if (count($tids) <= 0) {
            if (!in_array($taxonomy['tid'], $tidsDone) && ($taxonomy['parent'] === '0' || in_array($taxonomy['parent'], $tidsDone))) {
              if (!in_array($taxonomy['tid'], $tidsDone)) {
                $parent = $taxonomy['parent'];
                if (isset($newTids[$taxonomy['parent']])) {
                  $parent = $newTids[$taxonomy['parent']];
                }

                $context['message'] = t('Importing @taxonomy', ['@taxonomy' => $taxonomy['name']]);

                $entity_properties = [
                  'vid' => $vid,
                  'langcode' => $taxonomy['langcode'],
                  'name' => $taxonomy['name'],
                  'description' => [
                    'value' => $taxonomy['description__value'],
                    'format' => $taxonomy['description__format'],
                  ],
                  'weight' => $taxonomy['weight'],
                  'parent' => [$parent],
                  'uuid' => $taxonomy['uuid'],
                ];

                // Identify and build array of any custom fields attached to
                // terms.
                $entity_fields = [];
                foreach ($taxonomy as $field_name => $field_value) {
                  $is_custom_field = 'field_' === substr($field_name, 0, 6);
                  if ($is_custom_field) {
                    $not_term_reference = empty($field_value[0]['vid']);

                    if ($not_term_reference) {
                      $entity_fields[$field_name] = $field_value;
                    }
                    // If importing entity reference field that references other
                    // taxonomy terms, look up associated TID from name/VID
                    // value pair provided during export: Because TIDs aren't
                    // synced and may get altered using this module, we need to
                    // look up TIDs from the name/VID pair during import,
                    // otherwise term reference fields may lose data.
                    else {
                      foreach ($field_value as $field_properties) {
                        $tid = StructureSyncHelper::getEntityManager()
                          ->getStorage('taxonomy_term')
                          ->getQuery()
                          ->accessCheck(FALSE)
                          ->condition('vid', $field_properties['vid'])
                          ->condition('name', $field_properties['name'])
                          ->execute();

                        if ($tid) {
                          $entity_fields[$field_name][] = [
                            'target_id' => reset($tid),
                          ];
                        }
                        else {
                          // If we encounter a term reference field referencing
                          // a term that hasn't been imported again, trigger
                          // re-import following current import to update term
                          // reference fields once all terms are available.
                          $runAgain = TRUE;
                        }
                      }
                    }
                  }
                }

                Term::create($entity_properties + $entity_fields)->save();

                $query = StructureSyncHelper::getEntityQuery('taxonomy_term');
                $query->condition('vid', $vid);
                $query->condition('name', $taxonomy['name']);
                $tids = $query->execute();
                if (count($tids) > 0) {
                  $terms = Term::loadMultiple($tids);
                }

                if (isset($terms) && count($terms) > 0) {
                  reset($terms);
                  $newTid = key($terms);
                  $newTids[$taxonomy['tid']] = $newTid;
                }

                $tidsDone[] = $taxonomy['tid'];

                if (in_array($taxonomy['tid'], $tidsLeft)) {
                  unset($tidsLeft[array_search($taxonomy['tid'], $tidsLeft)]);
                }

                StructureSyncHelper::logMessage('Imported "' . $taxonomy['name'] . '" into ' . $vid);

                $context['sandbox']['progress']++;
                if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
                  $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
                }
              }
            }
            else {
              if (!in_array($taxonomy['tid'], $tidsLeft)) {
                $tidsLeft[] = $taxonomy['tid'];
              }
            }
          }
          elseif (!in_array($taxonomy['tid'], $tidsDone)) {
            $query = StructureSyncHelper::getEntityQuery('taxonomy_term');
            $query->condition('vid', $vid);
            $query->condition('name', $taxonomy['name']);
            $tids = $query->execute();
            if (count($tids) > 0) {
              $terms = Term::loadMultiple($tids);
            }

            if (isset($terms) && count($terms) > 0) {
              reset($terms);
              $newTid = key($terms);
              $newTids[$taxonomy['tid']] = $newTid;
              $tidsDone[] = $taxonomy['tid'];
            }
          }
        }
      }

      if ($runAgain) {
        StructureSyncHelper::logMessage('Running additional full import'
          . ' after all terms have been created in order to identify missing '
          . ' TIDs for term reference fields.');
        self::importTaxonomiesFull($taxonomies, $context);
      }

      $firstRun = FALSE;
    }

    $context['finished'] = 1;
  }

  /**
   * Function to delete all taxonomy terms.
   */
  public static function deleteTaxonomies(&$context) {
    $query = StructureSyncHelper::getEntityQuery('taxonomy_term');
    $tids = $query->execute();
    $controller = StructureSyncHelper::getEntityManager()
      ->getStorage('taxonomy_term');
    $entities = $controller->loadMultiple($tids);
    $controller->delete($entities);

    if (array_key_exists('drush', $context) && $context['drush'] === TRUE) {
      Drush::logger()->notice('Deleted all taxonomies');
    }
    StructureSyncHelper::logMessage('Deleted all taxonomies');
  }

  /**
   * Function to import (create) all taxonomies that need to be imported.
   */
  public static function importTaxonomiesForce($taxonomies, &$context) {
    $tidsDone = [];
    $tidsLeft = [];
    $newTids = [];
    $firstRun = TRUE;
    $runAgain = FALSE;
    $context['sandbox']['max'] = count($taxonomies);
    $context['sandbox']['progress'] = 0;
    while ($firstRun || count($tidsLeft) > 0) {
      foreach ($taxonomies as $vid => $vocabulary) {
        foreach ($vocabulary as $taxonomy) {
          if (!in_array($taxonomy['tid'], $tidsDone) && ($taxonomy['parent'] === '0' || in_array($taxonomy['parent'], $tidsDone))) {
            if (!in_array($taxonomy['tid'], $tidsDone)) {
              $parent = $taxonomy['parent'];
              if (isset($newTids[$taxonomy['parent']])) {
                $parent = $newTids[$taxonomy['parent']];
              }

              $context['message'] = t('Importing @taxonomy', ['@taxonomy' => $taxonomy['name']]);

              $entity_properties = [
                'vid' => $vid,
                'langcode' => $taxonomy['langcode'],
                'name' => $taxonomy['name'],
                'description' => [
                  'value' => $taxonomy['description__value'],
                  'format' => $taxonomy['description__format'],
                ],
                'weight' => $taxonomy['weight'],
                'parent' => [$parent],
                'uuid' => $taxonomy['uuid'],
              ];

              // Identify and build array of any custom fields attached to
              // terms.
              $entity_fields = [];
              foreach ($taxonomy as $field_name => $field_value) {
                $is_custom_field = 'field_' === substr($field_name, 0, 6);
                if ($is_custom_field) {
                  $not_term_reference = empty($field_value[0]['vid']);

                  if ($not_term_reference) {
                    $entity_fields[$field_name] = $field_value;
                  }
                  // If importing entity reference field that references other
                  // taxonomy terms, look up associated TID from name/VID value
                  // pair provided during export: Because TIDs aren't synced and
                  // may get altered using this module, we need to look up TIDs
                  // from the name/VID pair during import, otherwise term
                  // reference fields may lose data.
                  else {
                    foreach ($field_value as $field_properties) {
                      $tid = StructureSyncHelper::getEntityManager()
                        ->getStorage('taxonomy_term')
                        ->getQuery()
                        ->accessCheck(FALSE)
                        ->condition('vid', $field_properties['vid'])
                        ->condition('name', $field_properties['name'])
                        ->execute();

                      if ($tid) {
                        $entity_fields[$field_name][] = [
                          'target_id' => reset($tid),
                        ];
                      }
                      else {
                        // If we encounter a term reference field referencing a
                        // term that hasn't been imported again, trigger
                        // re-import following current import to update term
                        // reference fields once all terms are available.
                        $runAgain = TRUE;
                      }
                    }
                  }
                }
              }

              Term::create($entity_properties + $entity_fields)->save();

              $query = StructureSyncHelper::getEntityQuery('taxonomy_term');
              $query->condition('vid', $vid);
              $query->condition('name', $taxonomy['name']);
              $tids = $query->execute();
              if (count($tids) > 0) {
                $terms = Term::loadMultiple($tids);
              }

              if (isset($terms) && count($terms) > 0) {
                reset($terms);
                $newTid = key($terms);
                $newTids[$taxonomy['tid']] = $newTid;
              }

              $tidsDone[] = $taxonomy['tid'];

              if (in_array($taxonomy['tid'], $tidsLeft)) {
                unset($tidsLeft[array_search($taxonomy['tid'], $tidsLeft)]);
              }

              StructureSyncHelper::logMessage('Imported "' . $taxonomy['name'] . '" into ' . $vid);

              $context['sandbox']['progress']++;
              if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
                $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
              }
            }
          }
          else {
            if (!in_array($taxonomy['tid'], $tidsLeft)) {
              $tidsLeft[] = $taxonomy['tid'];
            }
          }
        }
      }

      if ($runAgain) {
        StructureSyncHelper::logMessage('Running additional full import'
          . ' after all terms have been created in order to identify missing '
          . ' TIDs for term reference fields.');
        self::importTaxonomiesFull($taxonomies, $context);
      }

      $firstRun = FALSE;
    }

    $context['finished'] = 1;
  }

  /**
   * Function that signals that the import of taxonomies has finished.
   */
  public static function taxonomiesImportFinishedCallback($success, $results, $operations) {
    StructureSyncHelper::logMessage('Successfully imported taxonomies');

    \Drupal::messenger()->addStatus(t('Successfully imported taxonomies'));
  }

}
