<?php

namespace Drupal\pb_custom_form\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManager;
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
  ) {
    $this->routeMatch = $route_match;
    $this->languageManager = $language_manager;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->pathCurrent = $path_current;
    $this->pathAliasManager = $path_alias_manager;
    $this->pageCacheKillSwitch = $page_cache_kill_switch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_route_match'),
      $container->get('language_manager'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('path.current'),
      $container->get('path_alias.manager'),
      $container->get('page_cache_kill_switch')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['nodeViewRedirect'];
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
    $landingPages = ['/homepage',
      '/about-us',
      '/terms-and-conditions',
      '/privacy-policy',
      '/foleja',
      '/foleja-about-us',
      '/foleja-privacy-policy',
    ];
    if (!$this->isNodeRoute()) {
      return;
    }

    if (in_array($internal, $landingPages)) {
      return;
    }
    if (!$this->currentUser->isAnonymous()) {
      return;
    }

    $redirect_urls = [
      'ru'     => 'https://www.unicef.org/eca/ru/bebbo-%D0%BF%D1%80%D0%B8%D0%BB%D0%BE%D0%B6%D0%B5%D0%BD%D0%B8%D1%8F-%D0%B4%D0%BB%D1%8F-%D1%80%D0%BE%D0%B4%D0%B8%D1%82%D0%B5%D0%BB%D0%B5%D0%B9',
      'sq'     => 'https://www.unicef.org/albania/sq/bebbo-app-partneri-juaj-n%C3%AB-prind%C3%ABrim',
      'al-sq'  => 'https://www.unicef.org/albania/sq/bebbo-app-partneri-juaj-n%C3%AB-prind%C3%ABrim',
      'by-be'  => 'https://www.unicef.by/bebbo-belarus/',
      'by-ru'  => 'https://www.unicef.by/bebbo-belarus/',
      'bg-bg'  => 'https://www.unicef.org/bulgaria/%D0%BD%D0%BE%D0%B2%D0%BE%D1%82%D0%BE-%D0%BF%D1%80%D0%B8%D0%BB%D0%BE%D0%B6%D0%B5%D0%BD%D0%B8%D0%B5-%D0%B1%D0%B5%D0%B1%D0%B1%D0%BE',
      'gr-el'  => 'https://www.unicef.org/greece/%CE%B3%CE%BD%CF%89%CF%81%CE%AF%CF%83%CF%84%CE%B5-%CF%84%CE%BF-%CE%B2ebbo',
      'xk-sq'  => 'https://www.bebbo.app/xk-sq/foleja',
      'xk-rs'  => 'https://www.bebbo.app/xk-rs/foleja',
      'kg-ky'  => 'https://www.unicef.org/kyrgyzstan/ky/%D0%B6%D0%B0%D2%A3%D1%8B-%D0%B1%D0%B5%D0%B1%D0%B1%D0%BE-%D1%82%D0%B8%D1%80%D0%BA%D0%B5%D0%BC%D0%B5%D1%81%D0%B8',
      'kg-ru'  => 'https://www.unicef.org/kyrgyzstan/ru/%D0%BD%D0%BE%D0%B2%D0%BE%D0%B5-%D0%BF%D1%80%D0%B8%D0%BB%D0%BE%D0%B6%D0%B5%D0%BD%D0%B8%D0%B5-%D0%B1%D0%B5%D0%B1%D0%B1%D0%BE',
      'me-cnr' => 'https://www.unicef.org/montenegro/price/bebbo-aplikacija-pouzdane-informacije-za-roditelje',
      'mk-mk'  => 'https://www.unicef.org/northmacedonia/mk/soop%C5%A1teni%D1%98a/unicef-%D1%98a-pretstavi-aplikaci%D1%98ata-bebbo-za-poddr%C5%A1ka-na-roditelite-i-staratelite-na-deca',
      'mk-sq'  => 'https://www.unicef.org/northmacedonia/sq/deklarata-shtypi/unicef-e-shpalosi-aplikacionin-p%C3%ABr-prind%C3%ABrim-bebbo-p%C3%ABr-mb%C3%ABshtetje-t%C3%AB-prind%C3%ABrve-dhe',
      'md-ro'  => 'https://www.bebbo.app/ro-ro/homepage',
      'ro'     => 'https://www.bebbo.app/ro-ro/homepage',
      'ro-ro'  => 'https://www.bebbo.app/ro-ro/homepage',
      'sr'     => 'https://www.unicef.org/serbia/bebbo-vas-saputnik-u-roditeljstvu',
      'rs-en'  => 'https://www.unicef.org/serbia/en/bebbo-app-your-partner-in-parenthood',
      'rs-sr'  => 'https://www.unicef.org/serbia/bebbo-vas-saputnik-u-roditeljstvu',
      'tj-ru'  => 'https://bebbo.tj/tj/%d0%b0%d1%81%d0%be%d1%81%d3%a3/',
      'tj-tg'  => 'https://bebbo.tj/',
      'uk'     => 'https://www.unicef.org/ukraine/press-releases/unicef-launches-bebbo-mobile-app-help-parents-care-children-during-war',
      'uz-ru'  => 'https://www.unicef.org/uzbekistan/new-parenting-app-launched',
      'uz-uz'  => 'https://www.unicef.org/uzbekistan/uz/new-parenting-app-launched',
    ];

    if (is_numeric($node)) {
      $node = $this->entityTypeManager->getStorage('node')->load($node);
    }
    if ($node instanceof NodeInterface) {
      $current_lang = $this->languageManager->getCurrentLanguage()->getId();
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
    return strpos($this->routeMatch->getRouteName(), 'entity.node.canonical') === 0;
  }

}
