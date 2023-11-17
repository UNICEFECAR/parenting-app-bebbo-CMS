<?php

namespace Drupal\symfony_mailer\Plugin\EmailAdjuster;

use Drupal\Component\Utility\Html;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\MailerHelperTrait;
use Drupal\symfony_mailer\Processor\EmailAdjusterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the Wrap and convert Email Adjuster.
 *
 * @EmailAdjuster(
 *   id = "mailer_wrap_and_convert",
 *   label = @Translation("Wrap and convert"),
 *   description = @Translation("Wraps the email and converts to plain text."),
 *   weight = 800,
 * )
 */
class WrapAndConvertEmailAdjuster extends EmailAdjusterBase implements ContainerFactoryPluginInterface {

  use MailerHelperTrait;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The module handler to invoke the alter hook.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RendererInterface $renderer, ModuleHandlerInterface $module_handler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->renderer = $renderer;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('renderer'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email) {
    if ($this->configuration['swiftmailer'] && $this->moduleHandler->moduleExists('swiftmailer')) {
      // Add the CSS library to match Swiftmailer.
      $theme = $email->getTheme();
      $email->addLibrary("$theme/swiftmailer");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postRender(EmailInterface $email) {
    $orig_html = $email->getHtmlBody();
    $plain = $html = NULL;

    if ($orig_html && !$this->configuration['plain']) {
      $html = $this->render($email, $orig_html, TRUE);
    }
    $email->setHtmlBody($html);

    if ($orig_plain = $email->getTextBody()) {
      // To wrap the plain text we need to convert to HTML to render the
      // template then convert back again. We avoid check_markup() as it would
      // convert URLs to links.
      // @todo Inefficient? Could set second parameter to `{{ body }}` then
      // search and replace with the actual body after.
      $plain = $this->render($email, _filter_autop(Html::escape($orig_plain)), FALSE);
    }
    elseif ($orig_html) {
      // Wrap plain text.
      $plain = $this->render($email, $orig_html, FALSE);
    }

    if ($plain) {
      $email->setTextBody($this->helper()->htmlToText($plain));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form['plain'] = [
      '#title' => $this->t('Plain text'),
      '#type' => 'checkbox',
      '#default_value' => $this->configuration['plain'] ?? NULL,
      '#description' => $this->t('Send as plain text only.'),
    ];

    $form['swiftmailer'] = [
      '#title' => $this->t('Emulate swiftmailer'),
      '#type' => 'checkbox',
      '#default_value' => $this->configuration['swiftmailer'] ?? NULL,
      '#description' => $this->t('Emulate wrapping from the swiftmailer module. This is intended as a short-term workaround and you should migrate to the new template when possible.'),
      '#access' => $this->moduleHandler->moduleExists('swiftmailer'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $titles = [
      'plain' => $this->t('Plain text'),
      'swiftmailer' => $this->t('Emulate swiftmailer'),
    ];
    foreach ($this->configuration as $id => $value) {
      if ($value) {
        $summary[] = $titles[$id];
      }
    }

    return implode(', ', $summary ?? []);
  }

  /**
   * Renders a body string using the wrapper template.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email being processed.
   * @param string $body
   *   The body string to wrap.
   * @param bool $is_html
   *   True if generating HTML output, false for plain text.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The wrapped body.
   */
  protected function render(EmailInterface $email, string $body, bool $is_html) {
    if ($this->configuration['swiftmailer'] && $this->moduleHandler->moduleExists('swiftmailer') && ($message = $email->getParam('legacy_message'))) {
      $message['body'] = Markup::create($body);
      $message['subject'] = $email->getSubject();
      $render = [
        '#theme' => $message['params']['theme'] ?? 'swiftmailer',
        '#message' => $message,
        '#is_html' => $is_html,
      ];
    }
    else {
      $render = [
        '#theme' => 'email_wrap',
        '#email' => $email,
        '#body' => Markup::create($body),
        '#is_html' => $is_html,
      ];
    }

    return $this->renderer->renderPlain($render);
  }

}
