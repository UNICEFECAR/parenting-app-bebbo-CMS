<?php

namespace Drupal\csp\Plugin\CspReportingHandler;

use Drupal\Core\Url;
use Drupal\csp\Csp;
use Drupal\csp\Plugin\ReportingHandlerBase;

/**
 * Csp Reporting Handler to use Drupal log.
 *
 * @CspReportingHandler(
 *   id = "sitelog",
 *   label = @Translation("Site Log"),
 *   description = @Translation("Reports will be added to the site log."),
 * )
 */
class SiteLog extends ReportingHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function alterPolicy(Csp $policy) {

    $reportUri = Url::fromRoute(
      'csp.reporturi',
      ['type' => ($this->configuration['type'] == 'enforce') ? 'enforce' : 'reportOnly'],
      ['absolute' => TRUE]
    );
    $policy->setDirective('report-uri', $reportUri->toString());
  }

}
