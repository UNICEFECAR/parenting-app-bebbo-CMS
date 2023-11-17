<?php

namespace Drupal\tmgmt_deepl\Plugin\tmgmt\Translator;

use Drupal\Component\Utility\Html;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\tmgmt\ContinuousTranslatorInterface;
use Drupal\tmgmt\Data;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\Entity\Translator;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt\JobItemInterface;
use Drupal\tmgmt\TMGMTException;
use Drupal\tmgmt\Translator\AvailableResult;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\tmgmt\TranslatorPluginBase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * DeepL translator base class.
 */
abstract class DeeplTranslator extends TranslatorPluginBase implements ContainerFactoryPluginInterface, ContinuousTranslatorInterface {

  use StringTranslationTrait;

  /**
   * Name of parameter that contains source string to be translated.
   *
   * @var string
   */
  protected static string $qParamName = 'text';

  /**
   * Max number of text queries for translation sent in one request.
   *
   * @var int
   */
  protected int $qChunkSize = 5;

  /**
   * Guzzle HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $client;

  /**
   * TMGMT data service.
   *
   * @var \Drupal\tmgmt\Data
   */
  protected Data $tmgmtData;

  /**
   * The queue object.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected QueueInterface $queue;

  /**
   * If the process is being run via cron or not.
   *
   * @var bool|null
   */
  protected ?bool $isCron;

  /**
   * Constructs a DeeplProTranslator object.
   *
   * @param \GuzzleHttp\ClientInterface $client
   *   The Guzzle HTTP client.
   * @param \Drupal\tmgmt\Data $tmgmt_data
   *   The Guzzle HTTP client.
   * @param \Drupal\Core\Queue\QueueInterface $queue
   *   The queue object.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(ClientInterface $client, Data $tmgmt_data, QueueInterface $queue, array $configuration, string $plugin_id, array $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->client = $client;
    $this->tmgmtData = $tmgmt_data;
    $this->queue = $queue;
    $this->isCron = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    // @phpstan-ignore-next-line
    return new static(
      $container->get('http_client'),
      $container->get('tmgmt.data'),
      $container->get('queue')->get('deepl_translate_worker', TRUE),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function checkAvailable(TranslatorInterface $translator): AvailableResult {
    if ($translator->getSetting('auth_key')) {
      return AvailableResult::yes();
    }

    return AvailableResult::no($this->t('@translator is not available. Make sure it is properly <a href=:configured>configured</a>.', [
      '@translator' => $translator->label(),
      ':configured' => $translator->toUrl()->toString(),
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public function requestTranslation(JobInterface $job): void {
    $this->requestJobItemsTranslation($job->getItems());
    if (!$job->isRejected()) {
      $job->submitted('The translation job has been submitted.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultRemoteLanguagesMappings(): array {
    return [
      'bg' => 'BG',
      'cs' => 'CS',
      'da' => 'DA',
      'de' => 'DE',
      'el' => 'EL',
      'en' => 'EN',
      'es' => 'ES',
      'et' => 'ET',
      'fi' => 'FI',
      'fr' => 'FR',
      'hu' => 'HU',
      'id' => 'ID',
      'it' => 'IT',
      'ja' => 'JA',
      'ko' => 'KO',
      'lt' => 'LT',
      'lv' => 'LV',
      'nb' => 'NB',
      'nl' => 'NL',
      'pl' => 'PL',
      'pt-br' => 'PT-BR',
      'pt-pt' => 'PT-PT',
      'ro' => 'RO',
      'ru' => 'RU',
      'sk' => 'SK',
      'sl' => 'SL',
      'sv' => 'SV',
      'tr' => 'TR',
      'uk' => 'UK',
      'zh' => 'ZH',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedRemoteLanguages(TranslatorInterface $translator): array {
    // Pre-defined array of available languages.
    return [
      'BG' => $this->t('Bulgarian'),
      'CS' => $this->t('Czech'),
      'DA' => $this->t('Danish'),
      'DE' => $this->t('German'),
      'EL' => $this->t('Greek'),
      'EN-GB' => $this->t('English (British)'),
      'EN-US' => $this->t('English (American)'),
      'EN' => $this->t('English'),
      'ES' => $this->t('Spanish'),
      'ET' => $this->t('Estonian'),
      'FI' => $this->t('Finnish'),
      'FR' => $this->t('French'),
      'HU' => $this->t('Hungarian'),
      'ID' => $this->t('Indonesian'),
      'IT' => $this->t('Italian'),
      'JA' => $this->t('Japanese'),
      'KO' => $this->t('Korean'),
      'LT' => $this->t('Lithuanian'),
      'LV' => $this->t('Latvian'),
      'NB' => $this->t('Norwegian (Bokmål)'),
      'NL' => $this->t('Dutch'),
      'PL' => $this->t('Polish'),
      'PT-PT' => $this->t('Portuguese (excluding Brazilian Portuguese)'),
      'PT-BR' => $this->t('Portuguese (Brazilian)'),
      'PT' => $this->t('Portuguese (deprecated, select PT-PT or PT-BR instead)'),
      'RO' => $this->t('Romanian'),
      'RU' => $this->t('Russian'),
      'SK' => $this->t('Slovak'),
      'SL' => $this->t('Slovenian'),
      'SV' => $this->t('Swedish'),
      'TR' => $this->t('Turkish'),
      'UK' => $this->t('Ukrainian'),
      'ZH' => $this->t('Chinese (simplified)'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedTargetLanguages(TranslatorInterface $translator, $source_language): array {
    $languages = $this->getSupportedRemoteLanguages($translator);

    // There are no language pairs, any supported language can be translated
    // into the others. If the source language is part of the languages,
    // then return them all, just remove the source language.
    if (array_key_exists($source_language, $languages)) {
      unset($languages[$source_language]);
      return $languages;
    }

    return [];
  }

  /**
   * Source language mapping, cause not all sources as supported as target.
   *
   * @param string $source_lang
   *   The selected source language of the job item.
   *
   * @return string
   *   Fixed language mapping based on DeepL specification.
   */
  public static function fixSourceLanguageMappings(string $source_lang): string {
    $language_mapping = [
      'EN-GB' => 'EN',
      'EN-US' => 'EN',
      'PT-BR' => 'PT',
      'PT-PT' => 'PT',
    ];

    if (isset($language_mapping[$source_lang])) {
      return $language_mapping[$source_lang];
    }

    return $source_lang;
  }

