<?php

namespace Drupal\bebbo_serializer\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\PublicStream;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for fixing file path references on multisite instances.
 *
 * On non-default sites (Bangladesh, Turkey, etc.), body HTML may contain
 * /sites/default/files/ references that should point to the site-specific
 * files directory. This command copies files and rewrites HTML paths.
 */
class FilePathsFixCommands extends DrushCommands {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * Fields to scan for file path references, keyed by content type.
   *
   * NULL means the field applies to all content types.
   */
  private const FIELD_MAP = [
    'body' => NULL,
    'field_answer_part_2' => ['faq'],
    'field_message' => ['guide'],
    'field_references_and_comments' => ['article', 'video_article'],
  ];

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    FileSystemInterface $file_system,
  ) {
    parent::__construct();
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
  }

  /**
   * Fix /sites/default/files/ references in body fields for a content type.
   *
   * Copies physical files to the current site's files directory and rewrites
   * HTML paths. Must be run on a non-default site (e.g., Bangladesh).
   *
   * @param string $content_type
   *   The machine name of the content type to process.
   * @param array $options
   *   Command options.
   *
   * @command file-paths:fix
   * @aliases fpf
   * @option limit Process only this many nodes.
   * @option dry-run Report what would change without modifying anything.
   * @option taxonomy Also process taxonomy term descriptions (growth_introductory).
   * @usage drush file-paths:fix article
   *   Fix file paths for all published articles.
   * @usage drush fpf activities --limit=10 --dry-run
   *   Dry-run for first 10 activities nodes.
   * @usage drush fpf article --taxonomy
   *   Fix articles and also growth_introductory taxonomy terms.
   */
  public function fix(
    string $content_type,
    array $options = [
      'limit' => NULL,
      'dry-run' => FALSE,
      'taxonomy' => FALSE,
    ],
  ): void {
    $currentBasePath = PublicStream::basePath();

    if ($currentBasePath === 'sites/default/files') {
      $this->logger()->notice('Running on the default site — nothing to fix.');
      return;
    }

    $oldPrefix = '/sites/default/files/';
    $newPrefix = '/' . $currentBasePath . '/';
    $dryRun = $options['dry-run'];

    $this->processNodes($content_type, $oldPrefix, $newPrefix, $options);

    if ($options['taxonomy']) {
      $this->processTaxonomyTerms($oldPrefix, $newPrefix, $dryRun);
    }
  }

  /**
   * Processes nodes of a given content type.
   */
  private function processNodes(string $contentType, string $oldPrefix, string $newPrefix, array $options): void {
    $storage = $this->entityTypeManager->getStorage('node');
    $dryRun = $options['dry-run'];

    $typeStorage = $this->entityTypeManager->getStorage('node_type');
    if (!$typeStorage->load($contentType)) {
      $this->logger()->error("Content type '{$contentType}' does not exist.");
      return;
    }

    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $contentType)
      ->condition('status', 1);

    if (!empty($options['limit'])) {
      $query->range(0, (int) $options['limit']);
    }

    $nids = $query->execute();
    if (empty($nids)) {
      $this->logger()->notice("No published nodes found for '{$contentType}'.");
      return;
    }

    $total = count($nids);
    $updated = 0;
    $skipped = 0;
    $filesCopied = 0;
    $filesMissing = 0;

    $this->logger()->notice("Processing {$total} '{$contentType}' nodes" . ($dryRun ? ' (dry-run)' : '') . '...');

    $fieldsForType = $this->getFieldsForContentType($contentType);

    foreach ($storage->loadMultiple($nids) as $node) {
      $nodeUpdated = FALSE;

      foreach ($node->getTranslationLanguages() as $langcode => $language) {
        $translation = $node->getTranslation($langcode);

        foreach ($fieldsForType as $fieldName) {
          if (!$translation->hasField($fieldName)) {
            continue;
          }

          $fieldItem = $translation->get($fieldName);
          $value = $fieldItem->value ?? '';
          $summary = $fieldItem->summary ?? '';
          $valueHasPrefix = !empty($value) && strpos($value, $oldPrefix) !== FALSE;
          $summaryHasPrefix = !empty($summary) && strpos($summary, $oldPrefix) !== FALSE;

          if (!$valueHasPrefix && !$summaryHasPrefix) {
            continue;
          }

          if ($dryRun) {
            $matchCount = substr_count($value, $oldPrefix) + substr_count($summary, $oldPrefix);
            $this->logger()->notice("  Node {$node->id()} ({$langcode}) [{$fieldName}]: {$matchCount} reference(s) to fix");
            $nodeUpdated = TRUE;
            continue;
          }

          if ($valueHasPrefix) {
            $copyResult = $this->copyReferencedFiles($value, $oldPrefix);
            $filesCopied += $copyResult['copied'];
            $filesMissing += $copyResult['missing'];
            $value = str_replace($oldPrefix, $newPrefix, $value);
          }

          if ($summaryHasPrefix) {
            $copyResult = $this->copyReferencedFiles($summary, $oldPrefix);
            $filesCopied += $copyResult['copied'];
            $filesMissing += $copyResult['missing'];
            $summary = str_replace($oldPrefix, $newPrefix, $summary);
          }

          $translation->set($fieldName, [
            'value' => $value,
            'summary' => $summary,
            'format' => $fieldItem->format ?? 'full_html',
          ]);
          $nodeUpdated = TRUE;
        }
      }

      if (!$nodeUpdated) {
        $skipped++;
        continue;
      }

      if (!$dryRun) {
        $node->setSyncing(TRUE);
        $node->save();
      }
      $updated++;
    }

    $label = $dryRun ? ' [DRY-RUN]' : '';
    $this->logger()->success("{$contentType}: {$updated} updated, {$skipped} skipped, {$total} total, {$filesCopied} files copied, {$filesMissing} files missing{$label}");
  }

  /**
   * Processes growth_introductory taxonomy term descriptions.
   */
  private function processTaxonomyTerms(string $oldPrefix, string $newPrefix, bool $dryRun): void {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');

    $tids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'growth_introductory')
      ->execute();

    if (empty($tids)) {
      $this->logger()->notice('No growth_introductory terms found.');
      return;
    }

    $total = count($tids);
    $updated = 0;
    $filesCopied = 0;
    $filesMissing = 0;

    $this->logger()->notice("Processing {$total} growth_introductory terms" . ($dryRun ? ' (dry-run)' : '') . '...');

    foreach ($storage->loadMultiple($tids) as $term) {
      $termUpdated = FALSE;

      foreach ($term->getTranslationLanguages() as $langcode => $language) {
        $translation = $term->getTranslation($langcode);
        $description = $translation->get('description')->value ?? '';

        if (empty($description) || strpos($description, $oldPrefix) === FALSE) {
          continue;
        }

        if ($dryRun) {
          $matchCount = substr_count($description, $oldPrefix);
          $this->logger()->notice("  Term {$term->id()} ({$langcode}): {$matchCount} reference(s) to fix");
          $termUpdated = TRUE;
          continue;
        }

        $copyResult = $this->copyReferencedFiles($description, $oldPrefix);
        $filesCopied += $copyResult['copied'];
        $filesMissing += $copyResult['missing'];

        $newDescription = str_replace($oldPrefix, $newPrefix, $description);
        $translation->set('description', [
          'value' => $newDescription,
          'format' => $translation->get('description')->format ?? 'full_html',
        ]);
        $termUpdated = TRUE;
      }

      if (!$termUpdated) {
        continue;
      }

      if (!$dryRun) {
        $term->setSyncing(TRUE);
        $term->save();
      }
      $updated++;
    }

    $label = $dryRun ? ' [DRY-RUN]' : '';
    $this->logger()->success("growth_introductory: {$updated} updated, {$total} total, {$filesCopied} files copied, {$filesMissing} files missing{$label}");
  }

  /**
   * Returns the list of fields to scan for a given content type.
   *
   * @return string[]
   *   Field machine names.
   */
  private function getFieldsForContentType(string $contentType): array {
    $fields = [];
    foreach (self::FIELD_MAP as $fieldName => $applicableTypes) {
      if ($applicableTypes === NULL || in_array($contentType, $applicableTypes, TRUE)) {
        $fields[] = $fieldName;
      }
    }
    return $fields;
  }

  /**
   * Extracts file paths from HTML and copies them to the current site's dir.
   *
   * @return array
   *   Associative array with 'copied' and 'missing' counts.
   */
  private function copyReferencedFiles(string $html, string $oldPrefix): array {
    $copied = 0;
    $missing = 0;

    preg_match_all(
      '/' . preg_quote($oldPrefix, '/') . '([^\s"\'<>]+)/',
      $html,
      $matches
    );

    if (empty($matches[1])) {
      return ['copied' => 0, 'missing' => 0];
    }

    $seen = [];
    foreach ($matches[1] as $relativePath) {
      // Strip query strings (e.g., ?itok=...).
      $relativePath = strtok($relativePath, '?') ?: $relativePath;
      // URL-decode for filesystem matching.
      $relativePath = urldecode($relativePath);

      if (isset($seen[$relativePath])) {
        continue;
      }
      $seen[$relativePath] = TRUE;

      $sourcePath = DRUPAL_ROOT . '/sites/default/files/' . $relativePath;
      $destUri = 'public://' . $relativePath;

      if (!file_exists($sourcePath)) {
        $this->logger()->warning("    Source file missing: {$sourcePath}");
        $missing++;
        continue;
      }

      // Skip if already exists at destination.
      $destRealPath = $this->fileSystem->realpath($destUri);
      if ($destRealPath && file_exists($destRealPath)) {
        continue;
      }

      // Ensure destination directory exists.
      $destDir = dirname($destUri);
      $this->fileSystem->prepareDirectory(
        $destDir,
        FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS
      );

      try {
        $this->fileSystem->copy($sourcePath, $destUri);
        $copied++;
      }
      catch (\Exception $e) {
        $this->logger()->warning("    Failed to copy {$relativePath}: {$e->getMessage()}");
        $missing++;
      }
    }

    return ['copied' => $copied, 'missing' => $missing];
  }

}
