<?php

/**
 * @file
 * Post-update hooks for Bebbo Custom General.
 */

use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Purges orphan entity_reference_revisions fields targeting paragraph.
 *
 * The paragraphs module is uninstalled on every site, but several sites still
 * carry deleted entity_reference_revisions fields (node.field_module,
 * node.field_question) whose data is queued for purge. Cron's field purge
 * (field_purge_batch) reads those queued items, which builds the field schema
 * via EntityReferenceRevisionsItem::schema() -> getDefinition('paragraph').
 * With the paragraph entity type gone this throws PluginNotFoundException and
 * aborts the whole cron purge batch — so those fields (and any other queued
 * deleted fields behind them) never clear and cron keeps failing.
 *
 * This finalises the purge for exactly those fields without reading their data:
 * field_purge_field_storage() -> node storage finalizePurge() drops the deleted
 * field's dedicated data/revision tables by name and clears its schema record.
 * That path never computes the field's column schema, so it does not need the
 * paragraph entity type. The remaining (non-paragraph) deleted fields then
 * purge normally on the next cron run.
 *
 * Runs per site (the deploy hook loops every site running `drush updb`) and is
 * idempotent: once the fields are gone — or if paragraphs is ever reinstalled —
 * it does nothing.
 */
function bebbo_custom_general_post_update_purge_orphan_paragraph_fields(): string {
  // If the paragraph entity type is available, normal cron purge works; leave
  // it alone.
  if (\Drupal::entityTypeManager()->hasDefinition('paragraph')) {
    return 'Paragraph entity type is available; orphan-field purge not needed.';
  }

  /** @var \Drupal\Core\Field\DeletedFieldsRepositoryInterface $repository */
  $repository = \Drupal::service('entity_field.deleted_fields_repository');

  $purged = [];
  foreach ($repository->getFieldStorageDefinitions() as $field_storage) {
    if ($field_storage->getType() !== 'entity_reference_revisions'
      || $field_storage->getSetting('target_type') !== 'paragraph') {
      continue;
    }

    // Purge the field definitions first, then the storage:
    // field_purge_field_storage() only proceeds once no field definitions
    // remain for the storage.
    $unique_id = $field_storage->getUniqueStorageIdentifier();
    foreach ($repository->getFieldDefinitions($unique_id) as $field) {
      field_purge_field($field);
    }
    field_purge_field_storage($field_storage);

    $purged[] = $field_storage->getTargetEntityTypeId() . '.' . $field_storage->getName();
  }

  return $purged
    ? 'Purged orphan paragraph-reference fields: ' . implode(', ', $purged) . '. Their dedicated tables were dropped; remaining deleted fields purge on the next cron.'
    : 'No orphan paragraph-reference fields queued for purge on this site.';
}

/**
 * Remove defunct modules and stale view configs before cim.
 *
 * Custom_serialization, pb_custom_rest_api, and pb_custom_standard_deviation
 * were deleted from disk. Active DB still references them in core.extension
 * and views still use the custom_serialization plugin, causing crashes.
 * This cleans up the active state so cim can proceed.
 */
