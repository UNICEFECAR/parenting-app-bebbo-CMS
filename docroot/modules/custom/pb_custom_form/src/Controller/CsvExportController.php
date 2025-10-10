<?php

namespace Drupal\pb_custom_form\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\UrlHelper;
use Drupal\entity_share\EntityShareUtility;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_share_client\Service\RemoteManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Provides CSV export for Entity Share data.
 */
class CsvExportController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The remote manager.
   *
   * @var \Drupal\entity_share_client\Service\RemoteManagerInterface
   */
  protected $remoteManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The logger channel for this module.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a CsvExportController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\entity_share_client\Service\RemoteManagerInterface $remote_manager
   *   The remote manager service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RemoteManagerInterface $remote_manager, LoggerChannelFactoryInterface $logger_factory, MessengerInterface $messenger, RequestStack $request_stack) {
    $this->entityTypeManager = $entity_type_manager;
    $this->remoteManager = $remote_manager;
    // Get a logger channel specific to this module.
    $this->logger = $logger_factory->get('pb_custom_form');
    $this->messenger = $messenger;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('entity_share_client.remote_manager'),
      $container->get('logger.factory'),
      $container->get('messenger'),
      $container->get('request_stack')
    );
  }

  /**
   * Downloads entity data from remote API as CSV.
   */
  public function download() {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request) {
      $this->messenger->addError($this->t('Unable to retrieve the current request.'));
      return $this->redirect('entity_share_client.admin_content_pull_form');
    }

    // Get filter parameters from the request.
    $channel_id = $request->query->get('channel');
    $remote_id = $request->query->get('remote');
    $import_config_id = $request->query->get('import_config');

    if (empty($channel_id) || empty($remote_id)) {
      $this->messenger()->addError($this->t('Channel and Remote website are required for CSV export.'));
      return $this->redirect('entity_share_client.admin_content_pull_form');
    }

    try {
      // Load remote website.
      $remote_websites = $this->entityTypeManager->getStorage('remote')->loadMultiple();
      if (!isset($remote_websites[$remote_id])) {
        $this->messenger()->addError($this->t('Remote website not found.'));
        return $this->redirect('entity_share_client.admin_content_pull_form');
      }
      $selected_remote = $remote_websites[$remote_id];

      // Get channels info.
      $channels_infos = $this->remoteManager->getChannelsInfos($selected_remote);
      if (!isset($channels_infos[$channel_id])) {
        $this->messenger()->addError($this->t('Channel not found.'));
        return $this->redirect('entity_share_client.admin_content_pull_form');
      }

      // Get import config if provided. We don't use the max size here, but
      // keep the retrieval for potential future use.
      if (!empty($import_config_id)) {
        $import_config = $this->entityTypeManager->getStorage('import_config')->load($import_config_id);
        if ($import_config) {
          // Intentionally not used here; import logic may use this later.
          EntityShareUtility::getMaxSize($import_config, $channel_id, $channels_infos);
        }
      }

      // Prepare URL for API request.
      $channel_url = $channels_infos[$channel_id]['url'];
      if (empty($channel_url)) {
        $this->messenger()->addError($this->t('Channel URL is empty.'));
        return $this->redirect('entity_share_client.admin_content_pull_form');
      }

      $parsed_url = UrlHelper::parse($channel_url);

      // Initialize query array if it doesn't exist.
      if (!isset($parsed_url['query'])) {
        $parsed_url['query'] = [];
      }

      // Fetch ALL data by making multiple requests if needed.
      $all_data = [];
      $page_number = 0;
      // JSON:API defaults to 50.
      $limit = 50;
      // Safety limit - can handle up to 5000 items (100 pages × 50 items)
      $max_pages = 100;

      // Use a map keyed by UUID to deduplicate efficiently.
      $all_data_map = [];

      do {
        $page_number++;
        $parsed_url['query']['page']['limit'] = $limit;
        $parsed_url['query']['page']['offset'] = ($page_number - 1) * $limit;

        $query = UrlHelper::buildQuery($parsed_url['query']);
        $prepared_url = $parsed_url['path'] . '?' . $query;

        try {
          $response = $this->remoteManager->jsonApiRequest($selected_remote, 'GET', $prepared_url);
          $json = Json::decode((string) $response->getBody());
        }
        catch (\Exception $api_exception) {
          $this->logger->error('CSV Export: API request failed on page @page: @error', [
            '@page' => $page_number,
            '@error' => $api_exception->getMessage(),
          ]);
          $this->messenger->addError($this->t('Failed to fetch data from remote API: @message', ['@message' => $api_exception->getMessage()]));
          return $this->redirect('entity_share_client.admin_content_pull_form');
        }

        if (empty($json['data'])) {
          break;
        }

        // Merge results into a map keyed by UUID to avoid expensive
        // repeated deduplication (keeps last occurrence of an ID).
        $current_batch_size = count($json['data']);
        foreach ($json['data'] as $item) {
          if (!empty($item['id'])) {
            $all_data_map[$item['id']] = $item;
          }
        }

        // Multiple ways to detect if there are more pages.
        $has_more_data = FALSE;

        // Method 1: Check for links.next.
        if (isset($json['links']['next'])) {
          $has_more_data = TRUE;
        }
        // Method 2: If we got a full page, there might be more.
        elseif ($current_batch_size >= $limit) {
          $has_more_data = TRUE;
        }

        // Safety guards.
        if ($page_number >= $max_pages) {
          $this->logger->warning('CSV Export: Reached page limit (@max), stopping for safety', ['@max' => $max_pages]);
          break;
        }

      } while ($has_more_data);

      // Convert map back to sequential array for downstream processing.
      $all_data = array_values($all_data_map);

      $this->logger->info('CSV Export: Finished pagination. Total items: @total from @pages pages', [
        '@total' => count($all_data),
        '@pages' => $page_number,
      ]);

      if (empty($all_data)) {
        $this->messenger()->addWarning($this->t('No data found for the selected channel.'));
        return $this->redirect('entity_share_client.admin_content_pull_form');
      }

      $json = ['data' => $all_data];

      // Log the number of items found.
      $item_count = count($json['data']);
      $this->logger->info('CSV Export: Found @count items for channel @channel on remote @remote', [
        '@count' => $item_count,
        '@channel' => $channel_id,
        '@remote' => $remote_id,
      ]);

      // Define specific headers as requested.
      $headers = [
        'Remote ID',
        'Local ID',
        'Label',
        'Type',
        'Bundle',
        'Remote entity changed date',
        'Status',
      ];

      // Get base URLs dynamically.
      $local_base = $request->getSchemeAndHttpHost();

      // Get remote base URL from the selected remote website.
      $remote_base = '';
      if ($selected_remote && method_exists($selected_remote, 'get')) {
        try {
          $remote_url_field = $selected_remote->get('url');
          if ($remote_url_field && method_exists($remote_url_field, 'getString')) {
            $remote_base = rtrim($remote_url_field->getString(), '/');
          }
          elseif ($remote_url_field && method_exists($remote_url_field, 'getValue')) {
            $url_value = $remote_url_field->getValue();
            if (is_array($url_value) && isset($url_value[0]['value'])) {
              $remote_base = rtrim($url_value[0]['value'], '/');
            }
            elseif (is_string($url_value)) {
              $remote_base = rtrim($url_value, '/');
            }
          }
        }
        catch (\Exception $e) {
          // If URL extraction fails, we'll use the fallback below.
        }
      }

      // Fallback: try to extract from channel URL.
      if (empty($remote_base)) {
        $parsed_channel = parse_url($channel_url);
        if (isset($parsed_channel['scheme']) && isset($parsed_channel['host'])) {
          $remote_base = $parsed_channel['scheme'] . '://' . $parsed_channel['host'];
          if (isset($parsed_channel['port'])) {
            $remote_base .= ':' . $parsed_channel['port'];
          }
        }
      }

      // Final fallback if we can't get the remote URL.
      if (empty($remote_base)) {
        // Hardcoded fallback.
        $remote_base = 'https://bebo.app.ddev.site';
        $this->logger->warning('Could not determine remote base URL, using hardcoded fallback');
      }

      // Log the URLs being used.
      $this->logger->info('CSV Export URLs - Local: @local, Remote: @remote', [
        '@local' => $local_base,
        '@remote' => $remote_base,
      ]);

      $csv_data = [];

      // Build data rows with specific mapping.
      foreach ($json['data'] as $item) {
        // Extract values from the API response.
        $remote_uuid = $item['id'] ?? '';

        // Try different fields for local ID depending on entity type.
        $local_id = $item['attributes']['drupal_internal__nid'] ??
          $item['attributes']['drupal_internal__tid'] ??
          $item['attributes']['drupal_internal__uid'] ?? '';

        $title = $item['attributes']['title'] ??
                $item['attributes']['name'] ??
                $item['attributes']['label'] ?? '';

        $raw_type = $item['type'] ?? '';

        // Clean up the type format (remove JSON:API format like "node--article"
        // to get the entity type and bundle separately).
        $type = $raw_type;
        $bundle = $raw_type;

        if (strpos($raw_type, '--') !== FALSE) {
          $type_parts = explode('--', $raw_type);
          // e.g., "node" from "node--article".
          $type = $type_parts[0];
          // e.g., "article" from "node--article".
          $bundle = $type_parts[1];
        }

        $changed_date = $item['attributes']['changed'] ?? '';
        // Status attribute not currently used in CSV export.
        // $status = $item['attributes']['status'] ?? '';.
        // Format the changed date.
        $formatted_date = '';
        if (!empty($changed_date)) {
          try {
            $date = new \DateTime($changed_date);
            $formatted_date = $date->format('Y-m-d H:i:s');
          }
          catch (\Exception $e) {
            $formatted_date = $changed_date;
          }
        }

        // Check if this entity exists locally by UUID first.
        $local_entity_id = '';
        try {
          if (!empty($remote_uuid)) {
            $entity_type = $type;
            if ($entity_type && $this->entityTypeManager->hasDefinition($entity_type)) {
              $storage = $this->entityTypeManager->getStorage($entity_type);
              $entities = $storage->loadByProperties(['uuid' => $remote_uuid]);
              if (!empty($entities)) {
                $local_entity = reset($entities);
                $local_entity_id = $local_entity->id();
              }
            }
          }
        }
        catch (\Exception $e) {
          // If we can't check for local entity, that's okay.
        }

        // Format synchronization status based on whether entity exists locally.
        if (!empty($local_entity_id)) {
          $status_text = 'Entities synchronized';
        }
        else {
          $status_text = 'Entities not synchronized';
        }

        // Determine the entity path based on type.
        // Default for nodes.
        $entity_path = '/node/';
        if (strpos($type, 'taxonomy_term') !== FALSE) {
          $entity_path = '/taxonomy/term/';
        }
        elseif (strpos($type, 'user') !== FALSE) {
          $entity_path = '/user/';
        }

        // Local lookup performed above; continue building CSV row.
        // Build the row.
        $row = [
        // Remote ID as URL or UUID fallback.
          !empty($local_id) ? $remote_base . $entity_path . $local_id : $remote_uuid,
        // Local ID (empty if not imported yet)
          $local_entity_id ?: 'Not imported',
        // Label.
          $title,
        // Type.
          $type,
        // Bundle.
          $bundle,
        // Remote entity changed date.
          $formatted_date,
        // Status.
          $status_text,
        ];

        $csv_data[] = $row;
      }

      // Create CSV content with proper line endings.
      $csv_content = '';

      // Add header row.
      $csv_content .= implode(',', array_map(function ($field) {
        return '"' . str_replace('"', '""', $field) . '"';
      }, $headers)) . "\r\n";

      // Add data rows.
      foreach ($csv_data as $row) {
        $csv_row = [];
        foreach ($row as $value) {
          // Escape quotes and wrap in quotes.
          $csv_row[] = '"' . str_replace('"', '""', (string) $value) . '"';
        }
        $csv_content .= implode(',', $csv_row) . "\r\n";
      }

      // Generate filename with filters.
      $filename_parts = ['entity-share-export'];
      if (!empty($channel_id)) {
        $filename_parts[] = 'channel-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $channel_id);
      }
      if (!empty($remote_id)) {
        $filename_parts[] = 'remote-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $remote_id);
      }
      $filename_parts[] = date('Y-m-d-H-i-s');
      $filename_parts[] = count($csv_data) . '-records';
      $filename = implode('-', $filename_parts) . '.csv';

      // Return response with CSV download headers.
      $response = new Response($csv_content);
      $disposition = $response->headers->makeDisposition(
        ResponseHeaderBag::DISPOSITION_ATTACHMENT,
        $filename
      );
      $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
      $response->headers->set('Content-Disposition', $disposition);
      $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');

      return $response;

    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Error exporting CSV: @message', ['@message' => $e->getMessage()]));
      return $this->redirect('entity_share_client.admin_content_pull_form');
    }
  }

}
