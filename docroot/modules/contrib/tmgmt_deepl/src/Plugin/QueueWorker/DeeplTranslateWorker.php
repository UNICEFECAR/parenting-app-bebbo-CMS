<?php

declare(strict_types=1);

namespace Drupal\tmgmt_deepl\Plugin\QueueWorker;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\tmgmt_deepl\Plugin\tmgmt\Translator\DeeplTranslator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *  @QueueWorker(
 *    id = "deepl_translate_worker",
 *    title = @Translation("TMGMT Deepl translate queue worker"),
 *    cron = {"time" = 120}
 *  )
 */
class DeeplTranslateWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {
  use StringTranslationTrait;

  /**
   * The logger channel interface.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface|null
   */
  protected ?LoggerChannelInterface $logger;

  /**
   * The container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  private ContainerInterface $container;

  /**
   * {@inheritDoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ContainerInterface $container) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->container = $container;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): DeeplTranslateWorker {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container,
    );
  }

  /**
   * Job item translation handler via Cron.
   *
   * @param $data
   *   An associative array containing the following, passed from DeeplTranslator.
   *   [
   *     'job' => $job,
   *     'job_item' => $job,
   *     'q' => $q,
   *     'translation' => $translation,
   *     'keys_sequence' => $keys_sequence,
   *   ]
   *
   * @return void
   */
  public function processItem($data): void {
    try {
      $context = [];
      $job = $data['job'];
      $job_item = $data['job_item'];
      $q = $data['q'];
      $translation = $data['translation'];
      $keys_sequence = $data['keys_sequence'];

      // Simply run the regular batch operations here.
      // @todo do we still want to chunk this?
      DeeplTranslator::batchRequestTranslation($job, $q, $translation, $keys_sequence, $context);
      $context['results']['job_item'] = $job_item;
      DeeplTranslator::batchFinished(TRUE, $context['results'], []);
    }
    catch (\Exception $exception) {
      $this->logger()->error($this->t(
        'Unable to translate job item: @id, the following exception was thrown: @message',
        [
          '@id' => $job_item->id(),
          '@message' => $exception->getMessage()
        ],
      ));
    }
  }

  /**
   * Getter for the logger.
   *
   * @return \Drupal\Core\Logger\LoggerChannelInterface
   */
  public function logger(): LoggerChannelInterface {
    if (empty($this->logger)) {
      $this->logger = $this->container->get('logger.factory')->get('tmgmt_deepl');
    }
    return $this->logger;
  }

}
