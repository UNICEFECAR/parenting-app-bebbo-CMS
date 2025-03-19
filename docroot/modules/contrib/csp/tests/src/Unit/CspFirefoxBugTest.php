<?php

namespace Drupal\Tests\csp\Unit;

use Drupal\csp\Csp;
use Drupal\Tests\UnitTestCase;

/**
 * Test Csp handling of Firefox bug #1313937.
 *
 * @see https://bugzilla.mozilla.org/show_bug.cgi?id=1313937
 *
 * @coversDefaultClass \Drupal\csp\Csp
 * @group csp
 */
class CspFirefoxBugTest extends UnitTestCase {

  /**
   * Test that no modifications are made if default-src isn't set.
   *
   * @covers ::ff1313937
   */
  public function testEmptyDefault() {
    $policy = new Csp();

    $policy->setDirective(
      'script-src',
      [Csp::POLICY_STRICT_DYNAMIC, "'nonce-abc'"]
    );
    $policy->setDirective('style-src', [Csp::POLICY_SELF, "'sha256-abcde'"]);

    $this->assertEquals(
      "script-src 'strict-dynamic' 'nonce-abc'; style-src 'self' 'sha256-abcde'",
      $policy->getHeaderValue()
    );
  }

  /**
   * Test that 'strict-dynamic' directive is copied from default-src.
   *
   * @covers ::ff1313937
   */
  public function testStrictDynamic() {
    $policy = new Csp();

    $policy->setDirective(
      'default-src',
      [Csp::POLICY_STRICT_DYNAMIC, "'nonce-abc'"]
    );

    $this->assertEquals(
      "default-src 'strict-dynamic' 'nonce-abc'; script-src 'strict-dynamic' 'nonce-abc'; style-src 'nonce-abc'",
      $policy->getHeaderValue()
    );
  }

  /**
   * Test that nonce directives are copied from default-src.
   *
   * @covers ::ff1313937
   */
  public function testNonce() {
    $policy = new Csp();

    $policy->setDirective('default-src', [Csp::POLICY_SELF, "'nonce-abc'"]);

    $this->assertEquals(
      "default-src 'self' 'nonce-abc'; script-src 'self' 'nonce-abc'; style-src 'self' 'nonce-abc'",
      $policy->getHeaderValue()
    );
  }

  /**
   * Test that hash directives are copied from default-src.
   *
   * @covers ::ff1313937
   */
  public function testHash() {
    $policy = new Csp();

    $policy->setDirective('default-src', [Csp::POLICY_SELF, "'sha256-abcde'"]);

    $this->assertEquals(
      "default-src 'self' 'sha256-abcde'; script-src 'self' 'sha256-abcde'; style-src 'self' 'sha256-abcde'",
      $policy->getHeaderValue()
    );
  }

  /**
   * Test that directives are not copied if more specific directive set.
   *
   * @covers ::ff1313937
   */
  public function testSetScriptSrc() {
    $policy = new Csp();

    $policy->setDirective('default-src', [Csp::POLICY_SELF, "'sha256-abcde'"]);
    $policy->setDirective(
      'script-src',
      [Csp::POLICY_STRICT_DYNAMIC, "'nonce-abc'"]
    );

    $this->assertEquals(
      "default-src 'self' 'sha256-abcde'; script-src 'strict-dynamic' 'nonce-abc'; style-src 'self' 'sha256-abcde'",
      $policy->getHeaderValue()
    );
  }

  /**
   * Test that directives are not copied if more specific directive set.
   *
   * @covers ::ff1313937
   */
  public function testSetStyleSrc() {
    $policy = new Csp();

    $policy->setDirective(
      'default-src',
      [Csp::POLICY_SELF, Csp::POLICY_STRICT_DYNAMIC, "'sha256-abcde'"]
    );
    $policy->setDirective('style-src', [Csp::POLICY_SELF]);

    $this->assertEquals(
      "default-src 'self' 'strict-dynamic' 'sha256-abcde'; script-src 'self' 'strict-dynamic' 'sha256-abcde'; style-src 'self'",
      $policy->getHeaderValue()
    );
  }

}
