<?php

namespace Drupal\tmgmt_memsource\Plugin\tmgmt\Translator;

use Drupal\Core\Extension\InfoParser;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\tmgmt\ContinuousTranslatorInterface;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\Entity\JobItem;
use Drupal\tmgmt\Entity\RemoteMapping;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt\JobItemInterface;
use Drupal\tmgmt\TMGMTException;
use Drupal\tmgmt\Translator\AvailableResult;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\tmgmt\TranslatorPluginBase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Phrase TMS translation plugin controller.
 *
 * @TranslatorPlugin(
 *   id = "memsource",
 *   label = @Translation("phrase"),
 *   description = @Translation("Phrase TMS translator service."),
 *   ui = "Drupal\tmgmt_memsource\MemsourceTranslatorUi",
 * )
 */
class MemsourceTranslator extends TranslatorPluginBase implements ContainerFactoryPluginInterface, ContinuousTranslatorInterface {
  use StringTranslationTrait;

  const PASSWORD_V2_PREFIX = 'MEMSOURCE_V2___';
  const PASSWORD_V2_PREFIX_LENGTH = 15;
  const CHECK_JOB_MAX_RETRIES = 5;

  /**
   * The translator.
   *
   * @var \Drupal\tmgmt\TranslatorInterface
   */
  private $translator;

  /**
   * Guzzle HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $client;

  /**
   * Info file parser.
   *
   * @var \Drupal\Core\Extension\InfoParser
   */
  protected $parser;

  /**
   * Version of tmgmt_memsource module.
   *
   * @var string|null
   */
  protected $moduleVersion = NULL;

  /**
   * Memsource action generated for session.
   *
   * @var string|null
   */
  private $memsourceActionId = NULL;

  /**
   * @var string|null
   */
  protected $itemType = NULL;

  /**
   * @var string
   */
  protected $itemId = NULL;