  /**
   * {@inheritdoc}
   */
  public function hasCheckoutSettings(JobInterface $job): bool {
    return FALSE;
  }

  /**
   * Local method to do request to DeepL Translate service.
   *
   * @param \Drupal\tmgmt\Entity\Translator $translator
   *   The translator entity to get the settings from.
   * @param array $query_params
   *   (Optional) Additional query params to be passed into the request.
   * @param array $options
   *   (Optional) Additional options that will passed to drupal_http_request().
   *
   * @return array
   *   Unserialized JSON response from DeepL API.
   *
   * @throws \Drupal\tmgmt\TMGMTException|\GuzzleHttp\Exception\GuzzleException
   *   - Unable to connect to the DeepL API Service
   *   - Error returned by the DeepL API Service.
   */
  protected static function doRequest(Translator $translator, array $query_params = [], array $options = []): array {
    // Get custom URL for testing purposes, if available.
    $custom_url = $translator->getSetting('test_url');
    $url = $custom_url ?: $translator->getSetting('url');
    // Define headers.
    $headers = [
      'Content-Type' => 'application/x-www-form-urlencoded',
    ];
    // Build the query.
    $query_string = '&auth_key=' . $translator->getSetting('auth_key');

    // Add text to be translated.
    if (isset($query_params[self::$qParamName])) {
      foreach ($query_params[self::$qParamName] as $source_text) {
        // Use rawurlencode, cause urlencode not encoding blanks correctly.
        if (is_string($source_text)) {
          $query_string .= '&text=' . rawurlencode(Html::decodeEntities($source_text));
        }
      }
    }
    // Add source language.
    if (isset($query_params['source_lang'])) {
      $query_string .= '&source_lang=' . $query_params['source_lang'];
    }

    // Add target language.
    if (isset($query_params['target_lang'])) {
      $query_string .= '&target_lang=' . $query_params['target_lang'];
    }

    // Split sentences.
    $query_string .= '&split_sentences=' . $translator->getSetting('split_sentences');

    // Formality.
    $query_string .= '&formality=' . $translator->getSetting('formality');

    // Preserve formatting.
    $query_string .= '&preserve_formatting=' . $translator->getSetting('preserve_formatting');

    // Tag handling.
    $tag_handling = $translator->getSetting('tag_handling');
    $query_string .= '&tag_handling=' . $tag_handling;

    if ($tag_handling == 'xml' || $tag_handling == 'html') {
      // Non splitting tags.
      if (!empty($translator->getSetting('non_splitting_tags'))) {
        $query_string .= '&non_splitting_tags=' . urlencode($translator->getSetting('non_splitting_tags'));
      }

      // Splitting tags.
      if (!empty($translator->getSetting('splitting_tags'))) {
        $query_string .= '&splitting_tags=' . urlencode($translator->getSetting('splitting_tags'));
      }

      // Ignore tags.
      if (!empty($translator->getSetting('ignore_tags'))) {
        $query_string .= '&ignore_tags=' . urlencode($translator->getSetting('ignore_tags'));
      }

      // Automatic outline detection.
      $query_string .= '&outline_detection=' . $translator->getSetting('outline_detection');
    }

    // Build request object.
    $request = new Request('POST', $url, $headers, $query_string);

    // Send the request with the query.
    try {
      $response = \Drupal::httpClient()->send($request);
    }
    catch (RequestException $e) {
      if ($e->hasResponse()) {
        $response = $e->getResponse();
        if ($response instanceof ResponseInterface) {
          throw new TMGMTException('DeepL API service returned following error: @error', ['@error' => $response->getReasonPhrase()]);
        }
      }
      else {
        throw new TMGMTException('DeepL API service returned following error: @error', ['@error' => $e->getMessage()]);
      }
    }

    // Process the JSON result into array.
    if ($response instanceof ResponseInterface) {
      /** @var array $return */
      $return = json_decode($response->getBody(), TRUE);
      return $return;
    }
    return ['translations' => []];
  }

