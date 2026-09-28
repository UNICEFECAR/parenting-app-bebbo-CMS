<?php

namespace Drupal\bebbo_serializer\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\bebbo_serializer\Service\BodyImageProcessor;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for populating embedded images field from body HTML.
 */
class EmbeddedImagesCommands extends DrushCommands {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The body image processor service.
   *
   * @var \Drupal\bebbo_serializer\Service\BodyImageProcessor
   */
  protected BodyImageProcessor $processor;

  /**
   * Constructs an EmbeddedImagesCommands object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\bebbo_serializer\Service\BodyImageProcessor $processor
   *   The body image processor service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    BodyImageProcessor $processor,
  ) {
    parent::__construct();
    $this->entityTypeManager = $entity_type_manager;
    $this->processor = $processor;
  }

  /**
   * Populate field_embedded_images from body HTML for a content type.
   *
   * @param string $content_type
   *   The machine name of the content type to process.
   * @param array $options
   *   Command options (limit, dry-run).
   *
   * @command embedded-images:populate
   * @aliases eip
   * @option limit Process only this many nodes.
   * @option dry-run Report counts without saving.
   * @usage drush embedded-images:populate activities
   *   Populate embedded images for all activities nodes, in any moderation
   *   state.
   * @usage drush eip article --limit=10 --dry-run
   *   Dry-run for first 10 article nodes.
   */
  public function populate(
    string $content_type,
    array $options = [
      'limit' => NULL,
      'dry-run' => FALSE,
    ],
  ): void {
    $storage = $this->entityTypeManager->getStorage('node');

    // Verify the content type exists.
    $type_storage = $this->entityTypeManager->getStorage('node_type');
    if (!$type_storage->load($content_type)) {
      $this->logger()->error("Content type '{$content_type}' does not exist.");
      return;
    }

    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $content_type);

    if (!empty($options['limit'])) {
      $query->range(0, (int) $options['limit']);
    }

    $nids = $query->execute();
    if (empty($nids)) {
      $this->logger()->notice("No nodes found for '{$content_type}'.");
      return;
    }

    $total = count($nids);
    $updated = 0;
    $skipped = 0;
    $pending = 0;
    $dry_run = $options['dry-run'];

    $this->logger()->notice("Processing {$total} '{$content_type}' nodes" . ($dry_run ? ' (dry-run)' : '') . '...');

    foreach ($storage->loadMultiple($nids) as $node) {
      // Check that the node has both required fields.
      if (!$node->hasField('body') || !$node->hasField('field_embedded_images')) {
        $skipped++;
        continue;
      }

      // Saving the default revision of a node that has a pending revision
      // would push that pending revision out of the latest-revision slot and
      // lose the editor's work in progress. Leave those nodes alone.
      if ($storage->getLatestRevisionId($node->id()) != $node->getRevisionId()) {
        $pending++;
        continue;
      }

      $translationUpdated = FALSE;

      foreach ($node->getTranslationLanguages() as $langcode => $language) {
        $translation = $node->getTranslation($langcode);
        $body = $translation->get('body')->value ?? '';

        // A body without images leaves an empty list, which clears a stale
        // value, as the node presave hook does.
        $urls = [];
        if (strpos($body, '<drupal-media') !== FALSE || stripos($body, '<img') !== FALSE) {
          // Same 255 character limit as the node presave hook: a longer URL
          // aborts the save with an SQL truncation error.
          $urls = array_values(array_filter($this->processor->extractImageUrls($body), static fn(string $url): bool => mb_strlen($url) <= 255));
        }

        // Nothing to do when the stored value is already correct: saving
        // anyway would spend a revision on every node on every run.
        if ($urls === array_column($translation->get('field_embedded_images')->getValue(), 'value')) {
          continue;
        }

        if ($dry_run) {
          $this->logger()->notice("  Node {$node->id()} ({$langcode}): " . count($urls) . " image(s)");
        }
        else {
          $translation->set('field_embedded_images', $urls);
        }
        $translationUpdated = TRUE;
      }

      if (!$translationUpdated) {
        $skipped++;
        continue;
      }

      if (!$dry_run) {
        $node->setSyncing(TRUE);
        $node->save();
      }
      $updated++;
    }

    $this->logger()->success("{$content_type}: {$updated} updated, {$skipped} skipped (nothing to update), {$pending} skipped (pending revision), {$total} total" . ($dry_run ? ' [DRY-RUN]' : ''));
  }

}
