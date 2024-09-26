<?php

declare(strict_types = 1);

namespace Drupal\entity_share_cron\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\entity_share_cron\EntityShareCronServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Imports entities from Entity Share channels.
 *
 * @QueueWorker(
 *     id = "entity_share_cron_pending",
 *     title = @Translation("Entity Share Cron"),
 *     cron = {"time" = 10}
 * )
 */
class EntityShareCronPending extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Entity Share Cron service.
   *
   * @var \Drupal\entity_share_cron\EntityShareCronServiceInterface
   */
  protected EntityShareCronServiceInterface $service;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\entity_share_cron\EntityShareCronServiceInterface $service
   *   Entity Share Cron service.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityShareCronServiceInterface $service,
    LoggerInterface $logger
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->service = $service;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    // @phpstan-ignore-next-line
    return new static($configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_share_cron'),
      $container->get('logger.channel.entity_share_cron')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    /** @var array $data */
    $remote_id = $data['remote_id'];
    $channel_id = $data['channel_id'];
    $url = $data['url'];
    $this->logger->info('Importing entities from channel %channel_id from remote %remote_id.', [
      '%channel_id' => $channel_id,
      '%remote_id' => $remote_id,
    ]);
    $this->service->sync($remote_id, $channel_id, $url);
  }

}
