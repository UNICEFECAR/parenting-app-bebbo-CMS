<?php

namespace Drupal\pb_custom_form\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\bebbo_custom_general\StoreUrl;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Admin form for setting Force Content Update or Force App Update per country.
 *
 * @Action(
 *   id = "pb_custom_form_action",
 *   label = @Translation("Force Update API Check"),
 *   type = "node",
 *   confirm = FALSE
 * )
 */
class CustomForm extends FormBase {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a CustomForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'forcefull_check_update_api';
  }

  /**
   * {@inheritdoc}
   *
   * Builds the force-update admin form with country selection, update type
   * dropdown, enable/disable flag, and conditional app store URL fields.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $groups = $this->entityTypeManager->getStorage('group')->loadMultiple();
    $country_group = [];
    foreach ($groups as $group) {
      $id = $group->id();
      $label = $group->label();
      $country_group[$id] = $label;
    }
    asort($country_group);

    $form['country_select'] = [
      '#type' => 'select',
      '#title' => $this->t('Country'),
      '#options' => $country_group,
      '#required' => TRUE,
    ];

    if (count($country_group) === 1) {
      $form['country_select']['#default_value'] = key($country_group);
    }

    // Replaces the old "Force Update Check" checkbox.
    $form['update_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Force Update'),
      '#options' => [
        'content_update' => $this->t('Force Content Update'),
        'app_update' => $this->t('Force App Update'),
      ],
      '#required' => TRUE,
    ];

    $form['flag'] = [
      '#type' => 'select',
      '#title' => $this->t('Flag'),
      '#options' => [
        '1' => $this->t('Enable'),
        '0' => $this->t('Disable'),
      ],
      '#required' => TRUE,
    ];

    $form['google_play_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Google Play'),
      '#description' => $this->t('Enter the new app store listing URL if a new app has been created.'),
      '#maxlength' => 512,
      '#states' => [
        'visible' => [
          ':input[name="update_type"]' => ['value' => 'app_update'],
          ':input[name="flag"]' => ['value' => '1'],
        ],
      ],
    ];

    $form['app_store_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('App Store'),
      '#description' => $this->t('Enter the new app store listing URL if a new app has been created.'),
      '#maxlength' => 512,
      '#states' => [
        'visible' => [
          ':input[name="update_type"]' => ['value' => 'app_update'],
          ':input[name="flag"]' => ['value' => '1'],
        ],
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Both fields only apply to an app update, and are optional even then:
    // they are filled in when a new app has been published elsewhere.
    if ($form_state->getValue('update_type') !== 'app_update' || $form_state->getValue('flag') !== '1') {
      return;
    }

    $google_play_url = trim((string) $form_state->getValue('google_play_url'));
    if ($google_play_url !== '' && !StoreUrl::isGooglePlay($google_play_url)) {
      $form_state->setErrorByName('google_play_url', $this->t('Enter a Google Play listing URL, for example https://play.google.com/store/apps/details?id=org.unicef.ecar.bebbo.'));
    }

    $app_store_url = trim((string) $form_state->getValue('app_store_url'));
    if ($app_store_url !== '' && !StoreUrl::isAppStore($app_store_url)) {
      $form_state->setErrorByName('app_store_url', $this->t('Enter an App Store listing URL, for example https://apps.apple.com/app/bebbo-parenting-app/id1588918146.'));
    }
  }

  /**
   * {@inheritdoc}
   *
   * Redirects to the confirmation form passing all values as query parameters.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    global $base_url;

    $flag = $form_state->getValue('flag');
    $country_id = $form_state->getValue('country_select');
    $update_type = $form_state->getValue('update_type');
    $google_play_url = $form_state->getValue('google_play_url') ?? '';
    $app_store_url = $form_state->getValue('app_store_url') ?? '';
    $country_label = $form['country_select']['#options'][$country_id];

    $query_params = http_build_query([
      'flag' => $flag,
      'country_id' => $country_id,
      'country_name' => $country_label,
      'update_type' => $update_type,
      'google_play_url' => $google_play_url,
      'app_store_url' => $app_store_url,
    ]);

    $path = $base_url . '/forcefull-update-check?' . $query_params;
    pb_custom_form_my_goto($path);
  }

}
