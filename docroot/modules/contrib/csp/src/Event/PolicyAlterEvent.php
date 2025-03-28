<?php

namespace Drupal\csp\Event;

use Drupal\csp\Csp;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event for altering a response CSP Policy.
 */
class PolicyAlterEvent extends Event {

  /**
   * A CSP policy.
   *
   * @var \Drupal\csp\Csp
   */
  private $policy;

  /**
   * The Response the policy is being applied to.
   *
   * @var \Symfony\Component\HttpFoundation\Response
   */
  private $response;

  /**
   * Create a new PolicyAlterEvent instance.
   *
   * @param \Drupal\csp\Csp $policy
   *   A CSP policy.
   * @param \Symfony\Component\HttpFoundation\Response $response
   *   The Response the policy is being applied to.
   */
  public function __construct(Csp $policy, Response $response) {
    $this->policy = $policy;
    $this->response = $response;
  }

  /**
   * Retrieve the defined CSP policy.
   *
   * @return \Drupal\csp\Csp
   *   The CSP policy.
   */
  public function getPolicy() {
    return $this->policy;
  }

  /**
   * Retrieve the Response the policy is applied to.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The Response the policy is applied to.
   */
  public function getResponse() {
    return $this->response;
  }

}
