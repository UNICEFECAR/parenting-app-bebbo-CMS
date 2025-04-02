<?php

namespace Drupal\Tests\csp\Unit;

use Drupal\csp\Csp;
use Drupal\Tests\UnitTestCase;

/**
 * Test optimization of CSP directives.
 *
 * @coversDefaultClass \Drupal\csp\Csp
 * @group csp
 */
class CspOptimizationTest extends UnitTestCase {

  /**
   * Test that source values are not repeated in the header.
   *
   * @covers ::getHeaderValue
   */
  public function testDuplicate() {
    $policy = new Csp();

    // Provide identical sources in an array.
    $policy->setDirective('default-src', [Csp::POLICY_SELF, Csp::POLICY_SELF]);
    // Provide identical sources in a string.
    $policy->setDirective('script-src', 'one.example.com one.example.com');

    // Provide identical sources through both set and append.
    $policy->setDirective('style-src', ['two.example.com', 'two.example.com']);
    $policy->appendDirective('style-src', ['two.example.com', 'two.example.com']);

    $this->assertEquals(
      "default-src 'self'; script-src one.example.com; style-src two.example.com",
      $policy->getHeaderValue()
    );
  }

  /**
   * Test optimizing policy based on directives which fallback to default-src.
   *
   * @covers ::getHeaderValue
   * @covers ::getDirectiveFallbackList
   * @covers ::reduceSourceList
   */
  public function testDefaultSrcFallback() {
    $policy = new Csp();
    $policy->setDirective('default-src', Csp::POLICY_SELF);

    // Directives which fallback to default-src.
    $policy->setDirective('script-src', Csp::POLICY_SELF);
    $policy->setDirective('style-src', Csp::POLICY_SELF);
    $policy->setDirective('worker-src', Csp::POLICY_SELF);
    $policy->setDirective('child-src', Csp::POLICY_SELF);
    $policy->setDirective('connect-src', Csp::POLICY_SELF);
    $policy->setDirective('manifest-src', Csp::POLICY_SELF);
    $policy->setDirective('prefetch-src', Csp::POLICY_SELF);
    $policy->setDirective('object-src', Csp::POLICY_SELF);
    $policy->setDirective('frame-src', Csp::POLICY_SELF);
    $policy->setDirective('media-src', Csp::POLICY_SELF);
    $policy->setDirective('font-src', Csp::POLICY_SELF);
    $policy->setDirective('img-src', Csp::POLICY_SELF);

    // Directives which do not fallback to default-src.
    $policy->setDirective('base-uri', Csp::POLICY_SELF);
    $policy->setDirective('form-action', Csp::POLICY_SELF);
    $policy->setDirective('frame-ancestors', Csp::POLICY_SELF);
    $policy->setDirective('navigate-to', Csp::POLICY_SELF);

    $this->assertEquals(
      "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; navigate-to 'self'",
      $policy->getHeaderValue()
    );
  }

  /**
   * Test optimizing policy based on the worker-src fallback list.
   *
   * @covers ::getHeaderValue
   * @covers ::getDirectiveFallbackList
   * @covers ::reduceSourceList
   */
  public function testWorkerSrcFallback() {
    $policy = new Csp();

    // Fallback should progresses as more policies in the list are added.
    $policy->setDirective('worker-src', Csp::POLICY_SELF);
    $this->assertEquals(
      "worker-src 'self'",
      $policy->getHeaderValue()
    );

    $policy->setDirective('child-src', Csp::POLICY_SELF);
    $this->assertEquals(
      "child-src 'self'",
      $policy->getHeaderValue()
    );

    $policy->setDirective('script-src', Csp::POLICY_SELF);
    $this->assertEquals(
      "script-src 'self'",
      $policy->getHeaderValue()
    );

    $policy->setDirective('default-src', Csp::POLICY_SELF);
    $this->assertEquals(
      "default-src 'self'",
      $policy->getHeaderValue()
    );

    // A missing directive from the list should not prevent fallback.
    $policy->removeDirective('child-src');
    $this->assertEquals(
      "default-src 'self'",
      $policy->getHeaderValue()
    );

    // Fallback should only progress to the nearest matching directive.
    // Since child-src differs from worker-src, both should be included.
    // script-src does not appear since it matches default-src.
    $policy->setDirective('child-src', [Csp::POLICY_SELF, 'example.com']);
    $this->assertEquals(
      "default-src 'self'; child-src 'self' example.com; worker-src 'self'",
      $policy->getHeaderValue()
    );

    // Fallback should only progress to the nearest matching directive.
    // worker-src now matches child-src, so it should be removed.
    $policy->setDirective('worker-src', [Csp::POLICY_SELF, 'example.com']);
    $this->assertEquals(
      "default-src 'self'; child-src 'self' example.com",
      $policy->getHeaderValue()
    );
  }

