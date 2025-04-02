<?php

namespace Drupal\csp\Plugin\CspReportingHandler;

use Drupal\csp\Plugin\ReportingHandlerBase;

/**
 * Null Csp Reporting Handler.
 *
 * @CspReportingHandler(
 *   id = "none",
 *   label = @Translation("None"),
 *   description = @Translation("Reporting is disabled"),
 * )
 */
class None extends ReportingHandlerBase {

}
