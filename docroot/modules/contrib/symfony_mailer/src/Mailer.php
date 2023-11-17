<?php

namespace Drupal\symfony_mailer;

use Drupal\Core\Language\LanguageDefault;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Url;
use Drupal\symfony_mailer\Exception\MissingTransportException;
use Drupal\symfony_mailer\Exception\SkipMailException;
use Drupal\user\Entity\User;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a Mailer service based on Symfony Mailer.
 */
class Mailer implements MailerInterface {

  use StringTranslationTrait;

  /**
   * The event dispatcher to notify of routes.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The language default.
   *
   * @var \Drupal\Core\Language\LanguageDefault
   */
  protected $languageDefault;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * The theme initialization.
   *
   * @var \Drupal\Core\Theme\ThemeInitializationInterface
   */
  protected $themeInitialization;

  /**
   * Account switcher.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface
   */
  protected $accountSwitcher;

  /**
   * The transport manager.
   *
   * @var \Symfony\Component\Mailer\Transport
   */
  protected $transportManager;

  /**
   * Constructs the Mailer object.
   *
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Language\LanguageDefault $language_default
   *   The default language.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager.
   * @param \Drupal\Core\Theme\ThemeInitializationInterface $theme_initialization
   *   The theme initialization.
   * @param \Drupal\Core\Session\AccountSwitcherInterface $account_switcher
   *   The account switcher service.
   * @param \Drupal\symfony_mailer\TransportFactoryManagerInterface $transport_factory_manager
   *   The transport factory manager.
   */
  public function __construct(EventDispatcherInterface $dispatcher, RendererInterface $renderer, LanguageDefault $language_default, LanguageManagerInterface $language_manager, LoggerChannelFactoryInterface $logger_factory, AccountInterface $account, ThemeManagerInterface $theme_manager, ThemeInitializationInterface $theme_initialization, AccountSwitcherInterface $account_switcher, TransportFactoryManagerInterface $transport_factory_manager) {
    $this->dispatcher = $dispatcher;
    $this->renderer = $renderer;
    $this->languageDefault = $language_default;
    $this->languageManager = $language_manager;
    $this->loggerFactory = $logger_factory;
    $this->account = $account;
    $this->themeManager = $theme_manager;
    $this->themeInitialization = $theme_initialization;
    $this->accountSwitcher = $account_switcher;
    $this->transportManager = new Transport($transport_factory_manager->getFactories());
  }

  /**
   * {@inheritdoc}
   */
  public function send(InternalEmailInterface $email) {
    // Mailing can invoke rendering (e.g., generating URLs, replacing tokens),
    // but e-mails are not HTTP responses: they're not cached, they don't have
    // attachments. Therefore we perform mailing inside its own render context,
    // to ensure it doesn't leak into the render context for the HTTP response
    // to the current request.
    return $this->renderer->executeInRenderContext(new RenderContext(), function () use ($email) {
      try {
        return $this->doSend($email);
      }
      catch (SkipMailException $e) {
        if ($this->account->hasPermission('administer mailer')) {
          \Drupal::messenger()->addError($this->t('Email sending skipped: %message.', [
            '%message' => $e->getMessage(),
          ]));
        }
      }
    });
  }