  /**
   * Test optimizing policy based on the script-src fallback list.
   *
   * @covers ::getHeaderValue
   * @covers ::getDirectiveFallbackList
   * @covers ::reduceSourceList
   */
  public function testScriptSrcFallback() {
    $policy = new Csp();

    $policy->setDirective('default-src', Csp::POLICY_SELF);
    $policy->setDirective(
      'script-src',
      [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE]
    );
    // script-src-elem should not fall back to default-src.
    $policy->setDirective('script-src-elem', Csp::POLICY_SELF);
    $policy->setDirective('script-src-attr', Csp::POLICY_UNSAFE_INLINE);
    $this->assertEquals(
      "default-src 'self'; script-src 'self' 'unsafe-inline'; script-src-attr 'unsafe-inline'; script-src-elem 'self'",
      $policy->getHeaderValue()
    );

    $policy->setDirective(
      'script-src-attr',
      [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE]
    );
    $this->assertEquals(
      "default-src 'self'; script-src 'self' 'unsafe-inline'; script-src-elem 'self'",
      $policy->getHeaderValue()
    );
  }

  /**
   * Test optimizing policy based on the style-src fallback list.
   *
   * @covers ::getHeaderValue
   * @covers ::getDirectiveFallbackList
   * @covers ::reduceSourceList
   */
  public function testStyleSrcFallback() {
    $policy = new Csp();

    $policy->setDirective('default-src', Csp::POLICY_SELF);
    $policy->setDirective(
      'style-src',
      [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE]
    );
    // style-src-elem should not fall back to default-src.
    $policy->setDirective('style-src-elem', Csp::POLICY_SELF);
    $policy->setDirective('style-src-attr', Csp::POLICY_UNSAFE_INLINE);
    $this->assertEquals(
      "default-src 'self'; style-src 'self' 'unsafe-inline'; style-src-attr 'unsafe-inline'; style-src-elem 'self'",
      $policy->getHeaderValue()
    );

    $policy->setDirective(
      'style-src-attr',
      [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE]
    );
    $this->assertEquals(
      "default-src 'self'; style-src 'self' 'unsafe-inline'; style-src-elem 'self'",
      $policy->getHeaderValue()
    );
  }

  /**
   * A source list with only 'none' should not produce a deprecation warning.
   *
   * @covers ::reduceSourceList
   */
  public function testSourceListWithNone() {
    $policy = new Csp();

    $policy->setDirective('object-src', [
      Csp::POLICY_NONE,
    ]);

    $this->assertEquals(
      "object-src 'none'",
      $policy->getHeaderValue()
    );
  }

  /**
   * A source list with 'report-sample' and 'none' should not produce a warning.
   *
   * @covers ::reduceSourceList
   */
  public function testSourceListWithNoneAndReportSample() {
    $policy = new Csp();

    $policy->setDirective('script-src', [
      Csp::POLICY_NONE,
      Csp::POLICY_REPORT_SAMPLE,
    ]);

    $this->assertEquals(
      "script-src 'none' 'report-sample'",
      $policy->getHeaderValue()
    );
  }

  /**
   * Legacy test for reducing the source list when 'none' is included.
   *
   * @group legacy
   * @covers ::reduceSourceList
   */
  public function testReduceSourceListWithNone() {
    $policy = new Csp();

    $policy->setDirective('object-src', [
      Csp::POLICY_NONE,
      'example.com',
      "'hash-123abc'",
    ]);

    $this->expectDeprecation("Unsilenced deprecation: 'none' overriding other sources is deprecated in csp:8.x-1.30 and behavior will change in csp:2.0.0. See https://www.drupal.org/node/3411477");

    $this->assertEquals(
      "object-src 'none'",
      $policy->getHeaderValue()
    );
  }

  /**
   * Legacy test that 'report-sample' is kept when 'none' is included.
   *
   * @group legacy
   * @covers ::reduceSourceList
   */
  public function testReduceSourceListWithNoneAndReportSample() {
    $policy = new Csp();

    $policy->setDirective('script-src', [
      Csp::POLICY_NONE,
      'example.com',
      "'hash-123abc'",
      Csp::POLICY_REPORT_SAMPLE,
    ]);

    $this->expectDeprecation("Unsilenced deprecation: 'none' overriding other sources is deprecated in csp:8.x-1.30 and behavior will change in csp:2.0.0. See https://www.drupal.org/node/3411477");

    $this->assertEquals(
      "script-src 'none' 'report-sample'",
      $policy->getHeaderValue()
    );
  }