  /**
   * Get translatorUrl.
   */
  final public function getTranslatorUrl(): ?string {
    return $this->translatorUrl ?? NULL;
  }

  /**
   * Get translatorUsageUrl.
   */
  final public function getUsageUrl(): ?string {
    return $this->translatorUsageUrl ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function requestJobItemsTranslation(array $job_items): void {
    $job_item = reset($job_items);
    if ($job_item instanceof JobItemInterface) {
      /** @var \Drupal\tmgmt\Entity\Job $job */
      $job = $job_item->getJob();
      foreach ($job_items as $job_item) {
        if ($job->isContinuous()) {
          $job_item->active();
        }
        // Pull the source data array through the job and flatten it.
        $data = $this->tmgmtData->filterTranslatable($job_item->getData());

        $translation = [];
        $q = [];
        $keys_sequence = [];

        // Build DeepL API q param and preserve initial array keys.
        foreach ($data as $key => $value) {
          $q[] = $value['#text'];
          $keys_sequence[] = $key;
        }

        // Use the Queue Worker if running via tmgmt_cron.
        if ($this->isCron()) {
          $this->queue->createItem([
            'job' => $job,
            'job_item' => $job_item,
            'q' => $q,
            'translation' => $translation,
            'keys_sequence' => $keys_sequence,
          ]);
        }
        else {
          $operations = [];
          $batch = [
            'title' => 'Translating job items',
            'finished' => [DeeplTranslator::class, 'batchFinished'],
          ];

          // Split $q into chunks of self::qChunkSize.
          foreach (array_chunk($q, $this->qChunkSize) as $_q) {
            // Build operations array.
            $arg_array = [$job, $_q, $translation, $keys_sequence];
            $operations[] = [
              '\Drupal\tmgmt_deepl\Plugin\tmgmt\Translator\DeeplTranslator::batchRequestTranslation',
              $arg_array,
            ];
          }

          // Add beforeBatchFinished operation.
          $arg2_array = [$job_item];
          $operations[] = [
            '\Drupal\tmgmt_deepl\Plugin\tmgmt\Translator\DeeplTranslator::beforeBatchFinished',
            $arg2_array,
          ];
          // Set batch operations.
          $batch['operations'] = $operations;
          batch_set($batch);
        }
      }
    }
  }

  /**
   * Batch 'operation' callback for requesting translation.
   *
   * @param \Drupal\tmgmt\Entity\Job $job
   *   The tmgmt job entity.
   * @param array $text
   *   The text to be translated.
   * @param array $translation
   *   The translated text.
   * @param array $keys_sequence
   *   Array of field name keys.
   * @param array $context
   *   The sandbox context.
   */
  public static function batchRequestTranslation(Job $job, array $text, array $translation, array $keys_sequence, &$context): void {
    $translator = $job->getTranslator();
    if (isset($context['results']) && isset($context['results']['i']) && $context['results']['i'] != NULL) {
      $i = $context['results']['i'];
    }
    else {
      $i = 0;
    }

    // Fix source language mapping.
    $source_lang = self::fixSourceLanguageMappings($job->getRemoteSourceLanguage());

    // Build query params.
    $query_params = [
      'source_lang' => $source_lang,
      'target_lang' => $job->getRemoteTargetLanguage(),
      'text' => $text,
    ];
    $result = self::doRequest($translator, $query_params);
    // Collect translated texts with use of initial keys.
    foreach ($result['translations'] as $translated) {
      $translation[$keys_sequence[$i]]['#text'] = rawurldecode(Html::decodeEntities($translated['text']));
      $i++;
    }
    if (isset($context['results']) && isset($context['results']['translation']) && $context['results']['translation'] != NULL) {
      $context['results']['translation'] = array_merge($context['results']['translation'], $translation);
    }
    else {
      $context['results']['translation'] = $translation;
    }
    $context['results']['i'] = $i;
  }

  /**
   * Batch 'operation' callback.
   *
   * @param \Drupal\tmgmt\JobItemInterface $job_item
   *   The job item.
   * @param array $context
   *   The sandbox context.
   */
  public static function beforeBatchFinished(JobItemInterface $job_item, &$context): void {
    $context['results']['job_item'] = $job_item;
  }

  /**
   * Batch 'operation' callback.
   *
   * @param bool $success
   *   Batch success.
   * @param array $results
   *   Results.
   * @param array $operations
   *   Operations.
   */
  public static function batchFinished(bool $success, array $results, array $operations): void {
    $tmgmtData = \Drupal::service('tmgmt.data');

    if (isset($results['job_item']) && $results['job_item'] instanceof JobItemInterface) {
      $job_item = $results['job_item'];
      $job_item->addTranslatedData($tmgmtData->unflatten($results['translation']));
      $job = $job_item->getJob();
      tmgmt_write_request_messages($job);
    }
  }

  /**
   * Local method to do request to DeepL API Usage service.
   *
   * @param \Drupal\tmgmt\Entity\Translator $translator
   *   The translator entity to get the settings from.
   *
   * @return array|int
   *   Unserialized JSON response from DeepL API or error code.
   *
   * @throws \GuzzleHttp\Exception\BadResponseException|\GuzzleHttp\Exception\GuzzleException
   *   - Unable to connect to the DeepL API Service
   *   - Error returned by the DeepL API Service.
   */
  public function getUsageData(Translator $translator) {
    // Set custom data for testing purposes, if available.
    $custom_usage_url = $translator->getSetting('url_usage');
    $custom_auth_key = $translator->getSetting('auth_key');
    /** @var string $url */
    $url = !empty($custom_usage_url) ? $custom_usage_url : $this->getUsageUrl();
    $auth_key = !empty($custom_auth_key) ? $custom_auth_key : $translator->getSetting('auth_key');

    // Prepare Guzzle Object.
    $request = new Request('GET', $url);

    // Build the query.
    $query_string = '&auth_key=' . $auth_key;

    // Send the request with the query.
    try {
      $response = $this->client->send($request, ['query' => $query_string]);
    }
    catch (BadResponseException $e) {
      return $e->getCode();
    }

    // Process the JSON result into array.
    /** @var array $return */
    $return = json_decode($response->getBody(), TRUE);
    return $return;
  }

  /**
   * Determine whether the process is being run via TMGMT cron.
   *
   * @param  int $backtrace_limit
   *   The amount of items to limit in the backtrace.
   *
   * @return bool
   */
  protected function isCron(int $backtrace_limit = 3): bool {
    if (!is_null($this->isCron)) {
      return $this->isCron;
    }
    $this->isCron = FALSE;
    foreach (debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, $backtrace_limit) as $item) {
      if ($item['function'] === 'tmgmt_cron') {
        $this->isCron = TRUE;
        break;
      }
    }
    return $this->isCron;
  }
}