  /**
   * Sends an email.
   *
   * @param \Drupal\symfony_mailer\InternalEmailInterface $email
   *   The email to send.
   *
   * @return bool
   *   Whether successful.
   *
   * @internal
   */
  public function doSend(InternalEmailInterface $email) {
    // LegacyEmailBuilder sets the theme during the process phase. Save the
    // active theme so we can change back.
    $active_theme_name = $this->themeManager->getActiveTheme()->getName();

    // Process the build phase.
    // @see \Drupal\symfony_mailer\EmailInterface::PHASE_BUILD
    $email->process();

    // Do switching.
    $current_langcode = $this->languageManager->getCurrentLanguage()->getId();
    if ($email->getParam('__disable_customize__')) {
      // Undocumented setting for use from LegacyEmailBuilder only for
      // back-compatibility. This may change without notice.
      //
      // By default, the language code and account are customized based on the
      // recipient ('To' address). This setting disables customization, and
      // leaves the language and account unchanged. Normally this is a bad idea
      // and it can even expose private information from rendering an entity in
      // the context of a privileged user.
      $langcode = $current_langcode;
      $account = $this->account;
    }
    else {
      $this->changeTheme($email->getTheme());

      // Determine langcode and account from the to address, if there is
      // agreement.
      $langcodes = $accounts = [];
      foreach ($email->getTo() as $to) {
        if ($loop_langcode = $to->getLangcode()) {
          $langcodes[$loop_langcode] = $loop_langcode;
        }
        if ($loop_account = $to->getAccount()) {
          $accounts[$loop_account->id()] = $loop_account;
        }
      }
      $langcode = (count($langcodes) == 1) ? reset($langcodes) : $this->languageManager->getDefaultLanguage()->getId();
      $account = (count($accounts) == 1) ? reset($accounts) : User::getAnonymousUser();
    }

    $email->customize($langcode, $account);

    $must_switch_account = $account->id() != $this->account->id();

    if ($must_switch_account) {
      $this->accountSwitcher->switchTo($account);
    }

    $must_switch_language = $langcode !== $current_langcode;

    if ($must_switch_language) {
      $this->changeActiveLanguage($langcode);
    }

    try {
      // Process the pre-render phase.
      // @see \Drupal\symfony_mailer\EmailInterface::PHASE_PRE_RENDER
      $email->process();

      // Render.
      $email->render();

      // Process the post-render phase.
      // @see \Drupal\symfony_mailer\EmailInterface::PHASE_POST_RENDER
      $email->process();
    }
    finally {
      // Switch back.
      if ($must_switch_account) {
        $this->accountSwitcher->switchBack();
      }

      if ($must_switch_language) {
        $this->changeActiveLanguage($current_langcode);
      }

      $this->changeTheme($active_theme_name);
    }

    try {
      // Send.
      $symfony_email = $email->getSymfonyEmail();
      $transport_dsn = $email->getTransportDsn();
      if (empty($transport_dsn)) {
        throw new MissingTransportException();
      }

      $transport = $this->transportManager->fromString($transport_dsn);
      $mailer = new SymfonyMailer($transport, NULL, $this->dispatcher);

      // ksm($email, $symfony_email->getHeaders());
      $mailer->send($symfony_email);
      $result = TRUE;
    }
    catch (\Exception $e) {
      if ($e instanceof MissingTransportException) {
        $message = $this->t('Missing email transport: please <a href=":url">configure a default</a>.', [
          ':url' => Url::fromRoute('entity.mailer_transport.collection')->toString(),
        ]);
      }
      else {
        $message = $e->getMessage();
      }
      $email->setError($message);

      // Log.
      $params = ['%message' => $message];
      $this->loggerFactory->get('mailer')->error('Error sending email: %message', $params);

      // Messenger.
      if (!$this->account->hasPermission('administer mailer')) {
        // Hide the detailed message and show a generic one instead.
        $message = $this->t('Unable to send email. Contact the site administrator if the problem persists.');
      }

      \Drupal::messenger()->addError($message);
      $result = FALSE;
    }

    // Process the post-send phase.
    // @see \Drupal\symfony_mailer\EmailInterface::PHASE_POST_SEND
    $email->process();

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function changeTheme(string $theme_name) {
    $active_theme_name = $this->themeManager->getActiveTheme()->getName();
    if ($theme_name !== $active_theme_name) {
      $this->themeManager->setActiveTheme($this->themeInitialization->initTheme($theme_name));
    }

    return $active_theme_name;
  }

  /**
   * Changes the active language for translations.
   *
   * @param string $langcode
   *   The langcode.
   */
  protected function changeActiveLanguage(string $langcode) {
    // Language switching adapted from commerce module.
    // @see \Drupal\commerce\MailHandler::sendMail
    if (!$this->languageManager->isMultilingual()) {
      return;
    }

    $language = $this->languageManager->getLanguage($langcode);
    if (!$language) {
      return;
    }
    // The language manager has no method for overriding the default language,
    // like it does for config overrides. We have to change the default
    // language service's current language.
    // @see https://www.drupal.org/project/drupal/issues/3029010
    $this->languageDefault->set($language);
    $this->languageManager->setConfigOverrideLanguage($language);
    $this->languageManager->reset();

    // The default string_translation service, TranslationManager, has a
    // setDefaultLangcode method. However, this method is not present on either
    // of its interfaces. Therefore we check for the concrete class here so
    // that any swapped service does not break the application.
    // @see https://www.drupal.org/project/drupal/issues/3029003
    $string_translation = $this->getStringTranslation();
    if ($string_translation instanceof TranslationManager) {
      $string_translation->setDefaultLangcode($language->getId());
      $string_translation->reset();
    }
  }

}
