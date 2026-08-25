<?php

namespace Drupal\bebbo_custom_general\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\bebbo_custom_general\StoreUrl;

/**
 * Configure app store redirect URLs for QR code landing page.
 */
class AppStoreRedirectForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['bebbo_custom_general.app_store_redirect'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'pb_custom_form_app_store_redirect';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('bebbo_custom_general.app_store_redirect');

    $form['landing_page'] = [
      '#type' => 'item',
      '#title' => $this->t('Landing page'),
      '#markup' => $this->t('Visitors are redirected from <a href=":url" target="_blank">:url</a>, the page QR codes should point to.', [':url' => $this->landingPageUrl()]),
    ];

    $form['app_store_url'] = [
      '#type' => 'url',
      '#title' => $this->t('App Store listing'),
      '#description' => $this->t('e.g. https://apps.apple.com/app/bebbo-parenting-app/id1588918146'),
      '#default_value' => $config->get('app_store_url'),
      '#maxlength' => 500,
    ];

    $form['google_play_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Google Play listing'),
      '#description' => $this->t('e.g. https://play.google.com/store/apps/details?id=org.unicef.ecar.bebbo'),
      '#default_value' => $config->get('google_play_url'),
      '#maxlength' => 500,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $app_store_url = (string) $form_state->getValue('app_store_url');
    if ($app_store_url !== '' && !StoreUrl::isAppStore($app_store_url)) {
      $form_state->setErrorByName('app_store_url', $this->t('Enter an App Store listing URL, for example https://apps.apple.com/app/bebbo-parenting-app/id1588918146.'));
    }

    $google_play_url = (string) $form_state->getValue('google_play_url');
    if ($google_play_url !== '' && !StoreUrl::isGooglePlay($google_play_url)) {
      $form_state->setErrorByName('google_play_url', $this->t('Enter a Google Play listing URL, for example https://play.google.com/store/apps/details?id=org.unicef.ecar.bebbo.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('bebbo_custom_general.app_store_redirect')
      ->set('app_store_url', $form_state->getValue('app_store_url'))
      ->set('google_play_url', $form_state->getValue('google_play_url'))
      ->save();

    $this->messenger()->addStatus($this->t('The configuration options have been saved. The redirect page is live at <a href=":url" target="_blank">:url</a>.', [':url' => $this->landingPageUrl()]));
  }

  /**
   * Builds the absolute URL of the public redirect page.
   *
   * @return string
   *   The absolute /downloadapp.html URL for the current site.
   */
  protected function landingPageUrl() {
    return Url::fromRoute('bebbo_custom_general.app_store_redirect_page')
      ->setAbsolute()
      ->toString();
  }

}