function bebbo_custom_general_post_update_remove_defunct_modules(): string {
  $modules_to_remove = [
    'custom_serialization',
    'pb_custom_rest_api',
    'pb_custom_standard_deviation',
  ];

  // 1. Remove from core.extension.
  $config_factory = \Drupal::configFactory();
  $config = $config_factory->getEditable('core.extension');
  $module_list = $config->get('module') ?? [];
  $removed = [];
  foreach ($modules_to_remove as $module) {
    if (array_key_exists($module, $module_list)) {
      unset($module_list[$module]);
      $removed[] = $module;
    }
  }
  if ($removed) {
    $config->set('module', $module_list)->save();
  }

  // 2. Clean system.schema entries.
  \Drupal::database()->delete('key_value')
    ->condition('collection', 'system.schema')
    ->condition('name', $modules_to_remove, 'IN')
    ->execute();

  // 3. Delete stale view configs that reference custom_serialization.
  // cim will re-import the correct versions (articles is fully removed,
  // country_listing/sponsors_list/tax get updated without the old displays).
  $stale_views = [
    'views.view.articles',
    'views.view.duplicate_of_tax_test',
  ];
  foreach ($stale_views as $view_name) {
    $view_config = $config_factory->getEditable($view_name);
    if (!$view_config->isNew()) {
      $view_config->delete();
    }
  }

  // 4. Strip custom_serialization dependency and displays from remaining views.
  $views_with_stale_displays = [
    'views.view.country_listing' => ['rest_export_1'],
    'views.view.sponsors_list' => ['rest_export_1'],
    'views.view.tax' => ['rest_export_1', 'rest_export_3'],
  ];
  foreach ($views_with_stale_displays as $view_name => $display_ids) {
    $view_config = $config_factory->getEditable($view_name);
    if ($view_config->isNew()) {
      continue;
    }
    foreach ($display_ids as $display_id) {
      $view_config->clear("display.$display_id");
    }
    // Remove custom_serialization from dependencies.
    $deps = $view_config->get('dependencies.module') ?? [];
    $deps = array_values(array_diff($deps, ['custom_serialization']));
    $view_config->set('dependencies.module', $deps);
    $view_config->save();
  }

  $log = 'Defunct module cleanup: ';
  $log .= $removed ? 'removed ' . implode(', ', $removed) . ' from core.extension. ' : 'no stale modules in core.extension. ';
  $log .= 'Cleaned stale view configs (articles, duplicate_of_tax_test deleted; stale displays stripped from country_listing, sponsors_list, tax).';
  return $log;
}

/**
 * Delete stale REST resource configs from removed modules.
 *
 * The pb_custom_rest_api module provided custom_rest_resource and
 * v2_custom_rest_resource plugins. Their rest.resource.* configs crash
 * cache rebuild after the module is removed. Also cleans up orphaned
 * pb_custom_form configs whose module was merged into bebbo_custom_general.
 */
function bebbo_custom_general_post_update_remove_stale_rest_configs(): string {
  $config_factory = \Drupal::configFactory();
  $deleted = [];

  $stale_configs = [
    'rest.resource.custom_rest_resource',
    'rest.resource.v2_custom_rest_resource',
    'pb_custom_form.mobile_app_share_link_form',
    'pb_custom_form.language_redirects',
    'pb_custom_form.landing_pages',
    'pb_custom_form.app_store_redirect',
    'pb_custom_form.adminsettings',
  ];

  foreach ($stale_configs as $config_name) {
    $config = $config_factory->getEditable($config_name);
    if (!$config->isNew()) {
      $config->delete();
      $deleted[] = $config_name;
    }
  }

  // Also catch any sites where the first post_update was auto-skipped:
  // re-run the module removal from core.extension as a safety net.
  $modules_to_remove = [
    'custom_serialization',
    'pb_custom_rest_api',
    'pb_custom_standard_deviation',
  ];
  $config = $config_factory->getEditable('core.extension');
  $module_list = $config->get('module') ?? [];
  $extra_removed = [];
  foreach ($modules_to_remove as $module) {
    if (array_key_exists($module, $module_list)) {
      unset($module_list[$module]);
      $extra_removed[] = $module;
    }
  }
  if ($extra_removed) {
    $config->set('module', $module_list)->save();
    \Drupal::database()->delete('key_value')
      ->condition('collection', 'system.schema')
      ->condition('name', $modules_to_remove, 'IN')
      ->execute();
  }

  $log = $deleted ? 'Deleted stale configs: ' . implode(', ', $deleted) . '. ' : 'No stale configs found. ';
  if ($extra_removed) {
    $log .= 'Also removed missed modules: ' . implode(', ', $extra_removed) . '.';
  }
  return $log;
}

/**
 * Removes content stored in languages that are not configured on this site.
 *
 * The Entity Share content pull imported entity translations (and whole
 * entities) in languages that are not enabled on every site — e.g. the
 * Bangladesh site (en + bn only) ended up holding al-sq / ro-ro / me-cnr / ar /
 * uk / by-be / kg-ky / rs-sr / rs-en rows. These orphan rows are invisible to
 * the entity API and crash entity rendering when hit (e.g. the v2
 * country-groups endpoint), so each site must hold only its configured
 * languages.
 *
 * Runs per site (the deploy hook loops every site running `drush updb`) and is
 * idempotent. Triage per orphan entity:
 *   - entity has a valid translation + orphan translation(s) -> remove the
 *     orphan translations,
 *   - entity is entirely orphan (default langcode not configured) and is NOT
 *     referenced by configured-language content -> delete it,
 *   - entity is entirely orphan but IS referenced by configured-language
 *     content -> re-langcode it to the site default language (keeps the file /
 *     reference, fixes the bogus language).
 */
