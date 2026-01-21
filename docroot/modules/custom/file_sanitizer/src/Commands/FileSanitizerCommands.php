<?php

namespace Drupal\file_sanitizer\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
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

}
