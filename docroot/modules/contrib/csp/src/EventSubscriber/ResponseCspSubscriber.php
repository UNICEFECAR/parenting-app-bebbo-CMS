<?php

namespace Drupal\csp\EventSubscriber;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Render\AttachmentsInterface;
use Drupal\Core\Utility\Error;
use Drupal\csp\Csp;
use Drupal\csp\CspEvents;
use Drupal\csp\Event\PolicyAlterEvent;
use Drupal\csp\LibraryPolicyBuilder;
use Drupal\csp\Nonce;
use Drupal\csp\ReportingHandlerPluginManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

// @phpcs:disable Drupal.Commenting.FunctionComment.ExtraParamComment
// @phpcs:disable Drupal.Commenting.FunctionComment.ParamNameNoMatch

/**
 * Class ResponseSubscriber.
 */
class ResponseCspSubscriber implements EventSubscriberInterface {

  /**
   * The Library Policy Builder service.
   *
   * @var \Drupal\csp\LibraryPolicyBuilder
   */
  protected LibraryPolicyBuilder $libraryPolicyBuilder;

  /**
   * The Reporting Handler Plugin Manager service.
   *
   * @var \Drupal\csp\ReportingHandlerPluginManager
   */
  private ReportingHandlerPluginManager $reportingHandlerPluginManager;

  /**
   * The Event Dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private EventDispatcherInterface $eventDispatcher;

  /**
   * The Nonce service.
   *
   * @var \Drupal\csp\Nonce
   */
  private Nonce $nonce;

  /**
   * Constructs a new ResponseSubscriber object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config Factory service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The Event Dispatcher Service.
   * @param \Drupal\csp\Nonce|null $nonce
   *   The nonce service.
   * @param \Drupal\csp\LibraryPolicyBuilder $libraryPolicyBuilder
   *   The Library Parser service.
   * @param \Drupal\csp\ReportingHandlerPluginManager $reportingHandlerPluginManager
   *   The Reporting Handler Plugin Manager service.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    $eventDispatcher,
    $nonce,
    $libraryPolicyBuilder = NULL,
    $reportingHandlerPluginManager = NULL,
  ) {
    $argOrder = FALSE;
    foreach (func_get_args() as $index => $arg) {
      if ($arg instanceof EventDispatcherInterface) {
        $this->eventDispatcher = $arg;
        $argOrder |= ($index !== 1);
      }
      elseif ($arg instanceof Nonce) {
        $this->nonce = $arg;
        $argOrder |= ($index !== 2);
      }
      elseif ($arg instanceof LibraryPolicyBuilder) {
        $this->libraryPolicyBuilder = $arg;
      }
      elseif ($arg instanceof ReportingHandlerPluginManager) {
        $this->reportingHandlerPluginManager = $arg;
      }
    }
    if ($argOrder) {
      // @phpcs:ignore Drupal.Semantics.FunctionTriggerError.TriggerErrorTextLayoutRelaxed
      @trigger_error("The parameter order for ResponseCspSubscriber has changed for compatibility with 2.0.0. See https://www.drupal.org/docs/contributed-modules/content-security-policy/upgrading-from-1x-to-2x#s-for-developers", E_USER_DEPRECATED);
    }

    if (empty($this->eventDispatcher)) {
      throw new \InvalidArgumentException("EventDispatcher service is required.");
    }
    if (empty($this->nonce)) {
      @trigger_error("Omitting the Nonce service is deprecated in csp:8.x-1.22 and will be required in csp:2.0.0. See https://www.drupal.org/project/csp/issues/3018679", E_USER_DEPRECATED);
      $this->nonce = \Drupal::service('csp.nonce');
    }
    if (empty($this->libraryPolicyBuilder)) {
      $this->libraryPolicyBuilder = \Drupal::service('csp.library_policy_builder');
    }
    if (empty($this->reportingHandlerPluginManager)) {
      $this->reportingHandlerPluginManager = \Drupal::service('plugin.manager.csp_reporting_handler');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::RESPONSE] = [
      // Nonce value needs to be added before settings are rendered to the page
      // by \Drupal\Core\EventSubscriber\HtmlResponseSubscriber.
      ['applyDrupalSettingsNonce', 1],
      // Policy needs to be generated after placeholder library info is bubbled
      // up and rendered to the page.
      ['onKernelResponse'],
    ];
    return $events;
  }

  /**
   * Add a nonce value to drupalSettings.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The Response Event.
   */
  public function applyDrupalSettingsNonce(ResponseEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $response = $event->getResponse();
    if (!($response instanceof AttachmentsInterface)) {
      return;
    }

    $response->addAttachments([
      'drupalSettings' => [
        'csp' => [
          'nonce' => $this->nonce->getValue(),
        ],
      ],
    ]);
  }

