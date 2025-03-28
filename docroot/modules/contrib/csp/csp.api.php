<?php

/**
 * @file
 * Documentation for CSP module APIs.
 */

/**
 * @addtogroup hooks
 * @{
 */

use Drupal\csp\Csp;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alters the CSP policy.
 *
 * This hook is only invoked for themes, modules should add an event subscriber
 * listening to the CspEvents::POLICY_ALTER event.
 *
 * @param \Drupal\csp\Csp $policy
 *   The CSP policy.
 * @param \Symfony\Component\HttpFoundation\Response $response
 *   The response the policy is applied to.
 */
function hook_csp_policy_alter(Csp $policy, Response $response): void {
  $policy->appendDirective('img-src', 'https://example.com');
}

/**
 * @} End of "addtogroup hooks".
 */
