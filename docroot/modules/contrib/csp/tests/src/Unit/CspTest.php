<?php

namespace Drupal\Tests\csp\Unit;

use Drupal\csp\Csp;
use Drupal\Tests\UnitTestCase;

/**
 * Test manipulating directives in a policy.
 *
 * @coversDefaultClass \Drupal\csp\Csp
 * @group csp
 */
class CspTest extends UnitTestCase {

  /**
   * Test calculating hash values.
   *
   * @covers ::calculateHash
   */
  public function testHash() {
    $this->assertEquals(
      'sha256-BnZSlC9IkS7BVcseRf0CAOmLntfifZIosT2C1OMQ088=',
      Csp::calculateHash('alert("Hello World");')
    );

    $this->assertEquals(
      'sha256-BnZSlC9IkS7BVcseRf0CAOmLntfifZIosT2C1OMQ088=',
      Csp::calculateHash('alert("Hello World");', 'sha256')
    );

    $this->assertEquals(
      'sha384-iZxROpttQr5JcGhwPlHbUPBm+IHbO2CwTxLGhVoZXCIIpjSZo+Ourcmqw1QHOpGM',
      Csp::calculateHash('alert("Hello World");', 'sha384')
    );

    $this->assertEquals(
      'sha512-6/WbXCJEH9R1/effxooQuXLAsm6xIsfGMK6nFa7TG76VuHZJVRZHIirKrXi/Pib8QbQmkzpo5K/3Ye+cD46ADQ==',
      Csp::calculateHash('alert("Hello World");', 'sha512')
    );
  }

  /**
   * Test specifying an invalid hash algorithm.
   *
   * @covers ::calculateHash
   */
  public function testInvalidHashAlgo() {
    $this->expectException(\InvalidArgumentException::class);

    Csp::calculateHash('alert("Hello World");', 'md5');
  }

  /**
   * Test that changing the policy's report-only flag updates the header name.
   *
   * @covers ::reportOnly
   * @covers ::isReportOnly
   * @covers ::getHeaderName
   */
  public function testReportOnly() {
    $policy = new Csp();

    $this->assertFalse($policy->isReportOnly());
    $this->assertEquals(
      "Content-Security-Policy",
      $policy->getHeaderName()
    );

    $policy->reportOnly();
    $this->assertTrue($policy->isReportOnly());
    $this->assertEquals(
      "Content-Security-Policy-Report-Only",
      $policy->getHeaderName()
    );

    $policy->reportOnly(FALSE);
    $this->assertFalse($policy->isReportOnly());
    $this->assertEquals(
      "Content-Security-Policy",
      $policy->getHeaderName()
    );
  }

  /**
   * Test that invalid directive names cause an exception.
   *
   * @covers ::setDirective
   * @covers ::isValidDirectiveName
   * @covers ::validateDirectiveName
   */
  public function testSetInvalidPolicy() {
    $this->expectException(\InvalidArgumentException::class);

    $policy = new Csp();

    $policy->setDirective('foo', Csp::POLICY_SELF);
  }

  /**
   * Test that invalid directive names cause an exception.
   *
   * @covers ::appendDirective
   * @covers ::isValidDirectiveName
   * @covers ::validateDirectiveName
   */
  public function testAppendInvalidPolicy() {
    $this->expectException(\InvalidArgumentException::class);

    $policy = new Csp();

    $policy->appendDirective('foo', Csp::POLICY_SELF);
  }

  /**
   * Test setting a single value to a directive.
   *
   * @covers ::setDirective
   * @covers ::hasDirective
   * @covers ::getDirective
   * @covers ::isValidDirectiveName
   * @covers ::validateDirectiveName
   * @covers ::getHeaderValue
   */
  public function testSetSingle() {
    $policy = new Csp();

    $policy->setDirective('default-src', Csp::POLICY_SELF);

    $this->assertTrue($policy->hasDirective('default-src'));
    $this->assertEquals(
      $policy->getDirective('default-src'),
      ["'self'"]
    );
    $this->assertEquals(
      "default-src 'self'",
      $policy->getHeaderValue()
    );
  }

  /**
   * Test appending a single value to an uninitialized directive.
   *
   * @covers ::appendDirective
   * @covers ::hasDirective
   * @covers ::getDirective
   * @covers ::isValidDirectiveName
   * @covers ::validateDirectiveName
   * @covers ::getHeaderValue
   */
  public function testAppendSingle() {
    $policy = new Csp();

    $policy->appendDirective('default-src', Csp::POLICY_SELF);

    $this->assertTrue($policy->hasDirective('default-src'));
    $this->assertEquals(
      $policy->getDirective('default-src'),
      ["'self'"]
    );
    $this->assertEquals(
      "default-src 'self'",
      $policy->getHeaderValue()
    );
  }