  /**
   * Test reducing source list when any host allowed.
   *
   * @covers ::reduceSourceList
   */
  public function testReduceSourceListAny() {
    $policy = new Csp();

    $policy->setDirective('script-src', [
      Csp::POLICY_ANY,
      // Hosts and network protocols should be removed.
      'example.com',
      'https://example.com',
      'http:',
      'https:',
      'ftp:',
      'ws:',
      'wss:',
      // Non-network protocols should be kept.
      'data:',
      // Additional keywords should be kept.
      Csp::POLICY_UNSAFE_INLINE,
      "'hash-123abc'",
      "'nonce-abc123'",
    ]);
    $this->assertEquals(
      "script-src * data: 'unsafe-inline' 'hash-123abc' 'nonce-abc123'",
      $policy->getHeaderValue()
    );
  }

  /**
   * Test reducing the source list when 'http:' is included.
   *
   * @covers ::reduceSourceList
   */
  public function testReduceSourceListWithHttp() {
    $policy = new Csp();

    $policy->setDirective('script-src', [
      'http:',
      // Hosts without protocol should be kept.
      // (e.g. this would allow ftp://example.com)
      'example.com',
      // HTTP hosts should be removed.
      'http://example.org',
      'https://example.net',
      // Hosts with port should not be removed.
      'http://example.com:80',
      // Other network protocols should be kept.
      'ftp:',
      // Non-network protocols should be kept.
      'data:',
      // Additional keywords should be kept.
      Csp::POLICY_UNSAFE_INLINE,
      "'hash-123abc'",
      "'nonce-abc123'",
    ]);

    $this->assertEquals(
      "script-src http: example.com http://example.com:80 ftp: data: 'unsafe-inline' 'hash-123abc' 'nonce-abc123'",
      $policy->getHeaderValue()
    );
  }

  /**
   * Test reducing the source list when 'https:' is included.
   *
   * @covers ::reduceSourceList
   */
  public function testReduceSourceListWithHttps() {
    $policy = new Csp();

    $policy->setDirective('script-src', [
      'https:',
      // Non-secure hosts should be kept.
      'example.com',
      'http://example.org',
      // Secure Hosts should be removed.
      'https://example.net',
      // Hosts with port should not be removed.
      'https://example.com:443',
      // Other network protocols should be kept.
      'ftp:',
      // Non-network protocols should be kept.
      'data:',
      // Additional keywords should be kept.
      Csp::POLICY_UNSAFE_INLINE,
      "'hash-123abc'",
      "'nonce-abc123'",
    ]);

    $this->assertEquals(
      "script-src https: example.com http://example.org https://example.com:443 ftp: data: 'unsafe-inline' 'hash-123abc' 'nonce-abc123'",
      $policy->getHeaderValue()
    );
  }

  /**
   * Test reducing the source list when 'ws:' is included.
   *
   * @covers ::reduceSourceList
   */
  public function testReduceSourceListWithWs() {
    $policy = new Csp();

    $policy->setDirective('script-src', [
      'https:',
      'ws:',
      // Hosts without protocol should be kept.
      // (e.g. this would allow ftp://example.com)
      'example.com',
      // HTTP hosts should be removed.
      'ws://connect.example.org',
      'wss://connect.example.net',
      // Other network protocols should be kept.
      'ftp:',
      // Non-network protocols should be kept.
      'data:',
      // Additional keywords should be kept.
      Csp::POLICY_UNSAFE_INLINE,
      "'hash-123abc'",
      "'nonce-abc123'",
    ]);

    $this->assertEquals(
      "script-src https: ws: example.com ftp: data: 'unsafe-inline' 'hash-123abc' 'nonce-abc123'",
      $policy->getHeaderValue()
    );
  }

  /**
   * Test reducing the source list when 'wss:' is included.
   *
   * @covers ::reduceSourceList
   */
  public function testReduceSourceListWithWss() {
    $policy = new Csp();

    $policy->setDirective('script-src', [
      'https:',
      'wss:',
      // Non-secure hosts should be kept.
      'example.com',
      'ws://connect.example.org',
      // Secure Hosts should be removed.
      'wss://connect.example.net',
      // Other network protocols should be kept.
      'ftp:',
      // Non-network protocols should be kept.
      'data:',
      // Additional keywords should be kept.
      Csp::POLICY_UNSAFE_INLINE,
      "'hash-123abc'",
      "'nonce-abc123'",
    ]);

    $this->assertEquals(
      "script-src https: wss: example.com ws://connect.example.org ftp: data: 'unsafe-inline' 'hash-123abc' 'nonce-abc123'",
      $policy->getHeaderValue()
    );
  }

