<?php

namespace Drupal\Tests\csp\Unit;

use Drupal\csp\Csp;

/**
 * Assertions for testing policy directives.
 */
trait AssertPolicyTrait {

  /**
   * A helper for asserting a policy's directives against expected values.
   *
   * @param array<string, string[]|bool> $expectedDirectives
   *   An array of expected directive values.
   *   Expected values can be an array or a boolean. TRUE will require that the
   *   policy has that corresponding directive with any value.  FALSE will
   *   require that the policy does not have the corresponding directive.
   * @param \Drupal\csp\Csp $policy
   *   The policy to check.
   * @param bool $strictPresence
   *   Require that unspecified directives are not present on the policy.
   */
  protected function assertPolicyEquals(array $expectedDirectives, Csp $policy, bool $strictPresence = FALSE): void {
    $falseDirectives = array_filter($expectedDirectives, function ($value) {
      return $value === FALSE;
    });
    $expectedDirectives = array_diff_key($expectedDirectives, $falseDirectives);

    if (!$strictPresence) {
      $falseDirectiveNames = array_keys($falseDirectives);
    }
    else {
      $falseDirectiveNames = array_diff(
        Csp::getDirectiveNames(),
        array_keys($expectedDirectives)
      );
    }
    foreach ($falseDirectiveNames as $directiveName) {
      $this->assertFalse(
        $policy->hasDirective($directiveName),
        "Policy should not have directive '{$directiveName}'"
      );
    }

    foreach ($expectedDirectives as $directiveName => $expectedValue) {
      $this->assertTrue(
        $policy->hasDirective($directiveName),
        "Policy does not have expected directive {$directiveName}"
      );
      if ($expectedValue === TRUE) {
        continue;
      }

      $this->assertEqualsCanonicalizing(
        $expectedValue,
        $policy->getDirective($directiveName),
        "Directive {$directiveName} does not match expected value"
      );
    }
  }

}
