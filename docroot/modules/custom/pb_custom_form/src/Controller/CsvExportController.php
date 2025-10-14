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
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;

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
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The private temp store factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

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
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The private temp store factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RemoteManagerInterface $remote_manager, LoggerChannelFactoryInterface $logger_factory, MessengerInterface $messenger, RequestStack $request_stack, FileSystemInterface $file_system, PrivateTempStoreFactory $temp_store_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->remoteManager = $remote_manager;
    // Get a logger channel specific to this module.
    $this->logger = $logger_factory->get('pb_custom_form');
    $this->messenger = $messenger;
    $this->requestStack = $request_stack;
    $this->fileSystem = $file_system;
    $this->tempStoreFactory = $temp_store_factory;
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
      $container->get('request_stack'),
      $container->get('file_system'),
      $container->get('tempstore.private')
    );
  }

  /**
   * Downloads entity data from remote API as CSV using batch processing.
   */
  public function downloadBatch() {
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

      // Get import config if provided.
      if (!empty($import_config_id)) {
        $import_config = $this->entityTypeManager->getStorage('import_config')->load($import_config_id);
        if ($import_config) {
          EntityShareUtility::getMaxSize($import_config, $channel_id, $channels_infos);
        }
      }

      // Prepare URL for API request.
      $channel_url = $channels_infos[$channel_id]['url'];
      if (empty($channel_url)) {
        $this->messenger()->addError($this->t('Channel URL is empty.'));
        return $this->redirect('entity_share_client.admin_content_pull_form');
      }

      // Get base URLs dynamically.
      $local_base = $request->getSchemeAndHttpHost();
      $remote_base = $this->getRemoteBaseUrl($selected_remote, $channel_url);

      // Store parameters in temp store for batch operations.
      $temp_store = $this->tempStoreFactory->get('pb_custom_form_csv_export');
      $temp_store->set('export_params', [
        'channel_id' => $channel_id,
        'remote_id' => $remote_id,
        'channel_url' => $channel_url,
        'local_base' => $local_base,
        'remote_base' => $remote_base,
        'selected_remote' => $selected_remote,
        'channels_infos' => $channels_infos,
      ]);

      // First, get total count to determine if we need batch processing.
      $total_count = $this->getTotalItemCount($selected_remote, $channel_url);

      // If small dataset, use regular download method.
      if ($total_count <= 300) {
        return $this->download();
      }

      // Create batch operations.
      $batch_builder = new BatchBuilder();
      $batch_builder
        ->setTitle($this->t('Exporting CSV data'))
        ->setInitMessage($this->t('Preparing CSV export...'))
        ->setProgressMessage($this->t('Processing @current of @total items.'))
        ->setErrorMessage($this->t('An error occurred during CSV export.'));

      // Add batch operations for data fetching.
      // Items per batch.
      $batch_size = 50;
      $total_pages = ceil($total_count / $batch_size);

      for ($page = 1; $page <= $total_pages; $page++) {
        $batch_builder->addOperation([$this, 'batchFetchData'], [$page, $batch_size]);
      }

      // Add final operation to create and serve the CSV file.
      $batch_builder->addOperation([$this, 'batchCreateCsv'], []);

      // Set finish callback.
      $batch_builder->setFinishCallback([$this, 'batchFinished']);

      // Set the batch.
      batch_set($batch_builder->toArray());

      // Redirect to batch processing page.
      return batch_process('pb_custom_form.csv_export_download');

    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Error setting up CSV export: @message', ['@message' => $e->getMessage()]));
      return $this->redirect('entity_share_client.admin_content_pull_form');
    }
  }

  /**
   * Downloads entity data from remote API as CSV for small datasets.
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
          break;
        }

      } while ($has_more_data);

      // Convert map back to sequential array for downstream processing.
      $all_data = array_values($all_data_map);

      // Finished pagination. Total items available in $all_data.
      if (empty($all_data)) {
        $this->messenger()->addWarning($this->t('No data found for the selected channel.'));
        return $this->redirect('entity_share_client.admin_content_pull_form');
      }

      $json = ['data' => $all_data];

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
      }

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

  /**
   * Gets the total count of items for the given channel.
   *
   * @param object $selected_remote
   *   The selected remote website.
   * @param string $channel_url
   *   The channel URL.
   *
   * @return int
   *   The total count of items.
   */
  protected function getTotalItemCount($selected_remote, $channel_url) {
    try {
      $parsed_url = UrlHelper::parse($channel_url);
      if (!isset($parsed_url['query'])) {
        $parsed_url['query'] = [];
      }

      // Get first page with limit 1 to get total count from meta.
      $parsed_url['query']['page']['limit'] = 1;
      $parsed_url['query']['page']['offset'] = 0;

      $query = UrlHelper::buildQuery($parsed_url['query']);
      $prepared_url = $parsed_url['path'] . '?' . $query;

      $response = $this->remoteManager->jsonApiRequest($selected_remote, 'GET', $prepared_url);
      $json = Json::decode((string) $response->getBody());

      // Try to get count from meta information.
      if (isset($json['meta']['count'])) {
        return (int) $json['meta']['count'];
      }

      // Fallback: estimate based on first page.
      // Conservative estimate.
      return 300;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to get total count: @error', ['@error' => $e->getMessage()]);
      // Conservative fallback.
      return 300;
    }
  }

  /**
   * Gets the remote base URL.
   *
   * @param object $selected_remote
   *   The selected remote website.
   * @param string $channel_url
   *   The channel URL.
   *
   * @return string
   *   The remote base URL.
   */
  protected function getRemoteBaseUrl($selected_remote, $channel_url) {
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
      $remote_base = 'https://bebo.app.ddev.site';
      $this->logger->warning('Could not determine remote base URL, using hardcoded fallback');
    }

    return $remote_base;
  }

  /**
   * Batch operation to fetch data for a specific page.
   *
   * @param int $page
   *   The page number.
   * @param int $batch_size
   *   The batch size.
   * @param array $context
   *   The batch context.
   */
  public function batchFetchData($page, $batch_size, &$context) {
    $temp_store = $this->tempStoreFactory->get('pb_custom_form_csv_export');
    $export_params = $temp_store->get('export_params');

    if (empty($export_params)) {
      $context['finished'] = 1;
      return;
    }

    try {
      $selected_remote = $export_params['selected_remote'];
      $channel_url = $export_params['channel_url'];

      $parsed_url = UrlHelper::parse($channel_url);
      if (!isset($parsed_url['query'])) {
        $parsed_url['query'] = [];
      }

      $parsed_url['query']['page']['limit'] = $batch_size;
      $parsed_url['query']['page']['offset'] = ($page - 1) * $batch_size;

      $query = UrlHelper::buildQuery($parsed_url['query']);
      $prepared_url = $parsed_url['path'] . '?' . $query;

      $response = $this->remoteManager->jsonApiRequest($selected_remote, 'GET', $prepared_url);
      $json = Json::decode((string) $response->getBody());

      if (!empty($json['data'])) {
        // Store data in temp store for this page.
        $temp_store->set("page_data_$page", $json['data']);

        // Initialize CSV file if this is the first page.
        if ($page === 1) {
          $this->initializeCsvFile($export_params);
        }

        // Process and append data to CSV file.
        $this->processAndAppendCsvData($json['data'], $export_params, $page);
      }

      $context['message'] = $this->t('Processed page @page', ['@page' => $page]);
      $context['finished'] = 0;
    }
    catch (\Exception $e) {
      $this->logger->error('Batch fetch failed for page @page: @error', [
        '@page' => $page,
        '@error' => $e->getMessage(),
      ]);
      $context['finished'] = 1;
    }
  }

  /**
   * Batch operation to create and serve the final CSV file.
   *
   * @param array $context
   *   The batch context.
   */
  public function batchCreateCsv(&$context) {
    $temp_store = $this->tempStoreFactory->get('pb_custom_form_csv_export');
    $export_params = $temp_store->get('export_params');

    if (empty($export_params)) {
      $context['finished'] = 1;
      return;
    }

    try {
      // Get the temporary CSV file path.
      $temp_file_path = $temp_store->get('csv_file_path');

      if (empty($temp_file_path) || !file_exists($temp_file_path)) {
        $context['finished'] = 1;
        return;
      }

      // Read the CSV content.
      $csv_content = file_get_contents($temp_file_path);

      if (empty($csv_content)) {
        $context['finished'] = 1;
        return;
      }

      // Generate filename.
      $filename = $this->generateFilename($export_params, $temp_store);

      // Store the CSV content and filename for download.
      $context['results']['csv_content'] = $csv_content;
      $context['results']['filename'] = $filename;
      $context['results']['export_params'] = $export_params;

      $context['finished'] = 1;
    }
    catch (\Exception $e) {
      $this->logger->error('Batch CSV creation failed: @error', ['@error' => $e->getMessage()]);
      $context['finished'] = 1;
    }
  }

  /**
   * Batch finish callback to store results for download.
   *
   * @param bool $success
   *   Whether the batch completed successfully.
   * @param array $results
   *   The batch results.
   * @param array $operations
   *   The batch operations.
   */
  public function batchFinished($success, $results, $operations) {
    if ($success && isset($results['csv_content']) && isset($results['filename'])) {
      // Store the CSV content and filename in temp store for download.
      $temp_store = $this->tempStoreFactory->get('pb_custom_form_csv_export');
      $temp_store->set('csv_content', $results['csv_content']);
      $temp_store->set('csv_filename', $results['filename']);

      $this->messenger->addStatus($this->t('CSV export completed successfully. Click the download link to get your file.'));
    }
    else {
      $this->messenger->addError($this->t('CSV export failed.'));
    }
  }

  /**
   * Downloads the completed CSV file after batch processing.
   */
  public function downloadCompleted() {
    $temp_store = $this->tempStoreFactory->get('pb_custom_form_csv_export');
    $csv_content = $temp_store->get('csv_content');
    $filename = $temp_store->get('csv_filename');

    if (empty($csv_content) || empty($filename)) {
      $this->messenger->addError($this->t('No CSV file available for download.'));
      return $this->redirect('entity_share_client.admin_content_pull_form');
    }

    // Create and return the CSV response.
    $response = new Response($csv_content);
    $disposition = $response->headers->makeDisposition(
      ResponseHeaderBag::DISPOSITION_ATTACHMENT,
      $filename
    );
    $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $response->headers->set('Content-Disposition', $disposition);
    $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');

    // Clean up temporary files.
    $this->cleanupTempFiles($temp_store);

    return $response;
  }

  /**
   * Initializes the CSV file with headers.
   *
   * @param array $export_params
   *   The export parameters.
   */
  protected function initializeCsvFile($export_params) {
    $temp_store = $this->tempStoreFactory->get('pb_custom_form_csv_export');

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

    // Create CSV content with proper line endings.
    $csv_content = '';
    $csv_content .= implode(',', array_map(function ($field) {
      return '"' . str_replace('"', '""', $field) . '"';
    }, $headers)) . "\r\n";

    // Create temporary file.
    $temp_dir = $this->fileSystem->getTempDirectory();
    $temp_file_path = $temp_dir . '/csv_export_' . uniqid() . '.csv';

    file_put_contents($temp_file_path, $csv_content);
    $temp_store->set('csv_file_path', $temp_file_path);
  }

  /**
   * Processes and appends CSV data to the temporary file.
   *
   * @param array $data
   *   The data to process.
   * @param array $export_params
   *   The export parameters.
   * @param int $page
   *   The current page number.
   */
  protected function processAndAppendCsvData($data, $export_params, $page) {
    $temp_store = $this->tempStoreFactory->get('pb_custom_form_csv_export');
    $temp_file_path = $temp_store->get('csv_file_path');

    if (empty($temp_file_path) || !file_exists($temp_file_path)) {
      return;
    }

    $csv_rows = [];

    foreach ($data as $item) {
      $row = $this->buildCsvRow($item, $export_params);
      if ($row) {
        $csv_rows[] = $row;
      }
    }

    // Append rows to CSV file.
    $csv_content = '';
    foreach ($csv_rows as $row) {
      $csv_row = [];
      foreach ($row as $value) {
        // Escape quotes and wrap in quotes.
        $csv_row[] = '"' . str_replace('"', '""', (string) $value) . '"';
      }
      $csv_content .= implode(',', $csv_row) . "\r\n";
    }

    file_put_contents($temp_file_path, $csv_content, FILE_APPEND | LOCK_EX);
  }

  /**
   * Builds a CSV row from an API item.
   *
   * @param array $item
   *   The API item.
   * @param array $export_params
   *   The export parameters.
   *
   * @return array|null
   *   The CSV row or NULL if invalid.
   */
  protected function buildCsvRow($item, $export_params) {
    if (empty($item['id'])) {
      return NULL;
    }

    // Extract values from the API response.
    $remote_uuid = $item['id'];
    $local_id = $item['attributes']['drupal_internal__nid'] ??
      $item['attributes']['drupal_internal__tid'] ??
      $item['attributes']['drupal_internal__uid'] ?? '';

    $title = $item['attributes']['title'] ??
      $item['attributes']['name'] ??
      $item['attributes']['label'] ?? '';

    $raw_type = $item['type'] ?? '';
    $type = $raw_type;
    $bundle = $raw_type;

    if (strpos($raw_type, '--') !== FALSE) {
      $type_parts = explode('--', $raw_type);
      $type = $type_parts[0];
      $bundle = $type_parts[1];
    }

    $changed_date = $item['attributes']['changed'] ?? '';
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

    // Check if this entity exists locally by UUID.
    $local_entity_id = '';
    try {
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
    catch (\Exception $e) {
      // If we can't check for local entity, that's okay.
    }

    // Format synchronization status.
    if (!empty($local_entity_id)) {
      $status_text = 'Entities synchronized';
    }
    else {
      $status_text = 'Entities not synchronized';
    }

    // Determine the entity path based on type.
    $entity_path = '/node/';
    if (strpos($type, 'taxonomy_term') !== FALSE) {
      $entity_path = '/taxonomy/term/';
    }
    elseif (strpos($type, 'user') !== FALSE) {
      $entity_path = '/user/';
    }

    // Build the row.
    return [
      // Remote ID as URL or UUID fallback.
      !empty($local_id) ? $export_params['remote_base'] . $entity_path . $local_id : $remote_uuid,
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
  }

  /**
   * Generates the filename for the CSV export.
   *
   * @param array $export_params
   *   The export parameters.
   * @param object $temp_store
   *   The temporary store.
   *
   * @return string
   *   The generated filename.
   */
  protected function generateFilename($export_params, $temp_store) {
    $filename_parts = ['entity-share-export'];
    if (!empty($export_params['channel_id'])) {
      $filename_parts[] = 'channel-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $export_params['channel_id']);
    }
    if (!empty($export_params['remote_id'])) {
      $filename_parts[] = 'remote-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $export_params['remote_id']);
    }
    $filename_parts[] = date('Y-m-d-H-i-s');

    // Get total count from temp store.
    $total_count = $temp_store->get('total_count') ?: 0;
    $filename_parts[] = $total_count . '-records';

    return implode('-', $filename_parts) . '.csv';
  }

  /**
   * Cleans up temporary files.
   *
   * @param object $temp_store
   *   The temporary store.
   */
  protected function cleanupTempFiles($temp_store) {
    $temp_file_path = $temp_store->get('csv_file_path');
    if ($temp_file_path && file_exists($temp_file_path)) {
      unlink($temp_file_path);
    }

    // Clear all temp store data.
    $temp_store->delete('export_params');
    $temp_store->delete('csv_file_path');
    $temp_store->delete('total_count');
    $temp_store->delete('csv_content');
    $temp_store->delete('csv_filename');
  }

}