  /**
   * Constructs a MemsourceTranslator object.
   *
   * @param \GuzzleHttp\ClientInterface $client
   *   The Guzzle HTTP client.
   * @param \Drupal\Core\Extension\InfoParser $parser
   *   Info file parser.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(ClientInterface $client, InfoParser $parser, array $configuration, $plugin_id, array $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->client = $client;
    $this->parser = $parser;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \GuzzleHttp\ClientInterface $client */
    $client = $container->get('http_client');
    /** @var \Drupal\Core\Extension\InfoParser $parser */
    $parser = $container->get('info_parser');
    return new static(
      $client,
      $parser,
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * Sets a Translator.
   *
   * @param \Drupal\tmgmt\TranslatorInterface $translator
   *   The translator to set.
   */
  public function setTranslator(TranslatorInterface $translator) {
    $this->translator = $translator;
  }

  /**
   * Checks if plugin is able to connect to Memsource
   */
  public function checkMemsourceConnection(TranslatorInterface $translator): bool
  {
    $users = [];
    $this->setTranslator($translator);

    try {
      $users = $this->sendApiRequest('/api2/v1/users');
    } catch (\Exception $e) {
      // Ignore exception, only testing connection.
    }

    if ($users) {
      return true;
    }

    return false;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedRemoteLanguages(TranslatorInterface $translator) {
    $supported_remote_languages = [];
    $this->setTranslator($translator);
    try {
      $supported_languages = $this->sendApiRequest('/api2/v1/languages');
      if (isset($supported_languages['languages']) &&
        (is_array($supported_languages['languages']) || is_object($supported_languages['languages']))) {
        foreach ($supported_languages['languages'] as $language) {
          $supported_remote_languages[$language['code']] = $language['name'];
        }
      }
    }
    catch (\Exception $e) {
      // Ignore exception, nothing we can do.
    }
    asort($supported_remote_languages);
    return $supported_remote_languages;
  }

  /**
   * Gets all Drupal connectors.
   */
  public function getDrupalConnectors(TranslatorInterface $translator): array {
    $tokens = [];
    $this->setTranslator($translator);
    try {
      $connectors = $this->sendApiRequest('/api2/v1/connectors');

      $connectors = array_filter($connectors['connectors'], static function ($connector) {
          return $connector['type'] === 'DRUPAL_PLUGIN';
      });

      foreach ($connectors as $connector) {
        $tokens[$connector['localToken']] = $connector['name'];
      }
    }
    catch (\Exception $e) {
      // Ignore exception, nothing we can do.
    }

    return $tokens;
  }

  /**
   * {@inheritdoc}
   */
  public function checkAvailable(TranslatorInterface $translator) {
    if ($this->getToken()) {
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
  public function requestTranslation(JobInterface $job) {
    $job = $this->requestJobItemsTranslation($job->getItems());
    if (!$job->isRejected()) {
      $job->submitted();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function requestJobItemsTranslation(array $job_items) {
    /** @var \Drupal\tmgmt\Entity\Job $job */

    // Assign url parameters for the selected connector.
    $this->itemType = reset($job_items)->get('item_type')->getString();
    $this->itemId = reset($job_items)->get('item_id')->getString();

    $job = reset($job_items)->getJob();
    $this->setTranslator($job->getTranslator());
    $due_date = $job->getSetting('due_date');
    $project_id = NULL;
    $job_part_uids = [];

    if ($job->getSetting('group_jobs')) {
      $project_id = $this->getMemsourceProjectIdByBatchId(reset($job_items));

      if (!$project_id) {
        $project_id = $this->getMemsourceProjectIdByContent($job_items);
      }
    }

    try {
      if ($project_id) {
        $job->addMessage('Using an existing project in Phrase TMS with the id: @id', ['@id' => $project_id], 'debug');
      }
      else {
        $project_id = $this->newTranslationProject($job);
        $job->addMessage('Created a new project in Phrase TMS with the id: @id', ['@id' => $project_id], 'debug');
      }

      /** @var \Drupal\tmgmt\Entity\JobItem $job_item */
      foreach ($job_items as $job_item) {
        $job_part_id = $this->sendFiles($job_item, $project_id, $due_date);

        /** @var \Drupal\tmgmt\Entity\RemoteMapping $remote_mapping */
        $remote_mapping = RemoteMapping::create([
          'tjid' => $job->id(),
          'tjiid' => $job_item->id(),
          'remote_identifier_1' => 'tmgmt_memsource',
          'remote_identifier_2' => $project_id,
          'remote_identifier_3' => $job_part_id,
          'remote_data' => [
            'FileStateVersion' => 1,
            'TmsState' => 'TranslatableSource',
            'RequiredBy' => $due_date,
          ],
        ]);
        $remote_mapping->save();

        if ($job_item->getJob()->isContinuous()) {
          $job_item->active();
        }
        $job_part_uids[] = $job_part_id;
      }
      $this->assignMemsourceProviders($project_id, $job, $job_part_uids);
    }
    catch (TMGMTException $e) {
      $job->rejected(
        'Job has been rejected with following error: @error, memsourceId: @memsourceId',
        ['@error' => $e->getMessage(), '@memsourceId' => $this->getMemsourceActionId()],
        'error'
      );

      if (isset($remote_mapping)) {
        $remote_mapping->delete();
      }
    }

    return $job;
  }

  /**
   * Get Memsource project ID if given job can be added to the project.
   *
   * @param \Drupal\tmgmt\JobItemInterface[] $job_items
   *   TMGMT job items.
   *
   * @return string|null
   *   Memsource project ID if found.
   */
  private function getMemsourceProjectIdByContent(array $job_items) {
    $memsource_project_ids = [];

    foreach ($job_items as $job_item) {
      $raw_items = \Drupal::entityQuery('tmgmt_job_item')
        ->condition('plugin', $job_item->getPlugin())
        ->condition('item_type', $job_item->getItemType())
        ->condition('item_id', $job_item->getItemId())
        ->condition('tjiid', $job_item->id(), '<>')
        ->sort('tjiid', 'DESC')
        ->accessCheck(FALSE)
        ->execute();

      if (!empty($raw_items)) {
        /** @var \Drupal\tmgmt\JobItemInterface[] $items */
        $items = JobItem::loadMultiple($raw_items);
      }

      foreach (($items ?? []) as $item) {
        $raw_remotes = \Drupal::entityQuery('tmgmt_remote')
          ->condition('tjiid', $item->id())
          ->sort('trid', 'DESC')
          ->accessCheck(FALSE)
          ->execute();

        if (!empty($raw_remotes)) {
          /** @var \Drupal\tmgmt\RemoteMappingInterface[] $remotes */
          $remotes = RemoteMapping::loadMultiple($raw_remotes);
        }

        foreach (($remotes ?? []) as $remote) {
          if (!empty($remote->getRemoteIdentifier2())) {
            try {
              $memsource_project = $this->sendApiRequest('/api2/v1/projects/' . $remote->getRemoteIdentifier2());
            }
            catch (\Exception $e) {
              $this->logWarn('Unable to fetch remote project: ' . $e->getMessage());
              continue;
            }

            if (!in_array($memsource_project['status'], ['COMPLETED', 'CANCELLED']) &&
              in_array($job_item->getJob()->getRemoteTargetLanguage(), $memsource_project['targetLangs'])) {
              $memsource_project_ids[] = $remote->getRemoteIdentifier2();
            }
          }
        }
      }
    }

    if (count(array_unique($memsource_project_ids)) === 1) {
      return reset($memsource_project_ids);
    }

    return NULL;
  }

  /**
   * Get Memsource project ID if given job can be added to the project.
   *
   * @param \Drupal\tmgmt\JobItemInterface $job_item
   *   TMGMT job item.
   *
   * @return string|null
   *   Memsource project ID if found.
   */
  private function getMemsourceProjectIdByBatchId(JobItemInterface $job_item) {
    $job = $job_item->getJob();
    $batch_id = $job->getSetting('batch_id');

    if (!empty($batch_id)) {
      $rawJobs = \Drupal::entityQuery('tmgmt_job')
        ->condition('settings', $batch_id, 'CONTAINS')
        ->condition('tjid', $job->id(), '<>')
        ->sort('tjid', 'DESC')
        ->accessCheck(FALSE)
        ->execute();

      if (!empty($rawJobs)) {
        /** @var \Drupal\tmgmt\JobInterface[] $jobs */
        $jobs = Job::loadMultiple($rawJobs);
      }

      foreach (($jobs ?? []) as $previous_job) {
        if ($previous_job->getSetting('batch_id') === $batch_id) {
          $previous_job_items = $previous_job->getItems([
            'plugin' => $job_item->getPlugin(),
            'item_type' => $job_item->getItemType(),
          ]);

          foreach ($previous_job_items as $previous_job_item) {
            /** @var \Drupal\tmgmt\RemoteMappingInterface $remote */
            foreach ($previous_job_item->getRemoteMappings() as $remote) {
              if (!empty($remote->getRemoteIdentifier2())) {
                try {
                  $memsource_project = $this->sendApiRequest('/api2/v1/projects/' . $remote->getRemoteIdentifier2());
                }
                catch (\Exception $e) {
                  $this->logWarn('Unable to fetch remote project: ' . $e->getMessage());
                  continue;
                }

                if (isset($memsource_project['uid'])) {
                  return $remote->getRemoteIdentifier2();
                }
              }
            }
          }
        }
      }
    }

    return NULL;
  }

  /**
   * Performs a login to Memsource Cloud.
   *
   * @return bool
   *   A success or failure.
   */
  public function loginToMemsource() {
    $params = [
      'userName' => $this->translator->getSetting('memsource_user_name'),
      'password' => $this->decodePassword($this->translator->getSetting('memsource_password')),
    ];

    try {
      $result = $this->request('/api2/v1/auth/login', 'POST', $params);

      if (isset($result['token']) && $result['token']) {
        // Store the token.
        $this->storeToken($result['token']);

        return TRUE;
      }
    }
    catch (TMGMTException $ex) {
      $this->logWarn('Unable to log in to Phrase TMS API: ' . $ex->getMessage());
    }

    return FALSE;
  }

  /**
   * Encode Memsource password.
   *
   * @param string $password
   *   Password to be encoded.
   *
   * @return string
   *   Encoded password.
   */
  public function encodePassword($password) {
    if (is_string($password) && (substr($password, 0, self::PASSWORD_V2_PREFIX_LENGTH) !== self::PASSWORD_V2_PREFIX)) {
      $password = self::PASSWORD_V2_PREFIX . bin2hex($password);
    }

    return $password;
  }

  /**
   * Decode Memsource password.
   *
   * @param string $password
   *   Encoded or plaintext password.
   *
   * @return string
   *   Decoded password.
   */
  public function decodePassword($password) {
    if (substr($password, 0, self::PASSWORD_V2_PREFIX_LENGTH) === self::PASSWORD_V2_PREFIX) {
      $password = hex2bin(substr($password, self::PASSWORD_V2_PREFIX_LENGTH, strlen($password)));
    }

    return $password;
  }

  /**
   * Stores a Memsource API token.
   *
   * @param string $token
   *   Token.
   */
  public function storeToken($token) {
    $config = \Drupal::configFactory()->getEditable('tmgmt_memsource.settings');
    $config->set('memsource_token', $token)->save();
  }

  /**
   * Returns a Memsource API token.
   *
   * @return string
   *   Token.
   */
  public function getToken() {
    return \Drupal::configFactory()
      ->get('tmgmt_memsource.settings')
      ->get('memsource_token');
  }

  /**
   * Sends a request to the Memsource API and refreshes the token if necessary.
   *
   * @param string $path
   *   API path.
   * @param string $method
   *   (Optional) HTTP method.
   * @param array $params
   *   (Optional) API params.
   * @param bool $download
   *   (Optional) If true, return the response body as a downloaded content.
   * @param bool $code
   *   (Optional) If true, return only the response HTTP status code.
   * @param string $body
   *   (Optional) An optional request body.
   *
   * @return array|int|null
   *   Result of the API request.
   *
   * @throws \Drupal\tmgmt\TMGMTException
   */
  public function sendApiRequest($path, $method = 'GET', array $params = [], $download = FALSE, $code = FALSE, $body = NULL) {
    $result = NULL;
    $params['token'] = $this->getToken();
    try {
      $result = $this->request($path, $method, $params, $download, $code, $body);
    }
    catch (TMGMTException $ex) {
      if ($ex->getCode() == 401) {
        // Token is invalid, try to re-login.
        $this->loginToMemsource();
        $params['token'] = $this->getToken();
        $result = $this->request($path, $method, $params, $download, $code, $body);
      }
      else {
        throw $ex;
      }
    }
    return $result;
  }

  /**
   * Does a request to Memsource API.
   *
   * @param string $path
   *   Resource path, for example '/api2/v1/auth/login'.
   * @param string $method
   *   (Optional) HTTP method (GET, POST...). By default uses GET method.
   * @param array $params
   *   (Optional) Form parameters to send to Memsource API.
   * @param bool $download
   *   (Optional) If we expect resource to be downloaded. FALSE by default.
   * @param bool $code
   *   (Optional) If we want to return the status code of the call. FALSE by
   *   default.
   * @param string|null $body
   *   (Optional) Body of the POST request. NULL by
   *   default.
   *
   * @return array|int
   *   Response array or status code.
   *
   * @throws \Drupal\tmgmt\TMGMTException
   */
  public function request($path, $method = 'GET', array $params = [], $download = FALSE, $code = FALSE, $body = NULL) {
    $options = ['headers' => []];

    if (!$this->translator) {
      throw new TMGMTException('There is no Translator entity. Access to the Phrase TMS API is not possible.');
    }

    $service_url = $this->translator->getSetting('service_url');

    if (!$service_url) {
      $this->logWarn("Attempt to call Phrase TMS API when service_url is not set: $path");
      return [];
    }

    $url = $this->updateServiceUrl($service_url) . $path;

    if (isset($params['token'])) {
      $options['headers'] = ['Authorization' => 'ApiToken ' . $params['token']];
      unset($params['token']);
    }

    if ($body) {
      $options['body'] = $body;
      if (!empty($params)) {
        $options['headers'] += $params;
      }
    }
    elseif ($method === 'GET') {
      $options['query'] = $params;
    }
    else {
      $options['json'] = $params;
    }

    $logOptions = $options;
    if (isset($logOptions['headers']['Authorization'])) {
      $logOptions['headers']['Authorization'] = '******';
    }
    $log = [
      '%method' => $method,
      '%url' => $url,
      '%request' => json_encode($logOptions),
    ];

    try {
      $response = $this->client->request($method, $url, $options);
    }
    catch (RequestException $e) {
      error_log("Error: {$e->getMessage()}, \nmemsource-action-id=" . $this->getMemsourceActionId());
      $responseContents = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : 'NULL';
      $this->logDebug(
        "=> REQUEST:\n%method %url\n%request\n\n=> RESPONSE:\n%response\n\n=> CODE:\n%code\n",
        $log + [
          '%response' => $responseContents,
          '%code' => $e->getCode(),
        ]
      );

      if (!$e->hasResponse()) {
        if ($code) {
          return $e->getCode();
        }

        throw new TMGMTException('Unable to connect to Phrase TMS API [memsource-action-id=@actionId] due to following error: @error',
            ['@error' => $e->getMessage(), '@actionId' => $this->getMemsourceActionId()], $e->getCode());
      }

      $response = $e->getResponse();

      $this->logDebug('=> ERROR: ' . "\n" . '%response', ['%response' => $responseContents]);

      if ($code) {
        return $response->getStatusCode();
      }

      throw new TMGMTException('Unable to connect to Phrase TMS API [memsource-action-id=@actionId] due to following error: @error',
          ['@error' => $response->getReasonPhrase(), '@actionId' => $this->getMemsourceActionId()], $response->getStatusCode());
    }

    $received_data = $response->getBody()->getContents();

    $this->logDebug(
      "=> REQUEST:\n%method %url\n%request\n\n=> RESPONSE:\n%response\n",
      $log + ['%response' => $received_data]
    );

    if ($code) {
      return $response->getStatusCode();
    }

    if (!in_array($response->getStatusCode(), [200, 201], TRUE)) {
      throw new TMGMTException(
        'Unable to connect to the Phrase TMS API [memsource-action-id=@actionId] due to following error: @error at @url',
        [
          '@error' => $response->getStatusCode(),
          '@url' => $url,
          '@actionId' => $this->getMemsourceActionId(),
        ]
      );
    }

    if ($download) {
      return $received_data;
    }

    return json_decode($received_data, TRUE);
  }

  /**
   * Modify service URL due to the Memsource API migration (if necessary).
   *
   * 'https://qa.memsource.com/web/api' -> 'https://qa.memsource.com/web'.
   *
   * @param string $url
   *   Service URL.
   *
   * @return string
   *   Updated Memsource URL.
   */
  private function updateServiceUrl($url) {
    $pos = strpos($url, '/web/');

    if ($pos !== FALSE) {
      $url = substr($url, 0, $pos + 4);
      // $this->translator->setSetting('service_url', $url);.
    }

    return $url;
  }

  /**
   * Creates new translation project at Memsource Cloud.
   *
   * Project has all languages available by default in order to load
   * all Translation Memories and Term Bases set in the template.
   *
   * @param \Drupal\tmgmt\JobInterface $job
   *   The job.
   *
   * @return string
   *   Memsource project uid.
   */
  public function newTranslationProject(JobInterface $job) {
    $due_date = $job->getSetting('due_date');
    $template_id = $job->getSetting('project_template');
    $source_language = $job->getRemoteSourceLanguage();
    $name_suffix = '';

    if ($job->getSetting('group_jobs')) {
      $remote_languages_mappings = $job->getTranslator()->getRemoteLanguagesMappings();
      if (($source_language_key = array_search($source_language, $remote_languages_mappings)) !== FALSE) {
        unset($remote_languages_mappings[$source_language_key]);
      }
      $target_languages = array_values($remote_languages_mappings);
    }
    else {
      $target_languages = [$job->getRemoteTargetLanguage()];
      $target_language_name = $job->getRemoteTargetLanguage();
      $config = \Drupal::configFactory()->get("language.entity.$target_language_name");
      if ($config && $config->get('label')) {
        $target_language_name = $config->get('label');
      }
      $name_suffix = " ($target_language_name)";
    }

    $params = [
      'name' => ($job->label() ?: 'Drupal TMGMT project ' . $job->id()) . $name_suffix,
      'sourceLang' => $source_language,
      'targetLangs' => $target_languages,
    ];

    if (strlen($due_date) > 0) {
      $params['dateDue'] = $this->convertDateToEod($due_date);
    }

    if ($template_id === '0' || $template_id === NULL) {
      $result = $this->sendApiRequest('/api2/v1/projects', 'POST', $params);
    }
    else {
      $result = $this->sendApiRequest("/api2/v2/projects/applyTemplate/$template_id", 'POST', $params);
    }

    return $result['uid'];
  }

  /**
   * Assign providers defined in project template.
   *
   * @param string $project_id
   *   Project UID.
   * @param \Drupal\tmgmt\JobInterface $job
   *   The job.
   */
  private function assignMemsourceProviders($project_id, JobInterface $job, $job_part_uids) {
    $template_id = $job->getSetting('project_template');

    if ($template_id !== NULL && $template_id !== '0') {
      $this->checkJobsCreated($project_id, $job_part_uids);
      try {
        $this->sendApiRequest(
          "/api2/v1/projects/$project_id/applyTemplate/$template_id/assignProviders",
          'POST'
        );
      } catch (\Exception $e) {
        $job->addMessage('Unable to assign providers. Phrase TMS project: @id', ['@id' => $project_id], 'error');
        $this->logError("Unable to assign providers. projectId=$project_id, templateId=$template_id");
      }
    }
  }

  /**
   * Check that all the jobs were created in Phrase TMS. If not, sleep for a while and try again until CHECK_JOB_MAX_RETRIES is reached.
   *
   * @param string $project_id
   *   Project UID.
   * @param string[] $job_part_uids
   *   Job part UID.
   */
  private function checkJobsCreated($project_id, $job_part_uids) {
    foreach ($job_part_uids as $job_part_uid) {
      for ($retries = 0; true; $retries++) {
        try {
          $response = $this->sendApiRequest("/api2/v1/projects/$project_id/jobs/$job_part_uid");
          if (isset($response['importStatus']['status']) && in_array($response['importStatus']['status'], ['ERROR', 'OK'], true)) {
            break;
          }
        } catch (\Exception $e) {}
        $this->logDebug("Job uid=$job_part_uid import not finished, going to sleep for $retries seconds");
        sleep($retries);
        if ($retries > self::CHECK_JOB_MAX_RETRIES) {
          $this->logWarn("Job uid=$job_part_uid not found, max retries reached");
          break;
        }
      }
    }
  }

  /**
   * Send the files to Memsource Cloud.
   *
   * @param \Drupal\tmgmt\JobItemInterface $job_item
   *   The Job.
   * @param int $project_id
   *   Memsource project id.
   * @param string $due_date
   *   The date by when the translation is required.
   *
   * @return string
   *   Memsource jobPartId.
   */
  private function sendFiles(JobItemInterface $job_item, $project_id, $due_date) {
    /** @var \Drupal\tmgmt_file\Format\FormatInterface $xliff_converter */
    $xliff_converter = \Drupal::service('plugin.manager.tmgmt_file.format')->createInstance('xlf');

    $job_id = $job_item->getJob()->id();
    $job_item_id = $job_item->id();
    $job_item_label = $job_item->label();
    $source_language = $job_item->getJob()->getSourceLangcode();
    $target_language = $job_item->getJob()->getRemoteTargetLanguage();
    $conditions = ['tjiid' => ['value' => $job_item_id]];
    $xliff = $xliff_converter->export($job_item->getJob(), $conditions);
    $name = "JobID_{$job_id}_{$job_item_label}_{$source_language}_{$target_language}";

    $job_item->addMessage('Created memsource-action-id=' . $this->getMemsourceActionId(), [], 'debug');

    return $this->createJob($project_id, $target_language, $due_date, $xliff, $name);
  }

  /**
   * Create a job in Memsource.
   *
   * @param string $project_id
   *   Project ID.
   * @param string $target_language
   *   Target language code.
   * @param string $due_date
   *   Job due date.
   * @param string $xliff
   *   XLIFF file.
   * @param string $name
   *   File name.
   *
   * @return string
   *   Job UID.
   *
   * @throws \Drupal\tmgmt\TMGMTException
   */
  public function createJob($project_id, $target_language, $due_date, $xliff, $name) {
    $params = [
      'targetLangs' => [$target_language],
      'useProjectFileImportSettings' => 'true',
      'sourceData' => [
        'clientType' => 'DRUPAL',
        'clientVersion' => $this->getMemsourceModuleVersion(),
        'hostVersion' => \Drupal::VERSION,
      ],
      'remotePreview' => [
          'connectorToken' => $this->translator->getSetting('memsource_connector_token'),
          'remoteFolder' => $this->itemType,
          'remoteFileName' => $this->itemId,
      ]
    ];

    if (strlen($due_date) > 0) {
      $params['due'] = $this->convertDateToEod($due_date);
    }

    $headers = [
      'Content-Disposition' => "filename*=UTF-8''" . urlencode($name) . ".xliff",
      'Memsource' => json_encode($params),
      'memsource-action-id' => $this->getMemsourceActionId(),
    ];

    $result = $this->sendApiRequest(
      "/api2/v1/projects/$project_id/jobs",
      'POST',
      $headers,
      FALSE,
      FALSE,
      $xliff
    );

    return $this->getUidOfLatestJob($result['jobs']);
  }

  /**
   * Convert local date to EOD (23:59:59) datetime in UTC timezone.
   *
   * @param string $date
   *   Date in format  YYYY-MM-DD.
   *
   * @return string
   *   Datetime in format YYYY-MM-DDTHH:mm:ssZ.
   */
  private function convertDateToEod($date) {
    $dateTime = new \DateTime("$date 23:59:59");
    $dateTime->setTimezone(new \DateTimeZone('UTC'));

    return $dateTime->format('Y-m-d\TH:i:s\Z');
  }

  /**
   * Get UID of the latest returned job (according to workflow steps).
   *
   * @param array $jobs
   *   Jobs.
   *
   * @return string
   *   Job UID.
   */
  private function getUidOfLatestJob(array $jobs) {
    $latestJob = current($jobs);

    if (count($jobs) > 1) {
      $maxWorkflowLevel = max(array_column($jobs, 'workflowLevel'));

      $filteredJobs = array_filter($jobs, static function ($job) use ($maxWorkflowLevel) {
        return $job['workflowLevel'] == $maxWorkflowLevel;
      });

      if (count($filteredJobs) > 0) {
        $latestJob = current($filteredJobs);
      }
    }

    return $latestJob['uid'];
  }

  /**
   * Fetches translations for job items of a given job.
   *
   * @param \Drupal\tmgmt\JobInterface $job
   *   A job containing job items that translations will be fetched for.
   *
   * @return array
   *   Array containing a containing the number of items translated and the
   *   number that has not been translated yet.
   */
  public function fetchTranslatedFiles(JobInterface $job) {
    $this->setTranslator($job->getTranslator());
    $translated = 0;
    $errors = [];

    try {
      /** @var \Drupal\tmgmt\JobItemInterface $job_item */
      foreach ($job->getItems() as $job_item) {
        $mappings = RemoteMapping::loadByLocalData($job->id(), $job_item->id());
        /** @var \Drupal\tmgmt\Entity\RemoteMapping $mapping */
        foreach ($mappings as $mapping) {
          // Prepare parameters for Job API (to get the job status).
          $job_uid = $mapping->getRemoteIdentifier3();
          $project_id = $mapping->getRemoteIdentifier2();
          $info = [];
          try {
            $info = $this->sendApiRequest("/api2/v1/projects/$project_id/jobs/$job_uid");
          }
          catch (TMGMTException $e) {
            $job->addMessage('Error fetching the job item @job_item. Phrase TMS Action: @actionId. Phrase TMS job @job_uid not found: @error',
              [
                '@job_item' => $job_item->label(),
                '@job_uid' => $job_uid,
                '@actionId' => $this->getMemsourceActionId(),
                '@error' => $e->getMessage(),
              ], 'error');
            $errors[] = 'Phrase TMS job ' . $job_uid . ' not found, it was probably deleted.';
          }
          if (isset($info['status']) && $this->remoteTranslationCompleted($info['status'])) {
            try {
              $this->addFileDataToJob($job, $info['status'], $project_id, $job_uid);
              if ($this->translator->getSetting('memsource_update_job_status') === 1) {
                $this->sendApiRequest(
                  "/api2/v1/projects/$project_id/jobs/$job_uid/setStatus",
                  'POST',
                  ["Content-Type" => "application/json"],
                  FALSE,
                  204,
                  '{"requestedStatus": "DELIVERED"}'
                );
              }
              $translated++;
            }
            catch (TMGMTException $e) {
              $job->addMessage(
                'Error fetching the job item @job_item: @error',
                [
                  '@job_item' => $job_item->label(),
                  '@error' => $e->getMessage(),
                ],
                'error'
              );
              continue;
            }
          }
        }
      }
    }
    catch (TMGMTException $e) {
      $this->logError('Could not pull translation resources: @error', ['@error' => $e->getMessage()]);
    }

    return [
      'translated' => $translated,
      'untranslated' => count($job->getItems()) - $translated,
      'errors' => $errors,
    ];
  }

  /**
   * Retrieve all the updates for all the job items in a translator.
   *
   * @param \Drupal\tmgmt\JobItemInterface $job_item
   *   The job item to get the translation.
   *
   * @return int
   *   The number of updated job items.
   */
  public function pullRemoteTranslation(JobItemInterface $job_item) {
    $job = $job_item->getJob();
    $this->setTranslator($job->getTranslator());
    $remotes = RemoteMapping::loadByLocalData($job->id(), $job_item->id());
    $remote = reset($remotes);
    $job_uid = $remote->getRemoteIdentifier3();
    $project_id = $remote->getRemoteIdentifier2();
    $info = $this->sendApiRequest("/api2/v1/projects/$project_id/jobs/$job_uid");
    if (isset($info['status']) && $this->remoteTranslationCompleted($info['status'])) {
      try {
        $this->addFileDataToJob($job, $info['status'], $project_id, $job_uid);
        return 1;
      }
      catch (TMGMTException $e) {
        $job->addMessage('Error fetching the job item: @job_item.', [
          '@job_item' => $remote->getJobItem()->label(),
        ], 'error');
      }
    }
    return 0;
  }

  /**
   * Retrieve the data of a file in a state.
   *
   * @param \Drupal\tmgmt\JobInterface $job
   *   The Job to which will be added the data.
   * @param string $state
   *   The state of the file.
   * @param int $project_id
   *   The project ID.
   * @param string $job_uid
   *   The file ID.
   *
   * @throws \Drupal\tmgmt\TMGMTException
   */
  public function addFileDataToJob(JobInterface $job, $state, $project_id, $job_uid) {
    $data = $this->sendApiRequest("/api2/v1/projects/$project_id/jobs/$job_uid/targetFile", 'GET', [], TRUE);
    $file_data = $this->parseTranslationData($data);
    if ($this->remoteTranslationCompleted($state)) {
      $status = TMGMT_DATA_ITEM_STATE_TRANSLATED;
    }
    else {
      $status = TMGMT_DATA_ITEM_STATE_PRELIMINARY;
    }
    $job->addTranslatedData($file_data, [], $status);
    $mappings = RemoteMapping::loadByRemoteIdentifier('tmgmt_memsource', $project_id, $job_uid);
    $mapping = reset($mappings);
    $mapping->removeRemoteData('TmsState');
    $mapping->addRemoteData('TmsState', $state);
    $mapping->save();
  }

  /**
   * Parses received translation from Memsource and returns unflatted data.
   *
   * @param string $data
   *   Xliff data, received from Memsource Cloud.
   *
   * @return array
   *   Unflatted data.
   */
  protected function parseTranslationData($data) {
    /** @var \Drupal\tmgmt_file\Format\FormatInterface $xliff_converter */
    $xliff_converter = \Drupal::service('plugin.manager.tmgmt_file.format')->createInstance('xlf');
    // Import given data using XLIFF converter.
    // Specify that passed content is not a file.
    return $xliff_converter->import($data, FALSE);
  }

  /**
   * Check that given status represents completed state.
   *
   * @param string $status
   *   Given status.
   *
   * @return bool
   *   Return true if status is conmleted.
   */
  public function remoteTranslationCompleted($status) {
    return in_array($status, ['COMPLETED_BY_LINGUIST', 'COMPLETED', 'DELIVERED']);
  }

  /**
   * Get version of Memsource plugin.
   *
   * @return string
   *   Current version.
   */
  private function getMemsourceModuleVersion() {
    if ($this->moduleVersion === NULL) {
      $file = dirname(dirname(dirname(dirname(__DIR__)))) . DIRECTORY_SEPARATOR . 'tmgmt_memsource.info.yml';
      try {
        $info = $this->parser->parse($file);
        $this->moduleVersion = ($info['version'] ?? '');
      }
      catch (\Exception $e) {
        $this->moduleVersion = '';
        $this->logDebug('Unable to parse tmgmt_memsource module version from info file: ' . $e->getMessage());
      }
    }

    return $this->moduleVersion;
  }

  /**
   * Log ERROR message.
   *
   * @param string $message
   *   Log message.
   * @param array $context
   *   Log message context.
   */
  private function logError($message, array $context = []) {
    $context['%actionId'] = $this->getMemsourceActionId();
    \Drupal::logger('tmgmt_memsource')->error($message . " \nmemsource-action-id=%actionId", $context);
  }

  /**
   * Log WARN message if debug mode enabled.
   *
   * @param string $message
   *   Log message.
   * @param array $context
   *   Log message context.
   */
  private function logWarn($message, array $context = []) {
    if ($this->isDebugEnabled()) {
      $context['%actionId'] = $this->getMemsourceActionId();
      \Drupal::logger('tmgmt_memsource')->warning($message . " \nmemsource-action-id=%actionId", $context);
    }
  }

  /**
   * Log DEBUG message if debug mode enabled.
   *
   * @param string $message
   *   Log message.
   * @param array $context
   *   Log message context.
   */
  private function logDebug($message, array $context = []) {
    if ($this->isDebugEnabled()) {
      $context['%actionId'] = $this->getMemsourceActionId();
      \Drupal::logger('tmgmt_memsource')->debug($message . " \nmemsource-action-id=%actionId", $context);
    }
  }

  /**
   * Check status of debug mode.
   *
   * @return bool
   *   Returns TRUE of debug mode is enabled, FALSE otherwise.
   */
  private function isDebugEnabled() {
    return \Drupal::configFactory()->get('tmgmt_memsource.settings')->get('debug');
  }

  /**
   * Returns generated MemsourceActionId, unique per instance.
   *
   * @return string|null
   *   Returns generated memsource action id
   */
  public function getMemsourceActionId(): string {
    if ($this->memsourceActionId === NULL) {
      $this->memsourceActionId = sprintf("%s-%s",
        (new \DateTime('now'))->format('u'),
        bin2hex(random_bytes(6))
      );
    }

    return $this->memsourceActionId;
  }

}
