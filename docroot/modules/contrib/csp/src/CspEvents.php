<?php

namespace Drupal\csp;

/**
 * Contains all events dispatched by CSP module.
 *
 * @package Drupal\csp
 */
final class CspEvents {

  /**
   * Name of event fired to alter CSP policies for the current request.
   *
   * The event listener receives a \Drupal\csp\Event\PolicyAlterEvent instance.
   *
   * @Event
   *
   * @var string
   */
  const POLICY_ALTER = 'csp.policy_alter';

}
