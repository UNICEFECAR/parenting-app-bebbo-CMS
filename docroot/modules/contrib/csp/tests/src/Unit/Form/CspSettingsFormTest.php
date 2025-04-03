<?php

namespace Drupal\Tests\csp\Unit\Form;

use Drupal\csp\Form\CspSettingsForm;
use Drupal\Tests\UnitTestCase;

/**
 * Test CSP Settings Form.
 *
 * @coversDefaultClass \Drupal\csp\Form\CspSettingsForm
 * @group csp
 */
class CspSettingsFormTest extends UnitTestCase {

  /**
   * Data provider of URLs for host source validity.
   *
   * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
   *
   * @return array[]
   *   An array of [URL, isValid] tuples.
   */
  public static function urlDataProvider() {
    return [
      'tld' => ['com', FALSE],
      'wildcard_tld' => ['*.com', FALSE],
      'bare' => ['example.com', TRUE],
      'bare_port' => ['example.com:1234', TRUE],
      'bare_path' => ['example.com/baz', TRUE],
      'empty_path' => ['example.com/', TRUE],
      'bare_path_query' => ['example.com/baz?foo=false', FALSE],
      'bare_wild_subdomain' => ['*.example.com', TRUE],
      'inner_wild_subdomain' => ['foo.*.example.com', FALSE],
      'wild_tld' => ['example.*', FALSE],

      'subdomain' => ['foo.example.com', TRUE],
      'subdomains' => ['foo.bar.example.com', TRUE],
      'subdomains_path' => ['foo.bar.example.com/baz', TRUE],

      'http' => ['http://example.com', TRUE],
      'https' => ['https://example.com', TRUE],
      'ws' => ['ws://example.com', TRUE],
      'wss' => ['wss://example.com', TRUE],
      'https_port' => ['https://example.com:1234', TRUE],
      'https_port_path' => ['https://example.com:1234/baz', TRUE],
      'https_wild_subdomain' => ['https://*.example.com', TRUE],

      'ipv4' => ['192.168.0.1', TRUE],
      'https_ipv4' => ['https://192.168.0.1', TRUE],
      'https_ipv4_path' => ['https://192.168.0.1/baz', TRUE],
      'https_ipv4_port' => ['https://192.168.0.1:1234', TRUE],

      'ipv6' => ['[fd42:92f4:7eb8:c821:f685:9190:bf44:b2f5]', TRUE],
      'ipv6_short' => ['[fd42:92f4:7eb8:c821::b2f5]', TRUE],
      'https_ipv6' => ['https://[fd42:92f4:7eb8:c821:f685:9190:bf44:b2f5]', TRUE],
      'https_ipv6_short' => ['https://[fd42:92f4:7eb8:c821::b2f5]', TRUE],
      'https_ipv6_port' => ['https://[fd42:92f4:7eb8:c821:f685:9190:bf44:b2f5]:1234', TRUE],
      'https_ipv6_short_port' => ['https://[fd42:92f4:7eb8:c821::b2f5]:1234', TRUE],
      'https_ipv6_port_path' => ['https://[fd42:92f4:7eb8:c821:f685:9190:bf44:b2f5]:1234/baz', TRUE],

      'localhost' => ['localhost', TRUE],
      'https_localhost' => ['https://localhost', TRUE],
      'https_localhost_path' => ['https://localhost/baz', TRUE],
      'https_localhost_port' => ['https://localhost:1234', TRUE],
      'https_localhost_port_path' => ['https://localhost:1234/baz', TRUE],

      'wild_port' => ['example.com:*', TRUE],
      'wild_subdomain_wild_port' => ['*.example.com:*', TRUE],
      'empty_port' => ['example.com:', FALSE],
      'letter_port' => ['example.com:b33f', FALSE],

      // @see https://www.w3.org/TR/CSP3/#grammardef-scheme-part
      // @see https://tools.ietf.org/html/rfc3986#section-3.1
      'other_protocol' => ['example://localhost', TRUE],
      'edge_case_protocol' => ['example-foo.123+bar://localhost', TRUE],
      'protocol_numeric_first_char' => ['1example://localhost', FALSE],
      // cspell:disable-next-line
      'protocol_invalid_symbol' => ['ex@mple://localhost', FALSE],
    ];
  }

  /**
   * Valid host source values.
   *
   * @param string $url
   *   A URL.
   * @param bool $valid
   *   TRUE if the url should be valid.
   *
   * @dataProvider urlDataProvider
   */
  public function testIsValidHost(string $url, bool $valid = TRUE) {
    $this->assertEquals($valid, HostValidator::isValidHost($url));
  }

}

/**
 * Expose protected CspSettingsForm::isValidHost() for testing.
 *
 * phpcs:disable
 */
class HostValidator extends CspSettingsForm {
  public static function isValidHost($url): bool {
    return parent::isValidHost($url);
  }
}
