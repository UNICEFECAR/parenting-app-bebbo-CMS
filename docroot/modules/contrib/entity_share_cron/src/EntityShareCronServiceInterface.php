<?php

declare(strict_types = 1);

namespace Drupal\entity_share_cron;

/**
 * Entity Share Cron service.
 */
interface EntityShareCronServiceInterface {
  public const PENDING_QUEUE_NAME = 'entity_share_cron_pending';

  /**
   * Enqueues a channel for later synchronization.
   *
   * @param string $remote_id
   *   The ID of the remote the channel belongs to.
   * @param string $channel_id
   *   The ID of the channel to be enqueued.
   * @param null|string $url
   *   The url of the page to enqueue for import. NULL if starting from the
   *   first page.
   */
  public function enqueue($remote_id, $channel_id, $url): void;

  /**
   * Synchronizes entities starting from provided channel.
   *
   * @param string $remote_id
   *   The ID of the remote the channel belongs to.
   * @param string $channel_id
   *   The ID of the channel to be synchronized.
   * @param null|string $url
   *   The url of the page to enqueue for import. NULL if starting from the
   *   first page.
   */
  public function sync($remote_id, $channel_id, $url): void;

}
