<?php

namespace Drupal\pb_custom_form\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountProxy;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event Subscriber for node view redirect.
 */
class InternalContentNodeRedirect implements EventSubscriberInterface {

  /**
   * CurrentRouteMatch var.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * LanguageManager var.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

  /**
   * AccountProxy var.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current path service.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $pathCurrent;

  /**
   * The path alias manager service.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $pathAliasManager;

  /**
   * The page cache kill switch service.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  protected $pageCacheKillSwitch;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Construct method.
   *
   * @inheritDoc
   */
  public function __construct(
    CurrentRouteMatch $route_match,
    LanguageManager $language_manager,
    AccountProxy $current_user,
    EntityTypeManagerInterface $entity_type_manager,
    CurrentPathStack $path_current,
    AliasManagerInterface $path_alias_manager,
    KillSwitch $page_cache_kill_switch,
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->routeMatch = $route_match;
    $this->languageManager = $language_manager;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->pathCurrent = $path_current;
    $this->pathAliasManager = $path_alias_manager;
    $this->pageCacheKillSwitch = $page_cache_kill_switch;
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('current_route_match'),
      $container->get('language_manager'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('path.current'),
      $container->get('path_alias.manager'),
      $container->get('page_cache_kill_switch'),
      $container->get('config.factory'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['nodeViewRedirect', 30];
    return $events;
  }

  /**
   * {@inheritdoc}
   */
  public function nodeViewRedirect(RequestEvent $event) {
    $node = $this->routeMatch->getParameter('node');
    global $base_url;
    $current_path = $this->pathCurrent->getPath();
    $internal = $this->pathAliasManager->getAliasByPath($current_path);
    $current_lang = $this->languageManager->getCurrentLanguage()->getId();

    // Get landing pages from configuration.
    $landing_pages_config = $this->configFactory->get('pb_custom_form.landing_pages');
    $landing_pages = $this->parseLandingPages($landing_pages_config->get('landing_pages'));

    if (!$this->isNodeRoute()) {
      return;
    }

    if (in_array($internal, $landing_pages)) {
      return;
    }
    if (!$this->currentUser->isAnonymous()) {
      return;
    }

    // Get redirect URLs from configuration.
    $redirect_config = $this->configFactory->get('pb_custom_form.language_redirects');
    $redirect_urls_raw = $redirect_config->get('redirect_urls');
    $redirect_urls = $this->parseRedirectUrls($redirect_urls_raw);

    if (is_numeric($node)) {
      $node = $this->entityTypeManager->getStorage('node')->load($node);
    }
    if ($node instanceof NodeInterface) {
      if ($current_lang == 'en') {
        $path = $base_url . '/';
        $event->setResponse(new RedirectResponse($path));
      }
      else {
        if (array_key_exists($current_lang, $redirect_urls)) {
          $path = $redirect_urls[$current_lang];
          $event->setResponse(new TrustedRedirectResponse($path));
        }
        else {
          $path = $base_url . '/';
          $event->setResponse(new RedirectResponse($path));
        }
      }
      $this->pageCacheKillSwitch->trigger();
    }
  }

  /**
   * Check if current route is a node route.
   *
   * @return bool
   *   TRUE if node entity route, FALSE otherwise.
   */
  protected function isNodeRoute() {
    $route_name = $this->routeMatch->getRouteName();
    $node = $this->routeMatch->getParameter('node');

    // Check if we have a node parameter (which indicates a node route)
    // or if the route name contains node canonical.
    return ($node !== NULL) ||
           ($route_name && (
             strpos($route_name, 'entity.node.canonical') === 0 ||
             strpos($route_name, 'node.') === 0
           ));
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

}
