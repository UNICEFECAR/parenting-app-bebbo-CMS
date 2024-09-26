<?php

declare(strict_types = 1);

namespace Drupal\entity_share_cron\HookHandler;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\State\StateInterface;
use Drupal\entity_share_cron\EntityShareCronServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Hook handler for the cron() hook.
 */
class CronHookHandler implements ContainerInjectionInterface {

  /**
   * The state ID.
   */
  public const STATE_ID = 'entity_share_cron.cron_last_run';

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The entity share cron service.
   *
   * @var \Drupal\entity_share_cron\EntityShareCronServiceInterface
   */
  protected EntityShareCronServiceInterface $entityShareCron;

  /**
   * CronHookHandler constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param \Drupal\entity_share_cron\EntityShareCronServiceInterface $entity_share_cron
   *   The entity share cron service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    StateInterface $state,
    LoggerInterface $logger,
    EntityShareCronServiceInterface $entity_share_cron
  ) {
    $this->configFactory = $config_factory;
    $this->state = $state;
    $this->logger = $logger;
    $this->entityShareCron = $entity_share_cron;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    // @phpstan-ignore-next-line
    return new static(
      $container->get('config.factory'),
      $container->get('state'),
      $container->get('logger.channel.entity_share_cron'),
      $container->get('entity_share_cron')
    );
  }

  /**
   * Enqueue channels for import if the new execution interval is reached.
   */
  public function process(): void {
    $config = $this->configFactory->get('entity_share_cron.settings');
    $now = \time();
    /** @var int $interval */
    $interval = $config->get('cron_interval');
    /** @var int $last_run */
    $last_run = $this->state->get(self::STATE_ID) ? $this->state->get(self::STATE_ID) : -99999;

    // Checks the interval since the last synchronization.
    if ($now < $last_run + $interval) {
      return;
    }

    // Enqueues enabled remotes and channels for synchronization.
    $remotes_config = $config->get('remotes');
    if (\is_array($remotes_config)) {
      foreach ($remotes_config as $remote_id => $remote_config) {
        // Checks if synchronization of this remote is enabled.
        if (empty($remote_config['enabled'])) {
          continue;
        }

        $channels_config = $remote_config['channels'] ?? [];
        foreach ($channels_config as $channel_id => $channel_config) {
          // Checks if synchronization of this channel is enabled.
          if ($channel_config['enabled']) {
            // Enqueues the channel for synchronization.
            $this->logger->info('Enqueuing channel %channel_id from remote %remote_id for synchronization.', [
              '%channel_id' => $channel_id,
              '%remote_id' => $remote_id,
            ]);
            $this->entityShareCron->enqueue($remote_id, $channel_id, NULL);
          }
        }
      }
    }

    // Updates last run timestamp.
    $this->state->set(self::STATE_ID, $now);
  }

}