function bebbo_custom_general_post_update_clean_orphan_language_content(array &$sandbox): string {
  // Runs per site (the deploy hook loops every site running `drush updb`). Each
  // site keeps only the languages it has configured, so this cleans whatever
  // foreign-language content the Entity Share pull left behind on that site.
  $configured = array_keys(\Drupal::languageManager()->getLanguages());
  $keep = array_unique(array_merge($configured, ['und', 'zxx']));
  $default_langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();

  // Translatable content entity types to clean, with their data table + id key.
  $specs = [
    'node'          => ['table' => 'node_field_data', 'id' => 'nid'],
    'media'         => ['table' => 'media_field_data', 'id' => 'mid'],
    'group'         => ['table' => 'groups_field_data', 'id' => 'id'],
    'taxonomy_term' => ['table' => 'taxonomy_term_field_data', 'id' => 'tid'],
    'block_content' => ['table' => 'block_content_field_data', 'id' => 'id'],
  ];

  $database = \Drupal::database();
  $schema = $database->schema();

  // 1. Discover orphan langcodes + affected entity ids.
  $orphan_langs = [];
  $affected = [];
  foreach ($specs as $entity_type => $spec) {
    if (!$schema->tableExists($spec['table'])) {
      continue;
    }
    $rows = $database->query(
      "SELECT {$spec['id']} AS id, langcode FROM {$spec['table']} WHERE langcode NOT IN (:keep[])",
      [':keep[]' => $keep]
    )->fetchAll();
    foreach ($rows as $row) {
      $orphan_langs[$row->langcode] = TRUE;
      $affected[$entity_type][$row->id][$row->langcode] = TRUE;
    }
  }
  $orphan_langs = array_keys($orphan_langs);

  if (!$orphan_langs) {
    return 'No orphan-language content found on this site; nothing to clean.';
  }

  // 2. Temporarily register the orphan languages so the entity API can see
  //    (and properly delete) their translations.
  $created = [];
  foreach ($orphan_langs as $langcode) {
    if (!ConfigurableLanguage::load($langcode)) {
      ConfigurableLanguage::create(['id' => $langcode, 'label' => $langcode])->save();
      $created[] = $langcode;
    }
  }
  \Drupal::languageManager()->reset();

  $log = [
    'removed_translations' => 0,
    'deleted_entities' => 0,
    'relangcoded_entities' => 0,
  ];

  try {
    foreach ($affected as $entity_type => $entities) {
      $storage = \Drupal::entityTypeManager()->getStorage($entity_type);
      foreach ($entities as $id => $langs) {
        $storage->resetCache([$id]);
        $entity = $storage->load($id);
        if (!$entity) {
          continue;
        }

        $all_langs = array_keys($entity->getTranslationLanguages(TRUE));
        $valid_langs = array_intersect($all_langs, $configured);

        if ($valid_langs) {
          // Entity has at least one configured-language translation: drop only
          // the orphan translations.
          foreach (array_keys($langs) as $langcode) {
            if (!in_array($langcode, $configured, TRUE) && $entity->hasTranslation($langcode)) {
              $entity->removeTranslation($langcode);
              $log['removed_translations']++;
            }
          }
          $entity->save();
        }
        elseif (_bebbo_custom_general_referenced_by_valid($entity_type, $id, $configured)) {
          // Entirely orphan but referenced by valid content: re-langcode to the
          // site default so the reference and any file survive.
          _bebbo_custom_general_relangcode_entity($entity_type, $id, $orphan_langs, $default_langcode);
          $log['relangcoded_entities']++;
        }
        else {
          // Entirely orphan and unreferenced: delete it.
          $entity->delete();
          $log['deleted_entities']++;
        }
      }
    }
  }
  finally {
    // 3. Always remove the temporary languages.
    foreach ($created as $langcode) {
      ConfigurableLanguage::load($langcode)?->delete();
    }
    \Drupal::languageManager()->reset();
  }

  return sprintf(
    'Orphan-language cleanup (kept: %s): removed %d translation(s), deleted %d entity(ies), re-langcoded %d entity(ies).',
    implode('/', $configured),
    $log['removed_translations'],
    $log['deleted_entities'],
    $log['relangcoded_entities']
  );
}

