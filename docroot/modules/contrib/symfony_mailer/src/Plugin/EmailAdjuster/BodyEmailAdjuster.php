<?php

namespace Drupal\symfony_mailer\Plugin\EmailAdjuster;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Processor\EmailAdjusterBase;

/**
 * Defines the Body Email Adjuster.
 *
 * @EmailAdjuster(
 *   id = "email_body",
 *   label = @Translation("Body"),
 *   description = @Translation("Sets the email body."),
 * )
 */
class BodyEmailAdjuster extends EmailAdjusterBase implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email) {
    $content = $this->configuration['content'];

    $body = [
      '#type' => 'processed_text',
      '#text' => $content['value'],
      '#format' => $content['format'] ?? filter_default_format(),
    ];

    $variables = $email->getVariables();
    if ($existing_body = $email->getBody()) {
      $variables['body'] = $existing_body;
    }

    if ($variables) {
      $body += [
        '#pre_render' => [
          // Preserve the default pre_render from the element.
          [$this, 'preRenderVariables'],
          ['Drupal\filter\Element\ProcessedText', 'preRenderText'],
        ],
        '#context' => $variables,
      ];
    }

    $email->setBody($body);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form['content'] = [
      '#title' => $this->t('Content'),
      '#type' => 'text_format',
      '#default_value' => $this->configuration['content']['value'] ?? NULL,
      '#format' => $this->configuration['content']['format'] ?? filter_default_format(),
      '#required' => TRUE,
      '#rows' => 10,
      '#description' => $this->t('Email body. This field may support tokens or Twig template syntax – please check the supplied default policy for possible values.'),
    ];

    // @todo Show the available Twig variables / token browser.
    return $form;
  }

  /**
   * Pre-render callback for replacing twig variables.
   */
  public function preRenderVariables(array $body) {
    $twig_service = \Drupal::service('twig');
    $body['#text'] = $twig_service->renderInline($body['#text'], $body['#context']);
    return $body;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRenderVariables'];
  }

}
