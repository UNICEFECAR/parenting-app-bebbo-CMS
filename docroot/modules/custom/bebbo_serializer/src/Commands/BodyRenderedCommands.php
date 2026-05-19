<?php

namespace Drupal\bebbo_serializer\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\bebbo_serializer\Service\BodyImageProcessor;
use Drush\Commands\DrushCommands;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Drush commands for populating field_body_rendered from body HTML.
 */
class BodyRenderedCommands extends DrushCommands {

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
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * Constructs a BodyRenderedCommands object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\bebbo_serializer\Service\BodyImageProcessor $processor
   *   The body image processor service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    BodyImageProcessor $processor,
    RequestStack $request_stack,
  ) {
    parent::__construct();
    $this->entityTypeManager = $entity_type_manager;
    $this->processor = $processor;
    $this->requestStack = $request_stack;
  }

  /**
   * Populate field_body_rendered from body HTML for a content type.
   *
   * @param string $content_type
   *   The machine name of the content type to process.
   * @param array $options
   *   Command options (limit, dry-run).
   *
   * @command body-rendered:populate
   * @aliases brp
   * @option limit Process only this many nodes.
   * @option dry-run Report counts without saving.
   * @usage drush body-rendered:populate article
   *   Populate rendered body for all published articles.
   * @usage drush brp activities --limit=10 --dry-run
   *   Dry-run for first 10 activities nodes.
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
      ->condition('type', $content_type)
      ->condition('status', 1);

    if (!empty($options['limit'])) {
      $query->range(0, (int) $options['limit']);
    }

    $nids = $query->execute();
    if (empty($nids)) {
      $this->logger()->notice("No published nodes found for '{$content_type}'.");
      return;
    }

    $total = count($nids);
    $updated = 0;
    $skipped = 0;
    $dry_run = $options['dry-run'];
    $baseUrl = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost();

    $this->logger()->notice("Processing {$total} '{$content_type}' nodes" . ($dry_run ? ' (dry-run)' : '') . '...');

    foreach ($storage->loadMultiple($nids) as $node) {
      if (!$node->hasField('body') || !$node->hasField('field_body_rendered')) {
        $skipped++;
        continue;
      }

      $translationUpdated = FALSE;

      foreach ($node->getTranslationLanguages() as $langcode => $language) {
        $translation = $node->getTranslation($langcode);
        $body = $translation->get('body')->value ?? '';

        if (empty($body)) {
          continue;
        }

        $bodyFormat = $translation->get('body')->format ?? 'full_html';

        if ($dry_run) {
          $hasDrupalMedia = strpos($body, '<drupal-media') !== FALSE;
          $this->logger()->notice("  Node {$node->id()} ({$langcode}): body length " . strlen($body) . ($hasDrupalMedia ? ' [has <drupal-media>]' : ''));
          $translationUpdated = TRUE;
          continue;
        }

        $rendered = $this->processor->render($body, $bodyFormat, $langcode, $baseUrl);

        $translation->set('field_body_rendered', [
          'value' => $rendered,
          'format' => 'full_html',
        ]);
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

    $this->logger()->success("{$content_type}: {$updated} updated, {$skipped} skipped (no body), {$total} total" . ($dry_run ? ' [DRY-RUN]' : ''));
  }

}