/**
 * Checks whether an entity is referenced by any configured-language content.
 *
 * @param string $target_type
 *   The referenced entity type id.
 * @param int|string $target_id
 *   The referenced entity id.
 * @param string[] $configured
 *   Configured language codes.
 *
 * @return bool
 *   TRUE if a configured-language entity references the target.
 */
function _bebbo_custom_general_referenced_by_valid(string $target_type, $target_id, array $configured): bool {
  $field_manager = \Drupal::service('entity_field.manager');
  $etm = \Drupal::entityTypeManager();
  $database = \Drupal::database();
  $schema = $database->schema();

  foreach ($field_manager->getFieldMapByFieldType('entity_reference') as $host_type => $fields) {
    $definition = $etm->getDefinition($host_type, FALSE);
    if (!$definition) {
      continue;
    }
    $host_table = $definition->getDataTable() ?: $definition->getBaseTable();
    $host_id_key = $definition->getKey('id');
    $storage_defs = $field_manager->getFieldStorageDefinitions($host_type);

    foreach (array_keys($fields) as $field_name) {
      $storage_def = $storage_defs[$field_name] ?? NULL;
      if (!$storage_def || $storage_def->getSetting('target_type') !== $target_type) {
        continue;
      }
      $field_table = $host_type . '__' . $field_name;
      $target_column = $field_name . '_target_id';
      if (!$schema->tableExists($field_table) || !$host_table) {
        continue;
      }
      $found = $database->query(
        "SELECT 1 FROM {$field_table} ft
         INNER JOIN {$host_table} hd
           ON hd.{$host_id_key} = ft.entity_id AND hd.langcode = ft.langcode
         WHERE ft.{$target_column} = :id AND hd.langcode IN (:cfg[])
         LIMIT 1",
        [':id' => $target_id, ':cfg[]' => $configured]
      )->fetchField();
      if ($found) {
        return TRUE;
      }
    }
  }
  return FALSE;
}

/**
 * Fix stale enforceIsNew flag on field_description and field_question.
 *
 * The D11 upgrade left enforceIsNew=TRUE in the stored field storage
 * definitions (key_value) while code definitions have NULL. This causes
 * "Mismatched entity and/or field definitions" on the status report despite
 * identical schemas. Re-saving the definitions clears the flag.
 */
function bebbo_custom_general_post_update_fix_field_definition_mismatch(): string {
  $manager = \Drupal::entityDefinitionUpdateManager();
  $code_defs = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions('node');
  $fixed = [];

  foreach (['field_description', 'field_question'] as $field_name) {
    if (!isset($code_defs[$field_name])) {
      continue;
    }
    $installed = $manager->getFieldStorageDefinition($field_name, 'node');
    if (!$installed) {
      continue;
    }
    $manager->updateFieldStorageDefinition($code_defs[$field_name]);
    $fixed[] = $field_name;
  }

  return $fixed
    ? 'Fixed field definition mismatch for: ' . implode(', ', $fixed)
    : 'No mismatched field definitions found.';
}

/**
 * Re-langcodes every row of an entity from orphan languages to a target.
 *
 * Updates the base table plus every data / field / revision table that carries
 * a langcode column for the entity type.
 *
 * @param string $entity_type
 *   The entity type id.
 * @param int|string $id
 *   The entity id.
 * @param string[] $orphan_langs
 *   The orphan language codes to replace.
 * @param string $target
 *   The target (site default) language code.
 */
