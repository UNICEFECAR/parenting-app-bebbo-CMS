<?php

declare(strict_types = 1);

namespace Drupal\entity_share_cron;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_client\ImportContext;
use Drupal\entity_share_client\Service\ImportServiceInterface;

/**
 * Entity Share Cron service.
 */
class EntityShareCronService implements EntityShareCronServiceInterface {

  /**
   * Module configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * Queue service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected QueueFactory $queueFactory;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Import service.
   *
   * @var \Drupal\entity_share_client\Service\ImportServiceInterface
   */
  protected ImportServiceInterface $importService;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   Queue service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\entity_share_client\Service\ImportServiceInterface $import_service
   *   Import service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    QueueFactory $queue_factory,
    EntityTypeManagerInterface $entity_type_manager,
    ImportServiceInterface $import_service
  ) {
    $this->config = $config_factory->get('entity_share_cron.settings');
    $this->queueFactory = $queue_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->importService = $import_service;
  }

  /**
   * {@inheritdoc}
   */
  public function enqueue($remote_id, $channel_id, $url): void {
    $queue = $this->queueFactory->get(EntityShareCronServiceInterface::PENDING_QUEUE_NAME);
    $item = [
      'remote_id' => $remote_id,
      'channel_id' => $channel_id,
      'url' => $url,
    ];
    $queue->createItem($item);
  }

  /**
   * {@inheritdoc}
   */
  public function sync($remote_id, $channel_id, $url): void {
    $page_limit = $this->config->get('page_limit');
    $channel_config = $this->getChannelConfig($remote_id, $channel_id);

    $import_context = new ImportContext($remote_id, $channel_id, $channel_config['import_config']);
    if (!$this->importService->prepareImport($import_context)) {
      return;
    }

    // Collects entities to import from each page.
    $data_to_import = [];
    $next_page = 1;
    if ($url === NULL) {
      $url = $this->importService->getRuntimeImportContext()->getChannelUrl();
    }
    while ($url) {
      // Performs request to get the list of entities.
      $page_data = $this->getPage($url);
      $data_to_import = \array_merge($data_to_import, $page_data['data']);
      ++$next_page;

      $url = $page_data['next'];
      if ($url && $page_limit != 0 && $next_page > $page_limit) {
        // Enqueues the next page after the limit.
        $this->enqueue($remote_id, $channel_id, $url);
        $url = FALSE;
      }
    }

    // Removes data to import according to enabled operations.
    $channel_config = $this->getChannelConfig($remote_id, $channel_id);
    if (empty($channel_config['operations']['create'])) {
      $this->filterDataToImport($data_to_import, TRUE);
    }
    if (empty($channel_config['operations']['update'])) {
      $this->filterDataToImport($data_to_import, FALSE);
    }

    // Imports the data.
    $this->importService->importEntityListData($data_to_import);
  }

  /**
   * Returns the page data to import entities.
   *
   * @param string $url
   *   The URL of the page.
   *
   * @return array
   *   An associative array with the following keys:
   *   - data => parsed JSON of entities to import.
   *   - next => URL of the next page.
   */
  protected function getPage($url): array {
    $data = [
      'data' => [],
      'next' => FALSE,
    ];

    $response = $this->importService->jsonApiRequest('GET', $url);
    $json = Json::decode((string) $response->getBody());
    if (!\is_array($json)) {
      return $data;
    }

    // Parses the JSON of entities to import.
    $data['data'] = EntityShareUtility::prepareData($json['data']);

    // Gets the URL of the next page.
    $data['next'] = !empty($json['links']['next']['href']) ? $json['links']['next']['href'] : FALSE;

    return $data;
  }

  /**
   * Filters the data to import by existing or non-existing entities.
   *
   * @param array $data
   *   The data to be filtered.
   * @param bool $keep_existing
   *   Keeps only existing entities if TRUE. Otherwise, only non-existing
   *   entities.
   */
  protected function filterDataToImport(array &$data, $keep_existing): void {
    // Groups entities by entity type before checking.
    $uuid_by_type = [];
    foreach ($data as $entity_data) {
      $parsed_type = \explode('--', $entity_data['type']);
      $entity_type = $parsed_type[0];
      $uuid = $entity_data['id'];
      $uuid_by_type[$entity_type][] = $uuid;
    }

    // Filters entities.
    $existing_uuids = [];
    foreach ($uuid_by_type as $entity_type => $uuids) {
      /** @var \Drupal\Core\Entity\EntityTypeInterface $definition */
      $definition = $this->entityTypeManager->getDefinition($entity_type);
      $uuid_property = $definition->getKey('uuid');

      // Gets existing entities from the list.
      $storage = $this->entityTypeManager->getStorage($entity_type);
      $existing_entities = $storage->loadByProperties([
        $uuid_property => $uuids,
      ]);

      // Adds existing UUIDs to list.
      foreach ($existing_entities as $entity) {
        $uuid = $entity->uuid();
        $existing_uuids[$uuid] = $uuid;
      }
    }

    // Filters data to be imported.
    $data_updated = [];
    foreach ($data as $entity_data) {
      $uuid = $entity_data['id'];
      if ($keep_existing && !empty($existing_uuids[$uuid])) {
        $data_updated[] = $entity_data;
      }
      elseif (!$keep_existing && empty($existing_uuids[$uuid])) {
        $data_updated[] = $entity_data;
      }
    }
    $data = $data_updated;
  }

  /**
   * Returns the settings of a channel.
   *
   * @param string $remote_id
   *   The ID of the remote the channel belongs to.
   * @param string $channel_id
   *   The ID of the channel.
   *
   * @return array
   *   Channel settings.
   */
  protected function getChannelConfig($remote_id, $channel_id): array {
    $settings = [];
    /** @var array $remotes */
    $remotes = $this->config->get('remotes');
    if (!empty($remotes[$remote_id]['channels'][$channel_id])) {
      $settings = $remotes[$remote_id]['channels'][$channel_id];
    }
    return $settings;
  }

}