  /**
   * Test that a directive is overridden when set with a new value.
   *
   * @covers ::setDirective
   * @covers ::isValidDirectiveName
   * @covers ::getHeaderValue
   */
  public function testSetMultiple() {
    $policy = new Csp();

    $policy->setDirective('default-src', Csp::POLICY_ANY);
    $policy->setDirective('default-src', [Csp::POLICY_SELF, 'one.example.com']);
    $policy->setDirective('script-src', Csp::POLICY_SELF . ' two.example.com');
    $policy->setDirective('upgrade-insecure-requests', TRUE);
    $policy->setDirective('report-uri', 'example.com/report-uri');

    $this->assertEquals(
      "default-src 'self' one.example.com; script-src 'self' two.example.com; report-uri example.com/report-uri; upgrade-insecure-requests",
      $policy->getHeaderValue()
    );
  }

  /**
   * Test that appending to a directive extends the existing value.
   *
   * @covers ::appendDirective
   * @covers ::isValidDirectiveName
   * @covers ::getHeaderValue
   */
  public function testAppendMultiple() {
    $policy = new Csp();

    $policy->appendDirective('default-src', Csp::POLICY_SELF);
    $policy->appendDirective('script-src', [Csp::POLICY_SELF, 'two.example.com']);
    $policy->appendDirective('default-src', 'one.example.com');

    $this->assertEquals(
      "default-src 'self' one.example.com; script-src 'self' two.example.com",
      $policy->getHeaderValue()
    );
  }

  /**
   * A string of multiple values with extra whitespace.
   *
   * @covers ::appendDirective
   * @covers ::isValidDirectiveName
   * @covers ::getHeaderValue
   */
  public function testAppendStringWithExtraWhitespace() {
    $policy = new Csp();

    $policy->appendDirective('script-src', ' one.example.com  two.example.com ');
    $policy->appendDirective('script-src-attr', " 'unsafe-inline'  one.example.com  two.example.com ");

    $this->assertEquals(
      "script-src one.example.com two.example.com; script-src-attr 'unsafe-inline'",
      $policy->getHeaderValue()
    );
  }

  /**
   * Test that setting an empty value removes a directive.
   *
   * @covers ::setDirective
   * @covers ::isValidDirectiveName
   * @covers ::getHeaderValue
   */
  public function testSetEmpty() {
    $policy = new Csp();
    $policy->setDirective('default-src', Csp::POLICY_SELF);
    $policy->setDirective('script-src', [Csp::POLICY_SELF]);
    $policy->setDirective('script-src', []);

    $this->assertEquals(
      "default-src 'self'",
      $policy->getHeaderValue()
    );

    $policy = new Csp();
    $policy->setDirective('default-src', Csp::POLICY_SELF);
    $policy->setDirective('script-src', [Csp::POLICY_SELF]);
    $policy->setDirective('script-src', '');

    $this->assertEquals(
      "default-src 'self'",
      $policy->getHeaderValue()
    );
  }

  /**
   * Test that appending an empty value doesn't change the directive.
   *
   * @covers ::appendDirective
   * @covers ::isValidDirectiveName
   * @covers ::getHeaderValue
   */
  public function testAppendEmpty() {
    $policy = new Csp();

    $policy->appendDirective('default-src', Csp::POLICY_SELF);
    $this->assertEquals(
      "default-src 'self'",
      $policy->getHeaderValue()
    );

    $policy->appendDirective('default-src', '');
    $policy->appendDirective('script-src', []);
    $this->assertEquals(
      "default-src 'self'",
      $policy->getHeaderValue()
    );
  }

