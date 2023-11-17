<?php

namespace Drupal\symfony_mailer;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManager;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\symfony_mailer\Processor\EmailBuilderManagerInterface;

/**
 * Provides a Symfony Mailer replacement for MailManager.
 */
class MailManagerReplacement extends MailManager implements MailManagerReplacementInterface {

  /**
   * The email factory.
   *
   * @var \Drupal\symfony_mailer\EmailFactoryInterface
   */
  protected $emailFactory;

  /**
   * The email builder manager.
   *
   * @var \Drupal\symfony_mailer\Processor\EmailBuilderManagerInterface
   */
  protected $emailBuilderManager;

  /**
   * The legacy mailer helper.
   *
   * @var \Drupal\symfony_mailer\LegacyMailerHelperInterface
   */
  protected $legacyHelper;

  /**
   * Constructs the MailManagerReplacement object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\symfony_mailer\EmailFactoryInterface $email_factory
   *   The email factory.
   * @param \Drupal\symfony_mailer\Processor\EmailBuilderManagerInterface $email_builder_manager
   *   The email builder manager.
   * @param \Drupal\symfony_mailer\LegacyMailerHelperInterface $legacy_helper
   *   The legacy mailer helper.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, TranslationInterface $string_translation, RendererInterface $renderer, EmailFactoryInterface $email_factory, EmailBuilderManagerInterface $email_builder_manager, LegacyMailerHelperInterface $legacy_helper) {
    parent::__construct($namespaces, $cache_backend, $module_handler, $config_factory, $logger_factory, $string_translation, $renderer);
    $this->emailFactory = $email_factory;
    $this->emailBuilderManager = $email_builder_manager;
    $this->legacyHelper = $legacy_helper;
  }

  /**
   * {@inheritdoc}
   */
  public function mail($module, $key, $to, $langcode, $params = [], $reply = NULL, $send = TRUE) {
    $message = [
      'id' => $module . '_' . $key,
      'module' => $module,
      'key' => $key,
      'to' => $to ?: NULL,
      'langcode' => $langcode,
      'params' => $params,
      'reply-to' => $reply,
      'send' => $send,
    ];

    // Create an email from the array.
    $builder = $this->emailBuilderManager->createInstanceFromMessage($message);
    $email = $builder->fromArray($this->emailFactory, $message);

    if ($send) {
      $message['result'] = $email->send();
    }
    else {
      // We set 'result' to NULL, because FALSE indicates an error in sending.
      $message['result'] = NULL;
    }

    $this->legacyHelper->emailToArray($email, $message);
    return $message;
  }

}
