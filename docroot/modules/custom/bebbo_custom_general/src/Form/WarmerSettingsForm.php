<?php

namespace Drupal\bebbo_custom_general\Form;

use Drupal\bebbo_custom_general\Service\WarmerRunner;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings, manual run and run log for the v1 API cache warmer.
 *
 * The button on this form warms the site it is opened on. Warming every site
 * in one go is the job of the bebbo:warm-all Drush command, because the URL
 * list of a site can only be built from that site's own database.
 */
class WarmerSettingsForm extends ConfigFormBase {

  /**
   * Environment keys, in the order the hosts table lists them.
   *
   * 'test' is Acquia's name for the staging environment.
   */
  const ENVIRONMENTS = ['dev', 'test', 'prod'];

  /**
   * The warmer runner.
   *
   * @var \Drupal\bebbo_custom_general\Service\WarmerRunner
   */
  protected WarmerRunner $runner;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->runner = $container->get('bebbo_custom_general.warmer_runner');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [WarmerRunner::CONFIG_NAME];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bebbo_warmer_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(WarmerRunner::CONFIG_NAME);

    $form['run'] = [
      '#type' => 'details',
      '#title' => $this->t('Warm this site'),
      '#open' => TRUE,
    ];
    $languages = $this->runner->getLanguages();
    $form['run']['summary'] = [
      '#type' => 'item',
      '#markup' => $this->t('This site is <strong>@site</strong>. It warms @count URL(s): every configured path in @langcount language(s) — @languages — plus one update check per country group.', [
        '@site' => $this->runner->getSiteKey(),
        '@count' => count($this->runner->buildUrls()),
        '@langcount' => count($languages),
        '@languages' => implode(', ', $languages),
      ]),
    ];
    $form['run']['warm'] = [
      '#type' => 'submit',
      '#value' => $this->t('Warm this site now'),
      '#submit' => ['::warmNow'],
      '#button_type' => 'primary',
    ];
    $form['run']['logs'] = [
      '#type' => 'link',
      '#title' => $this->t('See the run log'),
      '#url' => Url::fromRoute('bebbo_custom_general.warmer_logs'),
      '#attributes' => ['class' => ['button']],
    ];