  /**
   * Appending to a directive if it or a fallback is enabled.
   *
   * @covers ::fallbackAwareAppendIfEnabled
   */
  public function testFallbackAwareAppendIfEnabled() {
    // If no relevant directives are enabled, they should not change.
    $policy = new Csp();
    $policy->setDirective('style-src', Csp::POLICY_SELF);
    $policy->fallbackAwareAppendIfEnabled('script-src-attr', Csp::POLICY_UNSAFE_INLINE);
    $this->assertFalse($policy->hasDirective('default-src'));
    $this->assertFalse($policy->hasDirective('script-src'));
    $this->assertFalse($policy->hasDirective('script-src-attr'));

    // Script-src-attr should copy value from default-src.  Script-src should
    // not be changed.
    $policy = new Csp();
    $policy->setDirective('default-src', Csp::POLICY_SELF);
    $policy->fallbackAwareAppendIfEnabled('script-src-attr', Csp::POLICY_UNSAFE_INLINE);
    $this->assertEquals(
      [Csp::POLICY_SELF],
      $policy->getDirective('default-src')
    );
    $this->assertFalse($policy->hasDirective('script-src'));
    $this->assertEquals(
      [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
      $policy->getDirective('script-src-attr')
    );

    // Script-src-attr should copy value from script-src.
    $policy = new Csp();
    $policy->setDirective('script-src', Csp::POLICY_SELF);
    $policy->fallbackAwareAppendIfEnabled('script-src-attr', Csp::POLICY_UNSAFE_INLINE);
    $this->assertFalse($policy->hasDirective('default-src'));
    $this->assertEquals(
      [Csp::POLICY_SELF],
      $policy->getDirective('script-src')
    );
    $this->assertEquals(
      [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
      $policy->getDirective('script-src-attr')
    );

    // Script-src-attr should only append to existing value if enabled.
    $policy = new Csp();
    $policy->setDirective('script-src', Csp::POLICY_SELF);
    $policy->setDirective('script-src-attr', []);
    $policy->fallbackAwareAppendIfEnabled('script-src-attr', Csp::POLICY_UNSAFE_INLINE);
    $this->assertFalse($policy->hasDirective('default-src'));
    $this->assertEquals(
      [Csp::POLICY_SELF],
      $policy->getDirective('script-src')
    );
    $this->assertEquals(
      [Csp::POLICY_UNSAFE_INLINE],
      $policy->getDirective('script-src-attr')
    );
  }

  /**
   * Appending to a directive if its fallback includes 'none'.
   *
   * @covers ::fallbackAwareAppendIfEnabled
   */
  public function testFallbackAwareAppendIfEnabledNone() {
    // New directive should be enabled with only provided value.
    $policy = new Csp();
    $policy->setDirective('default-src', Csp::POLICY_NONE);
    $policy->fallbackAwareAppendIfEnabled(
      'script-src-attr',
      Csp::POLICY_UNSAFE_INLINE
    );
    $this->assertEquals(
      [Csp::POLICY_NONE],
      $policy->getDirective('default-src')
    );
    $this->assertFalse($policy->hasDirective('script-src'));
    $this->assertEquals(
      [Csp::POLICY_UNSAFE_INLINE],
      $policy->getDirective('script-src-attr')
    );

    // Additional values in fallback should be ignored if 'none' is present.
    $policy = new Csp();
    $policy->setDirective(
      'script-src',
      [Csp::POLICY_NONE, 'https://example.org']
    );
    $policy->fallbackAwareAppendIfEnabled(
      'script-src-attr',
      Csp::POLICY_UNSAFE_INLINE
    );
    $this->assertEquals(
      [Csp::POLICY_UNSAFE_INLINE],
      $policy->getDirective('script-src-attr')
    );
  }

  /**
   * Test that a boolean directive is set and output correctly.
   *
   * @covers ::setDirective
   * @covers ::getHeaderValue
   */
  public function testBooleanDirectiveTrue() {
    $policy = new Csp();

    $policy->setDirective('default-src', Csp::POLICY_SELF);
    $policy->setDirective('upgrade-insecure-requests', TRUE);

    $this->assertEquals("default-src 'self'; upgrade-insecure-requests", $policy->getHeaderValue());
  }

  /**
   * Test that a boolean directive is set and output correctly.
   *
   * @covers ::setDirective
   * @covers ::getHeaderValue
   */
  public function testBooleanDirectiveFalse() {
    $policy = new Csp();

    $policy->setDirective('default-src', Csp::POLICY_SELF);
    $policy->setDirective('upgrade-insecure-requests', FALSE);

    $this->assertEquals("default-src 'self'", $policy->getHeaderValue());
  }

  /**
   * Test that a string directive is set and output correctly.
   *
   * @covers ::setDirective
   * @covers ::getHeaderValue
   */
  public function testStringDirective() {
    $policy = new Csp();

    $policy->setDirective('default-src', Csp::POLICY_SELF);
    $policy->setDirective('webrtc', "'allow'");

    $this->assertEquals("default-src 'self'; webrtc 'allow'", $policy->getHeaderValue());
  }

  /**
   * Test that removed directives are not output in the header.
   *
   * @covers ::removeDirective
   * @covers ::isValidDirectiveName
   * @covers ::getHeaderValue
   */
  public function testRemove() {
    $policy = new Csp();

    $policy->setDirective('default-src', [Csp::POLICY_SELF]);
    $policy->setDirective('script-src', 'example.com');

    $policy->removeDirective('script-src');

    $this->assertEquals(
      "default-src 'self'",
      $policy->getHeaderValue()
    );
  }

  /**
   * Test that removing an invalid directive name causes an exception.
   *
   * @covers ::removeDirective
   * @covers ::isValidDirectiveName
   * @covers ::validateDirectiveName
   */
  public function testRemoveInvalid() {
    $this->expectException(\InvalidArgumentException::class);

    $policy = new Csp();

    $policy->removeDirective('foo');
  }

  /**
   * Test that invalid directive values cause an exception.
   *
   * @covers ::appendDirective
   */
  public function testInvalidValue() {
    $this->expectException(\InvalidArgumentException::class);

    $policy = new Csp();

    $policy->appendDirective('default-src', 12);
  }

  /**
   * @covers ::__toString
   */
  public function testToString() {
    $policy = new Csp();

    $policy->setDirective('default-src', Csp::POLICY_SELF);
    $policy->setDirective('script-src', [Csp::POLICY_SELF, 'example.com']);

    $this->assertEquals(
      "Content-Security-Policy: default-src 'self'; script-src 'self' example.com",
      $policy->__toString()
    );
  }

}
