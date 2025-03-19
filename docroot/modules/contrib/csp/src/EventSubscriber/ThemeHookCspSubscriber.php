<?php

namespace Drupal\csp\EventSubscriber;

use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\csp\CspEvents;
use Drupal\csp\Event\PolicyAlterEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Invoke a hook allowing themes to alter the CSP policy.
 */
class ThemeHookCspSubscriber implements EventSubscriberInterface {

  /**
   * The theme manager service.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[CspEvents::POLICY_ALTER] = ['onCspPolicyAlter', -10];
    return $events;
  }

  /**
   * ThemeHookCspSubscriber constructor.
   *
   * @param \Drupal\Core\Theme\ThemeManagerInterface $themeManager
   *   The theme manager service.
   */
  public function __construct(ThemeManagerInterface $themeManager) {
    $this->themeManager = $themeManager;
  }

  /**
   * Invoke a hook allowing themes to alter the CSP policy.
   *
   * @param \Drupal\csp\Event\PolicyAlterEvent $alterEvent
   *   The policy alter event.
   */
  public function onCspPolicyAlter(PolicyAlterEvent $alterEvent) {
    $policy = $alterEvent->getPolicy();
    $response = $alterEvent->getResponse();

    $this->themeManager->alter('csp_policy', $policy, $response);
  }

}