  /**
   * Network sources should be removed for attribute directives.
   *
   * @covers ::reduceAttrSourceList
   */
  public function testReduceAttrSourceListNetworkSource() {
    $policy = new Csp();

    $policy->setDirective('script-src-attr', [
      Csp::POLICY_UNSAFE_INLINE,
      'https:',
      'wss:',
      'example.com',
      'https://example.com',
      'ws://connect.example.org',
      'ftp:',
      'data:',
    ]);

    $this->assertEquals(
      "script-src-attr 'unsafe-inline'",
      $policy->getHeaderValue()
    );
  }

  /**
   * Wildcard source should be removed for attribute directives.
   *
   * @covers ::reduceAttrSourceList
   */
  public function testReduceAttrSourceListWildcard() {
    $policy = new Csp();

    $policy->setDirective('script-src-attr', [
      Csp::POLICY_UNSAFE_INLINE,
      Csp::POLICY_ANY,
    ]);

    $this->assertEquals(
      "script-src-attr 'unsafe-inline'",
      $policy->getHeaderValue()
    );
  }

  /**
   * Without 'unsafe-hashes', attr directives should not have hash sources.
   *
   * @covers ::reduceAttrSourceList
   */
  public function testReduceAttrSourceListNoUnsafeHash() {
    $policy = new Csp();

    $policy->setDirective('script-src-attr', [
      Csp::POLICY_UNSAFE_INLINE,
      "'sha256-BnZSlC9IkS7BVcseRf0CAOmLntfifZIosT2C1OMQ088='",
    ]);

    $this->assertEquals(
      "script-src-attr 'unsafe-inline'",
      $policy->getHeaderValue()
    );
  }

  /**
   * Hash sources should be allowed with 'unsafe-hashes'.
   *
   * @covers ::reduceAttrSourceList
   */
  public function testReduceAttrSourceListUnsafeHash() {
    $policy = new Csp();

    $policy->setDirective('script-src-attr', [
      Csp::POLICY_UNSAFE_INLINE,
      Csp::POLICY_UNSAFE_HASHES,
      "'sha256-BnZSlC9IkS7BVcseRf0CAOmLntfifZIosT2C1OMQ088='",
    ]);

    $this->assertEquals(
      "script-src-attr 'unsafe-inline' 'unsafe-hashes' 'sha256-BnZSlC9IkS7BVcseRf0CAOmLntfifZIosT2C1OMQ088='",
      $policy->getHeaderValue()
    );
  }

  /**
   * Nonces cannot be applied to attributes.
   *
   * @covers ::reduceAttrSourceList
   */
  public function testReduceAttrSourceListNonce() {
    $policy = new Csp();

    $policy->setDirective('script-src-attr', [
      Csp::POLICY_SELF,
      // cspell:disable-next-line
      "'nonce-qskCbxYHEcwf3tBVzkngCA'",
    ]);

    $this->assertEquals(
      "script-src-attr 'self'",
      $policy->getHeaderValue()
    );
  }

  /**
   * If attr directive is enabled but empty, it should be removed.
   *
   * @covers ::reduceAttrSourceList
   */
  public function testReduceAttrSourceListOriginallyEmpty() {
    $policy = new Csp();

    $policy->setDirective('script-src', [
      Csp::POLICY_SELF,
      'https://example.com',
    ]);
    $policy->setDirective('script-src-attr', []);

    $this->assertEquals(
      "script-src 'self' https://example.com",
      $policy->getHeaderValue()
    );
  }

  /**
   * If all values are removed from an attr source list it should be 'none'.
   *
   * @covers ::reduceAttrSourceList
   */
  public function testReduceAttrSourceListEmpty() {
    $policy = new Csp();

    $policy->setDirective('script-src', [
      Csp::POLICY_SELF,
      'https://example.com',
    ]);
    $policy->setDirective('script-src-attr', [
      'https://example.com',
    ]);

    $this->assertEquals(
      "script-src 'self' https://example.com; script-src-attr 'none'",
      $policy->getHeaderValue()
    );
  }

  /**
   * Attribute directive shouldn't be included if it matches fallback.
   *
   * @covers ::reduceAttrSourceList
   */
  public function testReduceAttrSourceListFallback() {
    $policy = new Csp();

    $directiveValue = [
      Csp::POLICY_SELF,
      'https://example.com',
      Csp::POLICY_UNSAFE_HASHES,
      "'sha256-BnZSlC9IkS7BVcseRf0CAOmLntfifZIosT2C1OMQ088='",
    ];

    $policy->setDirective('script-src', $directiveValue);
    $policy->setDirective('script-src-attr', $directiveValue);

    $this->assertEquals(
      "script-src 'self' https://example.com 'unsafe-hashes' 'sha256-BnZSlC9IkS7BVcseRf0CAOmLntfifZIosT2C1OMQ088='",
      $policy->getHeaderValue()
    );
  }

}
