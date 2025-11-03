<?php

namespace Drupal\pb_custom_form\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure redirect management settings.
 */
class RedirectManagementForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'pb_custom_form.language_redirects',
      'pb_custom_form.landing_pages',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'pb_custom_form_redirect_management';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $language_config = $this->config('pb_custom_form.language_redirects');
    $landing_config = $this->config('pb_custom_form.landing_pages');

    $form['#tree'] = TRUE;

    $form['description'] = [
      '#markup' => '<p>' . $this->t('Manage language-specific redirects and landing pages that bypass redirects.') . '</p>',
    ];

    // Language Redirects Section.
    $form['language_redirects'] = [
      '#type' => 'details',
      '#title' => $this->t('Language Redirects'),
      '#description' => $this->t('Configure redirect URLs for different language codes.'),
      '#open' => TRUE,
    ];

    // Parse existing redirects.
    $existing_redirects = $this->parseRedirectUrls($language_config->get('redirect_urls'));

    // Get number of redirects from form state or existing config.
    $num_redirects = $form_state->get('num_redirects');
    if ($num_redirects === NULL) {
      $num_redirects = count($existing_redirects) ?: 1;
      $form_state->set('num_redirects', $num_redirects);
    }

    $form['language_redirects']['redirects_table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Language Code'),
        $this->t('Redirect URL'),
        $this->t('Actions'),
      ],
      '#empty' => $this->t('No redirects configured.'),
    ];
    $redirects_table = $form_state->getValue(['language_redirects', 'redirects_table']);
    if (!$redirects_table) {
      $redirects_table = [];
      foreach ($existing_redirects as $lang_code => $url) {
        $redirects_table[] = [
          'language_code' => $lang_code,
          'redirect_url' => $url,
        ];
      }
      while (count($redirects_table) < $num_redirects) {
        $redirects_table[] = ['language_code' => '', 'redirect_url' => ''];
      }
    }

    // Add existing redirects and new rows.
    for ($i = 0; $i < $num_redirects; $i++) {
      $language_code = $redirects_table[$i]['language_code'] ?? '';
      $redirect_url = $redirects_table[$i]['redirect_url'] ?? '';

      $form['language_redirects']['redirects_table'][$i]['language_code'] = [
        '#type' => 'textfield',
        '#default_value' => $language_code,
        '#size' => 5,
        '#maxlength' => 10,
        '#placeholder' => 'e.g., ru, sq',
      ];

      $form['language_redirects']['redirects_table'][$i]['redirect_url'] = [
        '#type' => 'url',
        '#default_value' => $redirect_url,
        '#size' => 60,
        '#maxlength' => 500,
        '#placeholder' => 'https://example.com/redirect-url',
      ];

      $form['language_redirects']['redirects_table'][$i]['remove'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#name' => 'remove_redirect_' . $i,
        '#submit' => ['::removeRedirectRow'],
        '#ajax' => [
          'callback' => '::ajaxCallback',
          'wrapper' => 'redirect-management-wrapper',
        ],
        '#limit_validation_errors' => [],
      ];
    }

    $form['language_redirects']['add_redirect'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Language Redirect'),
      '#submit' => ['::addRedirectRow'],
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'wrapper' => 'redirect-management-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];

    // Landing Pages Section.
    $form['landing_pages'] = [
      '#type' => 'details',
      '#title' => $this->t('Landing Pages'),
      '#description' => $this->t('Configure landing pages that should bypass the language-based redirects.'),
      '#open' => TRUE,
    ];

    // Parse existing landing pages.
    $existing_pages = $this->parseLandingPages($landing_config->get('landing_pages'));

    // Get number of landing pages from form state or existing config.
    $num_pages = $form_state->get('num_pages');
    if ($num_pages === NULL) {
      $num_pages = count($existing_pages) ?: 1;
      $form_state->set('num_pages', $num_pages);
    }

    $form['landing_pages']['pages_table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Landing Page Path'),
        $this->t('Actions'),
      ],
      '#empty' => $this->t('No landing pages configured.'),
    ];
    $pages_table = $form_state->getValue(['landing_pages', 'pages_table']);
    if (!$pages_table) {
      $pages_table = [];
      foreach ($existing_pages as $page_path) {
        $pages_table[] = ['page_path' => $page_path];
      }
      while (count($pages_table) < $num_pages) {
        $pages_table[] = ['page_path' => ''];
      }
    }

    // Add existing pages and new rows.
    for ($i = 0; $i < $num_pages; $i++) {
      $page_path = $pages_table[$i]['page_path'] ?? '';

      $form['landing_pages']['pages_table'][$i]['page_path'] = [
        '#type' => 'textfield',
        '#default_value' => $page_path,
        '#size' => 40,
        '#maxlength' => 255,
        '#placeholder' => '/homepage',
      ];

      $form['landing_pages']['pages_table'][$i]['remove'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#name' => 'remove_page_' . $i,
        '#submit' => ['::removePageRow'],
        '#ajax' => [
          'callback' => '::ajaxCallback',
          'wrapper' => 'redirect-management-wrapper',
        ],
        '#limit_validation_errors' => [],
      ];
    }

    $form['landing_pages']['add_page'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Landing Page'),
      '#submit' => ['::addPageRow'],
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'wrapper' => 'redirect-management-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];

    $form['#prefix'] = '<div id="redirect-management-wrapper">';
    $form['#suffix'] = '</div>';

    return parent::buildForm($form, $form_state);
  }

  /**
   * AJAX callback for form updates.
   */
  public function ajaxCallback(array &$form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * Submit handler to add a redirect row.
   */
  public function addRedirectRow(array &$form, FormStateInterface $form_state) {
    $num_redirects = $form_state->get('num_redirects');
    $form_state->set('num_redirects', $num_redirects + 1);
    $form_state->setRebuild();
  }

  /**
   * Submit handler to remove a redirect row.
   */
  public function removeRedirectRow(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if (preg_match('/remove_redirect_(\d+)/', $button_name, $matches)) {
      $row_to_remove = (int) $matches[1];

      $user_input = $form_state->getUserInput();
      $current_redirects = $user_input['language_redirects']['redirects_table'] ?? [];
      unset($current_redirects[$row_to_remove]);
      $current_redirects = array_values($current_redirects);
      $form_state->setValue(['language_redirects', 'redirects_table'], $current_redirects);
      $user_input['language_redirects']['redirects_table'] = $current_redirects;
      $form_state->setUserInput($user_input);

      $num_redirects = $form_state->get('num_redirects');
      if ($num_redirects > 1) {
        $form_state->set('num_redirects', $num_redirects - 1);
      }
    }

    $form_state->setRebuild();
  }

  /**
   * Submit handler to add a landing page row.
   */
  public function addPageRow(array &$form, FormStateInterface $form_state) {
    $num_pages = $form_state->get('num_pages');
    $form_state->set('num_pages', $num_pages + 1);
    $form_state->setRebuild();
  }

  /**
   * Submit handler to remove a landing page row.
   */
  public function removePageRow(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if (preg_match('/remove_page_(\d+)/', $button_name, $matches)) {
      $row_to_remove = (int) $matches[1];

      // Get current user input from the form.
      $user_input = $form_state->getUserInput();
      $current_pages = $user_input['landing_pages']['pages_table'] ?? [];

      // Remove the specific row.
      unset($current_pages[$row_to_remove]);
      $current_pages = array_values($current_pages);

      $form_state->setValue(['landing_pages', 'pages_table'], $current_pages);
      $user_input['landing_pages']['pages_table'] = $current_pages;
      $form_state->setUserInput($user_input);

      $num_pages = $form_state->get('num_pages');
      if ($num_pages > 1) {
        $form_state->set('num_pages', $num_pages - 1);
      }
    }

    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Validate language redirects.
    $redirects_table = $form_state->getValue(['language_redirects', 'redirects_table']);
    if ($redirects_table) {
      $language_codes = [];
      foreach ($redirects_table as $i => $redirect) {
        $language_code = trim($redirect['language_code']);
        $redirect_url = trim($redirect['redirect_url']);

        // Skip empty rows.
        if (empty($language_code) && empty($redirect_url)) {
          continue;
        }

        // Validate language code.
        if (empty($language_code)) {
          $form_state->setErrorByName("language_redirects][redirects_table][$i][language_code", $this->t('Language code is required.'));
        }
        elseif (!preg_match('/^[a-z]{2}(-[a-z]{2,3})?$/i', $language_code)) {
          $form_state->setErrorByName("language_redirects][redirects_table][$i][language_code", $this->t('Language code must be in format like "en", "ru", "en-us", or "me-cnr".'));
        }
        elseif (in_array($language_code, $language_codes)) {
          $form_state->setErrorByName("language_redirects][redirects_table][$i][language_code", $this->t('Duplicate language code: %code', ['%code' => $language_code]));
        }
        else {
          $language_codes[] = $language_code;
        }

        // Validate redirect URL.
        if (empty($redirect_url)) {
          $form_state->setErrorByName("language_redirects][redirects_table][$i][redirect_url", $this->t('Redirect URL is required.'));
        }
        elseif (!filter_var($redirect_url, FILTER_VALIDATE_URL)) {
          $form_state->setErrorByName("language_redirects][redirects_table][$i][redirect_url", $this->t('Invalid URL format.'));
        }
      }
    }

    // Validate landing pages.
    $pages_table = $form_state->getValue(['landing_pages', 'pages_table']);
    if ($pages_table) {
      $page_paths = [];
      foreach ($pages_table as $i => $page) {
        $page_path = trim($page['page_path']);

        // Skip empty rows.
        if (empty($page_path)) {
          continue;
        }

        // Validate page path.
        if (!str_starts_with($page_path, '/')) {
          $form_state->setErrorByName("landing_pages][pages_table][$i][page_path", $this->t('Path must start with a forward slash (/).'));
        }
        elseif (in_array($page_path, $page_paths)) {
          $form_state->setErrorByName("landing_pages][pages_table][$i][page_path", $this->t('Duplicate page path: %path', ['%path' => $page_path]));
        }
        else {
          $page_paths[] = $page_path;
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Process language redirects.
    $redirects_table = $form_state->getValue(['language_redirects', 'redirects_table']);
    $redirect_lines = [];
    if ($redirects_table) {
      foreach ($redirects_table as $redirect) {
        $language_code = trim($redirect['language_code']);
        $redirect_url = trim($redirect['redirect_url']);

        if (!empty($language_code) && !empty($redirect_url)) {
          $redirect_lines[] = $language_code . '|' . $redirect_url;
        }
      }
    }

    // Process landing pages.
    $pages_table = $form_state->getValue(['landing_pages', 'pages_table']);
    $page_lines = [];
    if ($pages_table) {
      foreach ($pages_table as $page) {
        $page_path = trim($page['page_path']);

        if (!empty($page_path)) {
          $page_lines[] = $page_path;
        }
      }
    }

    // Save language redirects.
    $this->config('pb_custom_form.language_redirects')
      ->set('redirect_urls', implode("\n", $redirect_lines))
      ->save();

    // Save landing pages.
    $this->config('pb_custom_form.landing_pages')
      ->set('landing_pages', implode("\n", $page_lines))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Parse redirect URLs from configuration text.
   *
   * @param string|null $redirect_urls_text
   *   The redirect URLs configuration text.
   *
   * @return array
   *   An array of language code => redirect URL mappings.
   */
  protected function parseRedirectUrls($redirect_urls_text) {
    $redirect_urls = [];

    if (empty($redirect_urls_text)) {
      return $redirect_urls;
    }

    $lines = array_filter(array_map('trim', explode("\n", $redirect_urls_text)));

    foreach ($lines as $line) {
      if (strpos($line, '|') !== FALSE) {
        [$language_code, $redirect_url] = array_map('trim', explode('|', $line, 2));
        if (!empty($language_code) && !empty($redirect_url)) {
          $redirect_urls[$language_code] = $redirect_url;
        }
      }
    }

    return $redirect_urls;
  }

  /**
   * Parse landing pages from configuration text.
   *
   * @param string|null $landing_pages_text
   *   The landing pages configuration text.
   *
   * @return array
   *   An array of landing page paths.
   */
  protected function parseLandingPages($landing_pages_text) {
    if (empty($landing_pages_text)) {
      return [];
    }
    return array_filter(array_map('trim', explode("\n", $landing_pages_text)));
  }

}
