<?php

namespace Drupal\file_sanitizer\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
use Drupal\file_sanitizer\Service\FilenameSanitizer;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for sanitizing unsafe file names.
 */
class FileSanitizerCommands extends DrushCommands {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The filename sanitizer service.
   *
   * @var \Drupal\file_sanitizer\Service\FilenameSanitizer
   */
  protected FilenameSanitizer $sanitizer;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    Connection $database,
    FileSystemInterface $file_system,
    FilenameSanitizer $sanitizer,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
    $this->fileSystem = $file_system;
    $this->sanitizer = $sanitizer;
  }

  /**
   * Scan managed files, report unsafe filenames, optionally rename.
   *
   * @command file-sanitizer:scan
   * @option execute Rename files in-place (default: dry-run)
   * @option limit Limit number of files processed
   */
  public function scan(
    array $options = [
      'execute' => FALSE,
      'limit' => NULL,
    ],
  ): void {

    $timestamp = date('Ymd_His');
    $report_dir = 'public://file-sanitizer';

    $this->fileSystem->prepareDirectory(
      $report_dir,
      FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS
    );

    $inventory_csv = "$report_dir/all_files_$timestamp.csv";
    $infected_csv = "$report_dir/infected_files_$timestamp.csv";
    $validation_csv = "$report_dir/validation_errors_$timestamp.csv";

    $inventory = fopen($this->fileSystem->realpath($inventory_csv), 'w');
    $infected = fopen($this->fileSystem->realpath($infected_csv), 'w');
    $validation = fopen($this->fileSystem->realpath($validation_csv), 'w');

    fputcsv($inventory, [
      'fid',
      'filename',
      'sanitized_filename',
      'uri',
      'status',
    ]);

    fputcsv($infected, [
      'fid',
      'original_filename',
      'sanitized_filename',
      'uri_before',
      'uri_after',
      'action',
    ]);

    fputcsv($validation, [
      'fid',
      'filename',
      'uri',
      'error_type',
      'error_message',
    ]);

    // ✅ Build query to fetch ONLY files used in field_cover_image.
    // This joins: file_managed -> media__field_media_image ->
    // node__field_cover_image -> file_usage.
    // Only processes files that are actively referenced (count > 0).
    $query = $this->database->select('file_managed', 'fm');
    $query->fields('fm', ['fid', 'filename', 'uri', 'filemime', 'filesize', 'status']);

    // Join to media__field_media_image to link files to media entities.
    $query->innerJoin('media__field_media_image', 'mfi', 'fm.fid = mfi.field_media_image_target_id');

    // Join to node__field_cover_image to ensure file is used as cover image.
    $query->innerJoin('node__field_cover_image', 'ncf', 'mfi.entity_id = ncf.field_cover_image_target_id');

    // Join to file_usage to verify file is actively used.
    $query->innerJoin('file_usage', 'fu', 'fm.fid = fu.fid');

    // Filter conditions.
    // Only files with active usage.
    $query->condition('fu.count', 0, '>');
    $query->condition('fm.uri', 'temporary://%', 'NOT LIKE');
    $query->condition('fm.uri', 'public://styles/%', 'NOT LIKE');
    $query->condition('fm.uri', 'public://oembed_thumbnails/%', 'NOT LIKE');

    // Get distinct files (same file might be used multiple times)
    $query->distinct();
    $query->orderBy('fm.fid');

    if (!empty($options['limit'])) {
      $query->range(0, (int) $options['limit']);
    }

    foreach ($query->execute() as $record) {
      $sanitized = $this->sanitizer->sanitize($record->filename);

      fputcsv($inventory, [
        $record->fid,
        $record->filename,
        $sanitized,
        $record->uri,
        $record->status,
      ]);

      // ✅ SKIP - No sanitization needed
      if ($record->filename === $sanitized) {
        continue;
      }

      $old_uri = $record->uri;
      $new_uri = dirname($old_uri) . '/' . $sanitized;

      fputcsv($infected, [
        $record->fid,
        $record->filename,
        $sanitized,
        $old_uri,
        $new_uri,
        $options['execute'] ? 'RENAMED' : 'DRY-RUN',
      ]);

      if ($options['execute']) {
        $this->renameFileSafely(
          (int) $record->fid,
          $sanitized,
          $validation
        );
      }
    }

    fclose($inventory);
    fclose($infected);
    fclose($validation);

    $this->logger()->success('File scan completed.');
    $this->logger()->success("Inventory: $inventory_csv");
    $this->logger()->success("Infected files: $infected_csv");
    $this->logger()->success("Validation errors: $validation_csv");
    $this->logger()->warning(
      'After execution you MUST run: drush image:flush --all && drush cr'
    );
  }

  /**
   * Rename a file ONLY if the physical move succeeds.
   *
   * @param int $fid
   *   File ID.
   * @param string $new_filename
   *   Sanitized filename.
   * @param resource $validation_log
   *   File handle for validation error log.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   */
  protected function renameFileSafely(int $fid, string $new_filename, $validation_log): bool {
    $file = $this->entityTypeManager->getStorage('file')->load($fid);
    if (!$file) {
      fputcsv($validation_log, [
        $fid,
        '',
        '',
        'file_entity_not_found',
        'File entity could not be loaded from database',
      ]);
      $this->logger()->warning("SKIP fid {$fid}: File entity not found");
      return FALSE;
    }

    $old_uri = $file->getFileUri();
    $new_uri = dirname($old_uri) . '/' . $new_filename;

    if ($old_uri === $new_uri) {
      return FALSE;
    }

    // ✅ PRE-FLIGHT CHECK 1: Source file exists on disk
    $source_realpath = $this->fileSystem->realpath($old_uri);
    if (!$source_realpath || !file_exists($source_realpath)) {
      fputcsv($validation_log, [
        $fid,
        $file->getFilename(),
        $old_uri,
        'source_not_found',
        "Source file does not exist: {$old_uri}",
      ]);
      $this->logger()->warning("SKIP fid {$fid}: Source file not found at {$old_uri}");
      return FALSE;
    }

    // ✅ PRE-FLIGHT CHECK 2: Destination doesn't already exist
    $dest_realpath = $this->fileSystem->realpath($new_uri);
    if ($dest_realpath && file_exists($dest_realpath)) {
      fputcsv($validation_log, [
        $fid,
        $file->getFilename(),
        $old_uri,
        'destination_exists',
        "Destination already exists: {$new_uri}",
      ]);
      $this->logger()->warning("SKIP fid {$fid}: Destination already exists: {$new_uri}");
      return FALSE;
    }

    // ✅ PRE-FLIGHT CHECK 3: Directory is writable
    $directory = dirname($source_realpath);
    if (!is_writable($directory)) {
      fputcsv($validation_log, [
        $fid,
        $file->getFilename(),
        $old_uri,
        'permission_denied',
        "Directory not writable: {$directory}",
      ]);
      $this->logger()->error("SKIP fid {$fid}: Permission denied - directory not writable: {$directory}");
      return FALSE;
    }

    try {
      $final_uri = $this->fileSystem->move(
        $old_uri,
        $new_uri,
        FileExists::Rename
      );

      // ✅ Update entity ONLY after successful move
      $file->setFilename(basename($final_uri));
      $file->setFileUri($final_uri);
      $file->save();

      $this->logger()->success("✓ fid {$fid}: {$file->getFilename()} → {$new_filename}");
      return TRUE;
    }
    catch (\Throwable $e) {
      fputcsv($validation_log, [
        $fid,
        $file->getFilename(),
        $old_uri,
        'move_failed',
        $e->getMessage(),
      ]);
      $this->logger()->error("SKIP fid {$fid}: Move failed - {$e->getMessage()}");
      return FALSE;
    }
  }

  /**
   * Detect files where extension doesn't match actual MIME type.
   *
   * @command file-sanitizer:scan-mime
   * @option execute Fix mismatched extensions (default: dry-run)
   * @option limit Limit number of files processed
   */
  public function scanMimeTypeMismatch(
    array $options = [
      'execute' => FALSE,
      'limit' => NULL,
    ],
  ): void {

    $timestamp = date('Ymd_His');
    $report_dir = 'public://file-sanitizer';

    $this->fileSystem->prepareDirectory(
      $report_dir,
      FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS
    );

    $mismatch_csv = "$report_dir/mime_mismatches_$timestamp.csv";
    $validation_csv = "$report_dir/mime_validation_errors_$timestamp.csv";

    $mismatch = fopen($this->fileSystem->realpath($mismatch_csv), 'w');
    $validation = fopen($this->fileSystem->realpath($validation_csv), 'w');

    fputcsv($mismatch, [
      'fid',
      'filename',
      'current_extension',
      'db_mime_type',
      'actual_mime_type',
      'correct_extension',
      'uri_before',
      'uri_after',
      'action',
    ]);

    fputcsv($validation, [
      'fid',
      'filename',
      'uri',
      'error_type',
      'error_message',
    ]);

    // MIME type to valid extensions mapping
    // (can have multiple valid extensions).
    $mimeToExtensions = [
      'image/jpeg' => ['jpg', 'jpeg'],
      'image/jpg' => ['jpg', 'jpeg'],
      'image/png' => ['png'],
      'image/gif' => ['gif'],
      'image/webp' => ['webp'],
      'image/svg+xml' => ['svg'],
      'application/pdf' => ['pdf'],
      'text/csv' => ['csv'],
      'text/plain' => ['txt'],
      'application/msword' => ['doc'],
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
      'application/vnd.ms-excel' => ['xls'],
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
      'video/mp4' => ['mp4'],
      'video/webm' => ['webm'],
    ];

    // Build query to fetch files (same as scan command).
    $query = $this->database->select('file_managed', 'fm');
    $query->fields('fm', ['fid', 'filename', 'uri', 'filemime', 'filesize', 'status']);

    // Join to media__field_media_image to link files to media entities.
    $query->innerJoin('media__field_media_image', 'mfi', 'fm.fid = mfi.field_media_image_target_id');

    // Join to node__field_cover_image to ensure file is used as cover image.
    $query->innerJoin('node__field_cover_image', 'ncf', 'mfi.entity_id = ncf.field_cover_image_target_id');

    // Join to file_usage to verify file is actively used.
    $query->innerJoin('file_usage', 'fu', 'fm.fid = fu.fid');

    // Filter conditions.
    $query->condition('fu.count', 0, '>');
    $query->condition('fm.uri', 'temporary://%', 'NOT LIKE');
    $query->condition('fm.uri', 'public://styles/%', 'NOT LIKE');
    $query->condition('fm.uri', 'public://oembed_thumbnails/%', 'NOT LIKE');

    $query->distinct();
    $query->orderBy('fm.fid');

    if (!empty($options['limit'])) {
      $query->range(0, (int) $options['limit']);
    }

    $total_scanned = 0;
    $mismatches_found = 0;
    $files_fixed = 0;

    foreach ($query->execute() as $record) {
      $total_scanned++;

      // Get real path of file.
      $realpath = $this->fileSystem->realpath($record->uri);
      if (!$realpath || !file_exists($realpath)) {
        fputcsv($validation, [
          $record->fid,
          $record->filename,
          $record->uri,
          'file_not_found',
          'Physical file does not exist on disk',
        ]);
        continue;
      }

      // Detect actual MIME type using PHP's fileinfo.
      $finfo = new \finfo(FILEINFO_MIME_TYPE);
      $actualMime = $finfo->file($realpath);

      // Get current extension.
      $pathInfo = pathinfo($record->filename);
      $currentExtension = strtolower($pathInfo['extension'] ?? '');
      $baseName = $pathInfo['filename'] ?? '';

      // Get valid extensions for this MIME type.
      $validExtensions = $mimeToExtensions[$actualMime] ?? NULL;

      // Skip if we can't map the MIME type.
      if ($validExtensions === NULL) {
        continue;
      }

      // Check if current extension is NOT in the list of valid extensions.
      // This means there's a true mismatch
      // (e.g., .jpg file that's actually .webp).
      if (!in_array($currentExtension, $validExtensions, TRUE)) {
        $mismatches_found++;

        // Use the first valid extension as the correct one.
        $correctExtension = $validExtensions[0];
        $old_uri = $record->uri;
        $new_filename = $baseName . '.' . $correctExtension;
        $new_uri = dirname($old_uri) . '/' . $new_filename;

        fputcsv($mismatch, [
          $record->fid,
          $record->filename,
          $currentExtension,
          $record->filemime,
          $actualMime,
          $correctExtension,
          $old_uri,
          $new_uri,
          $options['execute'] ? 'FIXED' : 'DRY-RUN',
        ]);

        if ($options['execute']) {
          if ($this->fixMimeTypeMismatch(
            (int) $record->fid,
            $new_filename,
            $actualMime,
            $validation
          )) {
            $files_fixed++;
          }
        }
      }
    }

    fclose($mismatch);
    fclose($validation);

    $this->logger()->success("Files scanned: {$total_scanned}");
    $this->logger()->warning("Mismatches found: {$mismatches_found}");

    if ($options['execute']) {
      $this->logger()->success("Files fixed: {$files_fixed}");
    }

    $this->logger()->success("Mismatch report: $mismatch_csv");
    $this->logger()->success("Validation errors: $validation_csv");

    if ($options['execute']) {
      $this->logger()->warning(
        'After execution you MUST run: drush image:flush --all && drush cr'
      );
    }
  }

  /**
   * Fix a file with mismatched MIME type extension.
   *
   * @param int $fid
   *   File ID.
   * @param string $new_filename
   *   Corrected filename with proper extension.
   * @param string $actual_mime
   *   Actual MIME type detected.
   * @param resource $validation_log
   *   File handle for validation error log.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   */
  protected function fixMimeTypeMismatch(
    int $fid,
    string $new_filename,
    string $actual_mime,
    $validation_log,
  ): bool {
    $file = $this->entityTypeManager->getStorage('file')->load($fid);
    if (!$file) {
      fputcsv($validation_log, [
        $fid,
        '',
        '',
        'file_entity_not_found',
        'File entity could not be loaded from database',
      ]);
      $this->logger()->warning("SKIP fid {$fid}: File entity not found");
      return FALSE;
    }

    $old_uri = $file->getFileUri();
    $new_uri = dirname($old_uri) . '/' . $new_filename;

    if ($old_uri === $new_uri) {
      return FALSE;
    }

    // PRE-FLIGHT CHECK 1: Source file exists on disk.
    $source_realpath = $this->fileSystem->realpath($old_uri);
    if (!$source_realpath || !file_exists($source_realpath)) {
      fputcsv($validation_log, [
        $fid,
        $file->getFilename(),
        $old_uri,
        'source_not_found',
        "Source file does not exist: {$old_uri}",
      ]);
      $this->logger()->warning("SKIP fid {$fid}: Source file not found at {$old_uri}");
      return FALSE;
    }

    // PRE-FLIGHT CHECK 2: Destination doesn't already exist.
    $dest_realpath = $this->fileSystem->realpath($new_uri);
    if ($dest_realpath && file_exists($dest_realpath)) {
      fputcsv($validation_log, [
        $fid,
        $file->getFilename(),
        $old_uri,
        'destination_exists',
        "Destination already exists: {$new_uri}",
      ]);
      $this->logger()->warning("SKIP fid {$fid}: Destination already exists: {$new_uri}");
      return FALSE;
    }

    // PRE-FLIGHT CHECK 3: Directory is writable.
    $directory = dirname($source_realpath);
    if (!is_writable($directory)) {
      fputcsv($validation_log, [
        $fid,
        $file->getFilename(),
        $old_uri,
        'permission_denied',
        "Directory not writable: {$directory}",
      ]);
      $this->logger()->error("SKIP fid {$fid}: Permission denied - directory not writable: {$directory}");
      return FALSE;
    }

    try {
      $final_uri = $this->fileSystem->move(
        $old_uri,
        $new_uri,
        FileExists::Rename
      );

      // Update entity ONLY after successful move.
      $file->setFilename(basename($final_uri));
      $file->setFileUri($final_uri);

      // Update MIME type in database to match actual type.
      $file->setMimeType($actual_mime);

      $file->save();

      $this->logger()->success("✓ fid {$fid}: {$file->getFilename()} (MIME updated to {$actual_mime})");
      return TRUE;
    }
    catch (\Throwable $e) {
      fputcsv($validation_log, [
        $fid,
        $file->getFilename(),
        $old_uri,
        'move_failed',
        $e->getMessage(),
      ]);
      $this->logger()->error("SKIP fid {$fid}: Move failed - {$e->getMessage()}");
      return FALSE;
    }
  }

  /**
   * Scan body-embedded media files for unsafe filenames, optionally rename.
   *
   * @param string $content_type
   *   The machine name of the content type to scan.
   * @param array $options
   *   Command options (execute, limit).
   *
   * @command file-sanitizer:scan-body
   * @aliases fssb
   * @option execute Rename files in-place (default: dry-run)
   * @option limit Limit number of nodes processed
   * @usage drush file-sanitizer:scan-body activities
   *   Dry-run scan of body-embedded media in activities nodes.
   * @usage drush file-sanitizer:scan-body activities --execute
   *   Rename unsafe filenames in activities body-embedded media.
   */
  public function scanBody(
    string $content_type,
    array $options = [
      'execute' => FALSE,
      'limit' => NULL,
    ],
  ): void {
    $storage = $this->entityTypeManager->getStorage('node');

    // Verify content type exists.
    $type_storage = $this->entityTypeManager->getStorage('node_type');
    if (!$type_storage->load($content_type)) {
      $this->logger()->error("Content type '{$content_type}' does not exist.");
      return;
    }

    // Set up CSV reports.
    $timestamp = date('Ymd_His');
    $report_dir = 'public://file-sanitizer';
    $this->fileSystem->prepareDirectory(
      $report_dir,
      FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS
    );

    $report_csv = "$report_dir/body_files_{$content_type}_{$timestamp}.csv";
    $validation_csv = "$report_dir/body_validation_{$content_type}_{$timestamp}.csv";

    $report = fopen($this->fileSystem->realpath($report_csv), 'w');
    $validation = fopen($this->fileSystem->realpath($validation_csv), 'w');

    fputcsv($report, [
      'nid',
      'fid',
      'original_filename',
      'sanitized_filename',
      'uri_before',
      'uri_after',
      'action',
    ]);

    fputcsv($validation, [
      'fid',
      'filename',
      'uri',
      'error_type',
      'error_message',
    ]);

    // Query published nodes of the given content type.
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $content_type)
      ->condition('status', 1);

    if (!empty($options['limit'])) {
      $query->range(0, (int) $options['limit']);
    }

    $nids = $query->execute();
    if (empty($nids)) {
      $this->logger()->notice("No published nodes found for '{$content_type}'.");
      fclose($report);
      fclose($validation);
      return;
    }

    $mediaStorage = $this->entityTypeManager->getStorage('media');
    $total_nodes = count($nids);
    $files_checked = 0;
    $files_needing_rename = 0;
    $files_renamed = 0;
    $seen_fids = [];

    $this->logger()->notice("Processing {$total_nodes} '{$content_type}' nodes" . ($options['execute'] ? '' : ' (dry-run)') . '...');

    foreach ($storage->loadMultiple($nids) as $node) {
      if (!$node->hasField('body')) {
        continue;
      }

      // Collect media UUIDs from all translations of this node.
      $all_uuids = [];
      foreach ($node->getTranslationLanguages() as $langcode => $language) {
        $translation = $node->getTranslation($langcode);
        $body = $translation->get('body')->value ?? '';
        if (empty($body) || strpos($body, '<drupal-media') === FALSE) {
          continue;
        }

        preg_match_all(
          '/data-entity-uuid="([a-f0-9\-]+)"/i',
          $body,
          $matches
        );

        foreach ($matches[1] ?? [] as $uuid) {
          $all_uuids[$uuid] = TRUE;
        }
      }

      if (empty($all_uuids)) {
        continue;
      }

      // Load media entities by UUID.
      $mediaEntities = $mediaStorage->loadByProperties([
        'uuid' => array_keys($all_uuids),
      ]);

      foreach ($mediaEntities as $media) {
        if ($media->bundle() !== 'image' || !$media->hasField('field_media_image')) {
          continue;
        }

        $file = $media->get('field_media_image')->entity;
        if (!$file instanceof FileInterface) {
          continue;
        }

        $fid = (int) $file->id();

        // Skip files already processed in this run.
        if (isset($seen_fids[$fid])) {
          continue;
        }
        $seen_fids[$fid] = TRUE;
        $files_checked++;

        $original = $file->getFilename();
        $sanitized = $this->sanitizer->sanitize($original);

        if ($original === $sanitized) {
          continue;
        }

        $files_needing_rename++;
        $old_uri = $file->getFileUri();
        $new_uri = dirname($old_uri) . '/' . $sanitized;

        fputcsv($report, [
          $node->id(),
          $fid,
          $original,
          $sanitized,
          $old_uri,
          $new_uri,
          $options['execute'] ? 'RENAMED' : 'DRY-RUN',
        ]);

        if ($options['execute']) {
          if ($this->renameFileSafely($fid, $sanitized, $validation)) {
            $files_renamed++;
          }
        }
      }
    }

    fclose($report);
    fclose($validation);

    $this->logger()->success("{$content_type}: {$total_nodes} nodes scanned, {$files_checked} files checked, {$files_needing_rename} need rename" . ($options['execute'] ? ", {$files_renamed} renamed" : ' [DRY-RUN]'));
    $this->logger()->success("Report: $report_csv");
    $this->logger()->success("Validation errors: $validation_csv");

    if ($options['execute']) {
      $this->logger()->warning(
        'After execution you MUST run: drush image:flush --all && drush cr'
      );
    }
  }

}
