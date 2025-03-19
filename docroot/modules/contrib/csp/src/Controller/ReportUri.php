<?php

namespace Drupal\csp\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Report URI Controller.
 *
 * @package Drupal\csp\Controller
 */
class ReportUri implements ContainerInjectionInterface {

  /**
   * The Request Stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private $requestStack;

  /**
   * The Logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  /**
   * The Config Factory Service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * Create a new Report URI Controller.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The Request Stack service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The Logger channel.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The Config Factory Service.
   */
  public function __construct(RequestStack $requestStack, LoggerInterface $logger, ConfigFactoryInterface $configFactory) {
    $this->requestStack = $requestStack;
    $this->logger = $logger;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('logger.channel.csp'),
      $container->get('config.factory')
    );
  }

  /**
   * Handle a report submission.
   *
   * @param string $type
   *   The report type.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   An empty response.
   */
  public function log($type) {
    if (!in_array($type, $this->getValidTypes())) {
      return new Response('', 404);
    }

    $reportJson = $this->requestStack->getCurrentRequest()->getContent();
    $report = json_decode($reportJson);

    // Return 400: Bad Request if content cannot be parsed.
    if (empty($report) || json_last_error() != JSON_ERROR_NONE) {
      return new Response('', 400);
    }

    $this->logger
      ->info("@type <br/>\n<pre>@data</pre>", [
        '@type' => $type,
        '@data' => json_encode($report, JSON_PRETTY_PRINT),
      ]);

    // 202: Accepted.
    return new Response('', 202);
  }

  /**
   * Retrieve the valid reporting types.
   *
   * @return array
   *   The valid reporting types, based on the currently active configuration.
   */
  protected function getValidTypes() {
    $config = $this->configFactory->get('csp.settings');

    $validTypes = [];
    if ($config->get('enforce.reporting.plugin') === 'sitelog') {
      $validTypes[] = 'enforce';
    }

    if ($config->get('report-only.reporting.plugin') === 'sitelog') {
      $validTypes[] = 'reportOnly';
    }

    return $validTypes;
  }

}
