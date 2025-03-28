<?php

namespace Drupal\csp\EventSubscriber;

use Drupal\Core\Asset\LibraryDependencyResolverInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Render\AttachmentsInterface;
use Drupal\csp\Csp;
use Drupal\csp\CspEvents;
use Drupal\csp\Event\PolicyAlterEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Alter CSP policy for core modules and themes.
 */
class CoreCspSubscriber implements EventSubscriberInterface {

  /**
   * The Library Dependency Resolver service.
   *
   * @var \Drupal\Core\Asset\LibraryDependencyResolverInterface
   */
  private $libraryDependencyResolver;

  /**
   * The Module Handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[CspEvents::POLICY_ALTER] = ['onCspPolicyAlter'];
    return $events;
  }

  /**
   * CoreCspSubscriber constructor.
   *
   * @param \Drupal\Core\Asset\LibraryDependencyResolverInterface $libraryDependencyResolver
   *   The Library Dependency Resolver Service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The Module Handler service.
   */
  public function __construct(LibraryDependencyResolverInterface $libraryDependencyResolver, ModuleHandlerInterface $moduleHandler) {
    $this->libraryDependencyResolver = $libraryDependencyResolver;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * Alter CSP policy for libraries included in Drupal core.
   *
   * @param \Drupal\csp\Event\PolicyAlterEvent $alterEvent
   *   The Policy Alter event.
   */
  public function onCspPolicyAlter(PolicyAlterEvent $alterEvent) {
    $policy = $alterEvent->getPolicy();
    $response = $alterEvent->getResponse();

    if ($response instanceof AttachmentsInterface) {
      $libraries = $this->libraryDependencyResolver
        ->getLibrariesWithDependencies(
          $response->getAttachments()['library'] ?? []
        );

      // Libraries that load an editor after an AJAX request need their
      // exceptions applied to the calling page.
      $ajaxEditorLoader = (
        in_array('layout_builder/drupal.layout_builder', $libraries)
      );

      // CKEditor5 requires inline styles for interface.
      // @see https://ckeditor.com/docs/ckeditor5/latest/installation/advanced/csp.html
      if (
        in_array('core/ckeditor5', $libraries)
        || ($ajaxEditorLoader && $this->moduleHandler->moduleExists('ckeditor5'))
      ) {
        $policy->fallbackAwareAppendIfEnabled('style-src', [Csp::POLICY_UNSAFE_INLINE]);
        $policy->fallbackAwareAppendIfEnabled('style-src-attr', [Csp::POLICY_UNSAFE_INLINE]);
        $policy->fallbackAwareAppendIfEnabled('style-src-elem', [Csp::POLICY_UNSAFE_INLINE]);
      }

      $umamiFontLibraries = [
        'umami/webfonts-open-sans',
        'umami/webfonts-scope-one',
      ];
      if (!empty(array_intersect($libraries, $umamiFontLibraries))) {
        $policy->fallbackAwareAppendIfEnabled('font-src', ['https://fonts.gstatic.com']);
      }
    }
  }

}