function _bebbo_custom_general_relangcode_entity(string $entity_type, $id, array $orphan_langs, string $target): void {
  $database = \Drupal::database();
  $definition = \Drupal::entityTypeManager()->getDefinition($entity_type);
  $id_key = $definition->getKey('id');
  // Tables for this entity type: base, data, revision and field tables share
  // the entity's table prefix (e.g. node, node_field_data, node__field_x).
  $base = $definition->getBaseTable();
  $candidates = [
    $base => $id_key,
    $definition->getDataTable() => $id_key,
    $definition->getRevisionDataTable() => $id_key,
  ];
  // Field + revision field tables use entity_id. Escape the underscores so
  // they are matched literally and not as LIKE single-char wildcards.
  $like = $database->escapeLike($base . '__') . '%';
  $revision_like = $database->escapeLike($base . '_revision__') . '%';
  $schema = $database->schema();

  $field_tables = $database->query(
    "SELECT table_name FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND (table_name LIKE :a ESCAPE '\\\\' OR table_name LIKE :b ESCAPE '\\\\')",
    [':a' => $like, ':b' => $revision_like]
  )->fetchCol();
  foreach ($field_tables as $table) {
    $candidates[$table] = 'entity_id';
  }

  foreach ($candidates as $table => $id_column) {
    if (!$table || !$schema->tableExists($table)) {
      continue;
    }
    $columns = $schema->fieldExists($table, 'langcode');
    if (!$columns) {
      continue;
    }
    $database->update($table)
      ->fields(['langcode' => $target])
      ->condition($id_column, $id)
      ->condition('langcode', $orphan_langs, 'IN')
      ->execute();
  }
}

/**
 * Applies key-order-only config changes that config import cannot see.
 *
 * Drupal's StorageComparer sorts config data recursively before comparing it,
 * so a change that only reorders array keys is invisible to `drush cim`: the
 * importer reports "no changes" and the active config keeps the old order.
 * Views read their column order from the order of the `fields` array and their
 * exposed-filter order from the order of the `filters` array, so those two
 * changes never reach a deployed site.
 *
 * This re-orders the active config to match the sync file without touching a
 * single value: keys are taken in the sync file's order, keys that exist only
 * in the active config are appended, and keys that exist only in the sync file
 * are skipped. That keeps every per-site config_split override intact — the
 * splits change values, not ordering.
 *
 * Runs per site (the deploy hook loops every site running `drush updb`) before
 * config import, and is idempotent: once the order matches, it does nothing.
 */
function bebbo_custom_general_post_update_apply_config_key_order(): string {
  // Configs whose ordering changed but whose values did not.
  $names = [
    // Content ID leads the keyword table; the country content list moved its
    // tall exposed filters to the end of the form.
    'views.view.keyword_term_count',
    'views.view.country_content_listing',
  ];

  $sync = \Drupal::service('config.storage.sync');
  $config_factory = \Drupal::configFactory();
  $reordered = [];

  foreach ($names as $name) {
    $source = $sync->read($name);
    if (!$source) {
      continue;
    }
    $config = $config_factory->getEditable($name);
    $active = $config->getRawData();
    if (!$active) {
      continue;
    }
    $ordered = _bebbo_custom_general_order_like($active, $source);
    // === on arrays is key-order sensitive, which is exactly the difference
    // being fixed here.
    if ($ordered === $active) {
      continue;
    }
    $config->setData($ordered)->save();
    $reordered[] = $name;
  }

  return $reordered
    ? 'Re-ordered config keys to match the sync files: ' . implode(', ', $reordered) . '.'
    : 'All listed configs already match the sync key order.';
}

/**
 * Re-orders an array's keys to match a source array, keeping every value.
 *
 * @param array $data
 *   The active config data.
 * @param array $source
 *   The sync config data whose key order should be adopted.
 *
 * @return array
 *   The active data with the source's key order applied recursively.
 */
function _bebbo_custom_general_order_like(array $data, array $source): array {
  $ordered = [];
  foreach ($source as $key => $source_value) {
    if (!array_key_exists($key, $data)) {
      continue;
    }
    $ordered[$key] = (is_array($data[$key]) && is_array($source_value))
      ? _bebbo_custom_general_order_like($data[$key], $source_value)
      : $data[$key];
  }
  // Keys the sync file does not know about (per-site split additions) keep
  // their relative order at the end.
  foreach ($data as $key => $value) {
    if (!array_key_exists($key, $ordered)) {
      $ordered[$key] = $value;
    }
  }
  return $ordered;
}
