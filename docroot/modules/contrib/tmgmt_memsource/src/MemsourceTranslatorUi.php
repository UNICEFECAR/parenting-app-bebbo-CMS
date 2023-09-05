<?php

namespace Drupal\tmgmt_memsource;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\tmgmt\TranslatorPluginUiBase;
use Drupal\tmgmt\JobInterface;

/**
 * Memsource translator UI.
 */
class MemsourceTranslatorUi extends TranslatorPluginUiBase {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    /** @var \Drupal\tmgmt\TranslatorInterface $translator */
    $translator = $form_state->getFormObject()->getEntity();

    $form['service_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Phrase TMS Home URL'),
      '#default_value' => $translator->getSetting('service_url') ?: 'https://cloud.memsource.com/web',
      '#description' => $this->t('Please enter the Phrase TMS Home URL.'),
      '#required' => TRUE,
      '#placeholder' => 'https://cloud.memsource.com/web',
    ];
    $form['memsource_user_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User name'),
      '#default_value' => $translator->getSetting('memsource_user_name'),
      '#description' => $this->t('Please enter your Phrase TMS user name.'),
      '#required' => TRUE,
      '#placeholder' => 'user name',
    ];

    if (\Drupal::config($translator->getConfigDependencyName())->get('settings') === NULL) {
      $passwordFieldAttributes = [
        '#required' => TRUE,
        '#attributes' => ['value' => $translator->getSetting('memsource_password')],
      ];
    }
    else {
      $form['memsource_change_password'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Change password'),
      ];
      $passwordFieldAttributes = [
        '#states' => [
          'visible' => [
            ':input[name="settings[memsource_change_password]"]' => ['checked' => TRUE],
          ],
          'required' => [
            ':input[name="settings[memsource_change_password]"]' => ['checked' => TRUE],
          ],
        ],
      ];
      if ($translator->getSetting('memsource_change_password')) {
        $passwordFieldAttributes['#attributes'] = ['value' => $translator->getSetting('memsource_password')];
      }
    }

    $form['memsource_password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#description' => $this->t('Please enter your Phrase TMS password.'),
      '#placeholder' => 'password',

    ] + $passwordFieldAttributes;

    $form['memsource_update_job_status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Set Phrase TMS job status to Delivered after import to Drupal'),
      '#default_value' => $translator->getSetting('memsource_update_job_status'),
    ];

    $form += parent::addConnectButton();

    if ($translator->getPlugin()->checkMemsourceConnection($translator)) {
      $form['memsource_connector_token'] = [
        '#type' => 'select',
        '#title' => $this->t('Preview connector'),
        '#description' => $this->t('Please select your preview connector.'),
        '#default_value' => $translator->getSetting('memsource_connector_token'),
        '#empty_option' => $this->t('- Select -'),
        '#options' => $translator->getPlugin()->getDrupalConnectors($translator),
      ];
    } else {
      $form['memsource_connector_token'] = [
        '#type' => 'markup',
        '#markup' => '<div class="messages messages--warning">' . $this->t('Unable to authenticate to Phrase TMS. Please check your login credentials and try to connect again.') . '</div>'
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    if ($form_state->hasAnyErrors()) {
      return;
    }
    /** @var \Drupal\tmgmt\TranslatorInterface $translator */
    $translator = $form_state->getFormObject()->getEntity();
    $currentSettings = \Drupal::config($translator->getConfigDependencyName())->get('settings');
    if ($currentSettings !== NULL && $form_state->getValue(['settings', 'memsource_change_password']) === 0) {
      $translator->setSetting('memsource_password', $currentSettings['memsource_password']);
      $form_state->setValue(['settings', 'memsource_password'], $currentSettings['memsource_password']);
    }
    /** @var \Drupal\tmgmt_memsource\Plugin\tmgmt\Translator\MemsourceTranslator $plugin */
    $plugin = $translator->getPlugin();
    $plugin->setTranslator($translator);
    $result = $plugin->loginToMemsource();
    if (!$result) {
      $form_state->setErrorByName('settings][service_url', $this->t('Login incorrect. Please check the API endpoint, user name and password.'));
    }
    else {
      $pwd = $form_state->getValue(['settings', 'memsource_password']);
      $form_state->setValue(['settings', 'memsource_password'], $plugin->encodePassword($pwd));
      $form_state->setValue(['settings', 'memsource_change_password'], 0);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function checkoutSettingsForm(array $form, FormStateInterface $form_state, JobInterface $job) {

    if (!$job->getTranslator()->getPlugin()->checkMemsourceConnection($job->getTranslator())) {
      return [
        '#type' => 'markup',
        '#markup' => '<div class="messages messages--warning">' . $this->t('Unable to authenticate to Phrase TMS. Please select a different provider or check your login credentials.') . '</div>'
      ];
    }

    $form_values = $form_state->getUserInput();
    $settings = [];
    if (array_key_exists('settings', $form_values)) {
      if (array_key_exists('project_template', $form_values['settings'])) {
        $settings['project_template'] = $form_values['settings']['project_template'];
      }
      if (array_key_exists('due_date', $form_values['settings'])) {
        $settings['due_date'] = $form_values['settings']['due_date'];
      }
      if (array_key_exists('group_jobs', $form_values['settings'])) {
        $settings['group_jobs'] = $form_values['settings']['group_jobs'];
      }
      if (array_key_exists('batch_id', $form_values['settings'])) {
        $settings['batch_id'] = $form_values['settings']['batch_id'];
      }
    }

    // Save the settings and job in between to prevent error:
    // "The job has no provider assigned".
    $job->set('settings', $settings);
    $job->save();

    /** @var \Drupal\tmgmt_memsource\Plugin\tmgmt\Translator\MemsourceTranslator $translator_plugin */
    $translator_plugin = $job->getTranslator()->getPlugin();
    $translator_plugin->setTranslator($job->getTranslator());

    $project_templates = [];
    // phpcs:disable DrupalPractice.CodeAnalysis.VariableAnalysis.UndefinedVariable
    for ($page = 0; !isset($templates_page) || !empty($templates_page['content']); $page++) {
      $templates_page = $translator_plugin->sendApiRequest('/api2/v1/projectTemplates', 'GET', ['pageNumber' => $page]);
      $project_templates = array_merge($project_templates, $templates_page['content']);
    }

    // Get langs from select fields.
    $sourceLang = $job->getRemoteSourceLanguage();
    $targetLang = $job->getRemoteTargetLanguage();

    $templates = [];

    foreach ($project_templates as $template) {
      // Display only templates which match the selected source
      // AND (match the selected target langs OR target langs is empty).
      if (
        $template['sourceLang'] === $sourceLang &&
        (in_array($targetLang, $template['targetLangs'], TRUE) || $template['targetLangs'] === NULL)
      ) {
        $templates[$template['id']] = $template['templateName'];
      }
    }

    natcasesort($templates);

    $options = ['0' => '-'] + $templates;

    $settings['project_template'] = [
      '#type' => 'select',
      '#title' => $this->t('Project template'),
      '#options' => $options,
      '#description' => $this->t('Select a Phrase TMS project template.'),
    ];

    $settings['due_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Due Date'),
      '#date_date_format' => 'Y-m-d',
      '#description' => $this->t('Enter the due date of this translation.'),
      '#default_value' => NULL,
    ];

    $settings['group_jobs'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Group all translation jobs in a single Phrase TMS project.'),
    ];

    $settings['batch_id'] = [
      '#type' => 'hidden',
      '#default_value' => uniqid(),
    ];

    if (version_compare(\Drupal::VERSION, '9.0.0', '>=')) {
      $settings['due_date']['#attributes'] = ['placeholder' => 'YYYY-MM-DD'];
    }

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function checkoutInfo(JobInterface $job) {
    $form = [];

    if ($job->isActive()) {
      $form['actions']['pull'] = [
        '#type' => 'submit',
        '#value' => $this->t('Pull translations'),
        '#submit' => [[$this, 'submitPullTranslations']],
        '#weight' => -10,
      ];
    }

    return $form;
  }

  /**
   * Submit callback to pull translations form Memsource Cloud.
   */
  public function submitPullTranslations(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\tmgmt\Entity\Job $job */
    $job = $form_state->getFormObject()->getEntity();

    /** @var \Drupal\tmgmt_memsource\Plugin\tmgmt\Translator\MemsourceTranslator $translator_plugin */
    $translator_plugin = $job->getTranslator()->getPlugin();
    $result = $translator_plugin->fetchTranslatedFiles($job);
    $translated = $result['translated'];
    $untranslated = $result['untranslated'];
    if (count($result['errors']) === 0) {
      if ($untranslated == 0 && $translated != 0) {
        $job->addMessage('Fetched translations for @translated job items.', ['@translated' => $translated]);
      }
      elseif ($translated == 0) {
        $this->messenger()->addStatus('No job item has been translated yet.');
      }
      else {
        $job->addMessage('Fetched translations for @translated job items, @untranslated are not translated yet.', [
          '@translated' => $translated,
          '@untranslated' => $untranslated,
        ]);
      }
    }
    tmgmt_write_request_messages($job);
  }

}
