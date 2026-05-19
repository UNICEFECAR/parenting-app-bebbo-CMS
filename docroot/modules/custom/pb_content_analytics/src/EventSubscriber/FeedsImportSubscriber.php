<?php

namespace Drupal\pb_content_analytics\EventSubscriber;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\feeds\Event\EntityEvent;
use Drupal\feeds\Event\FeedsEvents;
use Drupal\feeds\Event\ImportFinishedEvent;
use Drupal\pb_content_analytics\Service\AnalyticsSyncService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sets analytics timestamp on feed items and logs completed imports.
 */
class FeedsImportSubscriber implements EventSubscriberInterface {

  protected const ANALYTICS_FEED_TYPES = [
    'content_analytics_import',
    'analytics_import_video_article',
    'analytics_import_course',
    'analytics_import_activities',
  ];

  public function __construct(
    protected readonly AnalyticsSyncService $syncService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      FeedsEvents::PROCESS_ENTITY_PRESAVE => 'onEntityPresave',
      FeedsEvents::IMPORT_FINISHED => 'onImportFinished',
    ];
  }

  /**
   * Sets the analytics updated timestamp before each entity is saved.
   */
  public function onEntityPresave(EntityEvent $event): void {
    $feed = $event->getFeed();
    if (!in_array($feed->getType()->id(), self::ANALYTICS_FEED_TYPES, TRUE)) {
      return;
    }

    $entity = $event->getEntity();
    if (!$entity instanceof FieldableEntityInterface || $entity->getEntityTypeId() !== 'node') {
      return;
    }

    $entity->set('field_analytics_updated', time());
  }

  /**
   * Logs a sync result when an analytics feed import completes.
   */
  public function onImportFinished(ImportFinishedEvent $event): void {
    $feed = $event->getFeed();
    if (!in_array($feed->getType()->id(), self::ANALYTICS_FEED_TYPES, TRUE)) {
      return;
    }

    $item_count = (int) $feed->get('item_count')->value;
    $this->syncService->logSyncResult([
      'processed' => $item_count,
      'updated' => $item_count,
      'skipped' => 0,
    ], 'feeds');
  }

}