    $form['settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Settings'),
      '#open' => TRUE,
    ];
    $form['settings']['concurrency'] = [
      '#type' => 'number',
      '#title' => $this->t('Concurrent requests'),
      '#description' => $this->t('How many warm-up requests to keep in flight at once. Every one of them occupies a web server worker, so raising this competes with real traffic.'),
      '#min' => 1,
      '#max' => 32,
      '#default_value' => $config->get('concurrency'),
      '#required' => TRUE,
    ];
    $form['settings']['request_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Request timeout (seconds)'),
      '#description' => $this->t('A cold listing renders far past the 30 second default. Aborting early leaves the cache cold for the next visitor too.'),
      '#min' => 30,
      '#max' => 900,
      '#default_value' => $config->get('request_timeout'),
      '#required' => TRUE,
    ];
    $form['settings']['paths'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Paths'),
      '#description' => $this->t('One path per line. {lang} is replaced with each language the site serves. Query strings are allowed.'),
      '#default_value' => implode("\n", $config->get('paths') ?: []),
      '#rows' => 12,
      '#required' => TRUE,
    ];
    $form['settings']['sites'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Sites'),
      '#description' => $this->t('One site per line, as <code>site|dev|stage|prod|languages</code>, where <em>site</em> is the directory under docroot/sites, the three hostnames carry no scheme, and <em>languages</em> is a comma separated list of langcodes. A site left without languages warms whatever its country groups serve in the app. This list covers every site, so edit and export it on the @default site.', [
        '@default' => 'bebbo',
      ]),
      '#default_value' => $this->sitesToText($config->get('sites') ?: []),
      '#rows' => 10,
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    foreach ($this->lines($form_state->getValue('paths')) as $path) {
      if (!str_starts_with($path, '/')) {
        $form_state->setErrorByName('paths', $this->t('Path "@path" has to start with a slash.', ['@path' => $path]));
      }
    }

    foreach ($this->lines($form_state->getValue('sites')) as $line) {
      $parts = array_map('trim', explode('|', $line));
      // Site, one hostname per environment, then the language list.
      if (count($parts) !== count(self::ENVIRONMENTS) + 2) {
        $form_state->setErrorByName('sites', $this->t('Line "@line" has to read site|dev|stage|prod|languages.', ['@line' => $line]));
        continue;
      }
      if (in_array('', array_slice($parts, 0, count(self::ENVIRONMENTS) + 1), TRUE)) {
        $form_state->setErrorByName('sites', $this->t('Line "@line" is missing a site or a hostname.', ['@line' => $line]));
        continue;
      }
      foreach (array_slice($parts, 1, count(self::ENVIRONMENTS)) as $host) {
        if (str_contains($host, '/')) {
          $form_state->setErrorByName('sites', $this->t('"@host" has to be a hostname without a scheme or path.', ['@host' => $host]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config(WarmerRunner::CONFIG_NAME)
      ->set('concurrency', (int) $form_state->getValue('concurrency'))
      ->set('request_timeout', (int) $form_state->getValue('request_timeout'))
      ->set('paths', $this->lines($form_state->getValue('paths')))
      ->set('sites', $this->textToSites($form_state->getValue('sites')))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Warms this site through the batch system.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function warmNow(array &$form, FormStateInterface $form_state): void {
    $urls = $this->runner->buildUrls();
    if (!$urls) {
      $this->messenger()->addWarning($this->t('This site has no country group with languages, so there is nothing to warm.'));
      return;
    }

    // One operation per batch of concurrent requests: the whole chunk is in
    // flight at once, so a chunk takes about as long as its slowest URL and
    // stays well inside the web server request limit.
    $operations = [];
    foreach (array_chunk($urls, $this->runner->getConcurrency()) as $chunk) {
      $operations[] = [self::class . '::warmChunk', [$chunk]];
    }

    batch_set([
      'title' => $this->t('Warming @count URL(s)', ['@count' => count($urls)]),
      'operations' => $operations,
      'finished' => self::class . '::warmFinished',
      'progress_message' => $this->t('Warmed @current of @total batches.'),
    ]);
  }

  /**
   * Batch operation: warms one chunk of URLs.
   *
   * @param string[] $urls
   *   The URLs of this chunk.
   * @param array|\ArrayAccess $context
   *   The batch context.
   */
  public static function warmChunk(array $urls, &$context): void {
    $runner = \Drupal::service('bebbo_custom_general.warmer_runner');
    $result = $runner->warm($urls);

    $context['results']['total'] = ($context['results']['total'] ?? 0) + count($urls);
    $context['results']['warmed'] = ($context['results']['warmed'] ?? 0) + $result['warmed'];
    $context['results']['failures'] = ($context['results']['failures'] ?? []) + $result['failures'];
    $context['results']['started'] = $context['results']['started'] ?? microtime(TRUE);
    $context['message'] = t('@count URL(s) warmed so far.', ['@count' => $context['results']['warmed']]);
  }

  /**
   * Batch callback: records the finished run and reports it.
   *
   * @param bool $success
   *   Whether the batch ran to completion.
   * @param array $results
   *   The accumulated results.
   * @param array $operations
   *   The operations left unprocessed.
   */
  public static function warmFinished($success, array $results, array $operations): void {
    $runner = \Drupal::service('bebbo_custom_general.warmer_runner');
    $messenger = \Drupal::messenger();

    if (!$success) {
      $messenger->addError(t('The warm-up stopped before it finished.'));
    }

    $runner->recordRun([
      'total' => $results['total'] ?? 0,
      'warmed' => $results['warmed'] ?? 0,
      'failures' => $results['failures'] ?? [],
      'duration' => microtime(TRUE) - ($results['started'] ?? microtime(TRUE)),
      'trigger' => 'form',
      'status' => $success ? '' : WarmerRunner::STATUS_ABORTED,
    ]);

    $failed = count($results['failures'] ?? []);
    $messenger->addStatus(t('@warmed of @total URL(s) warmed.', [
      '@warmed' => $results['warmed'] ?? 0,
      '@total' => $results['total'] ?? 0,
    ]));
    if ($failed) {
      $messenger->addWarning(t('@count URL(s) failed. The Logs tab lists them.', ['@count' => $failed]));
    }
  }

  /**
   * Renders the per-site settings as editable lines.
   *
   * @param array $sites
   *   Hostnames and languages keyed by site directory.
   *
   * @return string
   *   One site per line.
   */
  protected function sitesToText(array $sites): string {
    $lines = [];
    foreach ($sites as $site => $settings) {
      $line = [$site];
      foreach (self::ENVIRONMENTS as $environment) {
        $line[] = $settings['hosts'][$environment] ?? '';
      }
      $line[] = implode(',', $settings['languages'] ?? []);
      $lines[] = implode('|', $line);
    }
    return implode("\n", $lines);
  }

  /**
   * Parses the per-site textarea back into a mapping.
   *
   * @param string $text
   *   The submitted textarea.
   *
   * @return array
   *   Hostnames and languages keyed by site directory.
   */
  protected function textToSites(string $text): array {
    $sites = [];
    foreach ($this->lines($text) as $line) {
      $parts = array_map('trim', explode('|', $line));
      $site = array_shift($parts);
      $languages = array_pop($parts);

      $hosts = [];
      foreach (self::ENVIRONMENTS as $index => $environment) {
        $hosts[$environment] = $parts[$index];
      }

      $sites[$site] = [
        'hosts' => $hosts,
        'languages' => array_values(array_filter(array_map('trim', explode(',', $languages)))),
      ];
    }
    return $sites;
  }

  /**
   * Splits a textarea into trimmed, non-empty lines.
   *
   * @param string $text
   *   The textarea value.
   *
   * @return string[]
   *   The lines.
   */
  protected function lines(string $text): array {
    $lines = array_map('trim', explode("\n", $text));
    return array_values(array_filter($lines));
  }

}