  /**
   * Add Content-Security-Policy header to response.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The Response event.
   */
  public function onKernelResponse(ResponseEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $cspConfig = $this->configFactory->get('csp.settings');
    $libraryDirectives = $this->libraryPolicyBuilder->getSources();

    $response = $event->getResponse();

    if ($response instanceof CacheableResponseInterface) {
      $response->getCacheableMetadata()
        ->addCacheTags(['config:csp.settings']);
    }

    foreach (['report-only', 'enforce'] as $policyType) {

      if (!$cspConfig->get($policyType . '.enable')) {
        continue;
      }

      $policy = new Csp();
      $policy->reportOnly($policyType == 'report-only');

      foreach (($cspConfig->get($policyType . '.directives') ?: []) as $directiveName => $directiveOptions) {

        if (Csp::DIRECTIVES[$directiveName] == Csp::DIRECTIVE_SCHEMA_BOOLEAN) {
          $policy->setDirective($directiveName, (bool) $directiveOptions);
          continue;
        }

        if (Csp::DIRECTIVES[$directiveName] === Csp::DIRECTIVE_SCHEMA_ALLOW_BLOCK) {
          if (!empty($directiveOptions)) {
            $policy->setDirective($directiveName, "'" . $directiveOptions . "'");
          }
          continue;
        }

        // This is a directive with a simple array of values.
        if (!isset($directiveOptions['base'])) {
          $policy->setDirective($directiveName, $directiveOptions);
          continue;
        }

        switch ($directiveOptions['base']) {
          case 'self':
            $policy->setDirective($directiveName, [Csp::POLICY_SELF]);
            break;

          case 'none':
            $policy->setDirective($directiveName, [Csp::POLICY_NONE]);
            break;

          case 'any':
            $policy->setDirective($directiveName, [Csp::POLICY_ANY]);
            break;

          default:
            // Initialize to an empty value so that any alter subscribers can
            // tell that this directive was enabled.
            $policy->setDirective($directiveName, []);
        }

        if (!empty($directiveOptions['flags'])) {
          $policy->appendDirective($directiveName, array_map(function ($value) {
            return "'" . $value . "'";
          }, $directiveOptions['flags']));
        }

        if (!empty($directiveOptions['sources'])) {
          $policy->appendDirective($directiveName, $directiveOptions['sources']);
        }

        if (isset($libraryDirectives[$directiveName])) {
          $policy->appendDirective($directiveName, $libraryDirectives[$directiveName]);
        }
      }

      $reportingPluginId = $cspConfig->get($policyType . '.reporting.plugin');
      if ($reportingPluginId) {
        $reportingOptions = $cspConfig->get($policyType . '.reporting.options') ?: [];
        $reportingOptions += [
          'type' => $policyType,
        ];
        try {
          $this->reportingHandlerPluginManager
            ->createInstance($reportingPluginId, $reportingOptions)
            ->alterPolicy($policy);
        }
        catch (PluginException $e) {
          \Drupal::logger('csp')
            ->error(Error::DEFAULT_ERROR_MESSAGE, Error::decodeException($e));
        }
      }

      $this->eventDispatcher->dispatch(
        new PolicyAlterEvent($policy, $response),
        CspEvents::POLICY_ALTER
      );

      if (($headerValue = $policy->getHeaderValue())) {
        $response->headers->set($policy->getHeaderName(), $headerValue);
      }
    }
  }

}
