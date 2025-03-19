<?php

namespace Drupal\Tests\csp\Unit;

use Drupal\csp\Csp;
use Drupal\csp\PolicyHelper;

/**
 * Test PolicyHelper service.
 *
 * @coversDefaultClass \Drupal\csp\PolicyHelper
 * @group csp
 */
class PolicyHelperHashTest extends PolicyHelperTestBase {
  private const TEST_HASH_VALUE = "'sha256-m0zKW3SgFyV1D9aL5SVP9sTjV8ymQ9XirnpSfOsqCFk='";
  private const TEST_FALLBACK_VALUE = 'fallback.example.com';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->nonce->expects($this->never())
      ->method('getSource');
  }

  /**
   * Data Provider for hash tests.
   *
   * @return array<string, array{array<string, string[]>, array<string, string[]>, array<string, string[]|bool>}>
   *   An array of test values.
   */
  public static function hashPolicyProvider(): array {
    return [
      'empty' => [
        [],
        [
          'script' => 'elem',
          'style' => 'elem',
        ],
        [
          'default-src' => FALSE,
          'script-src' => FALSE,
          'script-src-elem' => FALSE,
          'style-src' => FALSE,
          'style-src-elem' => FALSE,
          'style-src-attr' => FALSE,
        ],
      ],

      // Append Element.
      'default fallback, append elem' => [
        [
          'default-src' => [Csp::POLICY_SELF],
        ],
        ['script' => 'elem'],
        [
          'default-src' => [Csp::POLICY_SELF],
          'script-src' => [Csp::POLICY_SELF, self::TEST_HASH_VALUE],
          'script-src-elem' => [Csp::POLICY_SELF, self::TEST_HASH_VALUE],
          'script-src-attr' => [Csp::POLICY_SELF],
        ],
      ],
      'script fallback, append elem' => [
        [
          'script-src' => [Csp::POLICY_SELF],
        ],
        ['script' => 'elem'],
        [
          'default-src' => FALSE,
          'script-src' => [Csp::POLICY_SELF, self::TEST_HASH_VALUE],
          'script-src-elem' => [Csp::POLICY_SELF, self::TEST_HASH_VALUE],
          'script-src-attr' => [Csp::POLICY_SELF],
        ],
      ],
      'safe script, safe elem, append elem' => [
        [
          'script-src' => [Csp::POLICY_SELF],
          'script-src-elem' => [Csp::POLICY_SELF],
        ],
        ['script' => 'elem'],
        [
          'default-src' => FALSE,
          'script-src' => [Csp::POLICY_SELF, self::TEST_HASH_VALUE],
          'script-src-elem' => [Csp::POLICY_SELF, self::TEST_HASH_VALUE],
          'script-src-attr' => [Csp::POLICY_SELF],
        ],
      ],
      'unsafe script, safe elem, append elem' => [
        [
          'script-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          'script-src-elem' => [Csp::POLICY_SELF],
        ],
        ['script' => 'elem'],
        [
          'default-src' => FALSE,
          'script-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE, self::TEST_FALLBACK_VALUE],
          'script-src-elem' => [Csp::POLICY_SELF, self::TEST_HASH_VALUE],
          'script-src-attr' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
        ],
      ],
      'unsafe script, unsafe elem, append elem' => [
        [
          'script-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          'script-src-elem' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
        ],
        ['script' => 'elem'],
        [
          'default-src' => FALSE,
          'script-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE, self::TEST_FALLBACK_VALUE],
          'script-src-elem' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE, self::TEST_FALLBACK_VALUE],
          'script-src-attr' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
        ],
      ],

      // Append Attr.
      'default fallback, append attr' => [
        [
          'default-src' => [Csp::POLICY_SELF],
        ],
        ['script' => 'attr'],
        [
          'default-src' => [Csp::POLICY_SELF],
          'script-src' => [Csp::POLICY_SELF, self::TEST_HASH_VALUE, Csp::POLICY_UNSAFE_HASHES],
          'script-src-elem' => [Csp::POLICY_SELF],
          'script-src-attr' => [Csp::POLICY_SELF, self::TEST_HASH_VALUE, Csp::POLICY_UNSAFE_HASHES],
        ],
      ],
      'script fallback, append attr' => [
        [
          'script-src' => [Csp::POLICY_SELF],
        ],
        ['script' => 'attr'],
        [
          'default-src' => FALSE,
          'script-src' => [Csp::POLICY_SELF, self::TEST_HASH_VALUE, Csp::POLICY_UNSAFE_HASHES],
          'script-src-elem' => [Csp::POLICY_SELF],
          'script-src-attr' => [Csp::POLICY_SELF, self::TEST_HASH_VALUE, Csp::POLICY_UNSAFE_HASHES],
        ],
      ],
      'safe script, safe attr, append attr' => [
        [
          'script-src' => [Csp::POLICY_SELF],
          'script-src-attr' => [Csp::POLICY_SELF],
        ],
        ['script' => 'attr'],
        [
          'default-src' => FALSE,
          'script-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_HASHES, self::TEST_HASH_VALUE],
          'script-src-elem' => [Csp::POLICY_SELF],
          'script-src-attr' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_HASHES, self::TEST_HASH_VALUE],
        ],
      ],
      'unsafe script, safe attr, append attr' => [
        [
          'script-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          'script-src-attr' => [Csp::POLICY_SELF],
        ],
        ['script' => 'attr'],
        [
          'default-src' => FALSE,
          'script-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE, self::TEST_FALLBACK_VALUE],
          'script-src-elem' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          'script-src-attr' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_HASHES, self::TEST_HASH_VALUE],
        ],
      ],
      'unsafe script, unsafe attr, append attr' => [
        [
          'script-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          'script-src-attr' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
        ],
        ['script' => 'attr'],
        [
          'default-src' => FALSE,
          'script-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE, self::TEST_FALLBACK_VALUE],
          'script-src-elem' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          'script-src-attr' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE, self::TEST_FALLBACK_VALUE],
        ],
      ],
      'unsafe script, existing hash' => [
        [
          'script-src' => [
            Csp::POLICY_SELF,
            Csp::POLICY_UNSAFE_INLINE,
            "'sha256-frbhOkbdoP2Zl7nVhBbp3HLelnsaDP4ll9sX8vt6XEE'",
          ],
        ],
        ['script' => 'elem'],
        [
          'default-src' => FALSE,
          'script-src' => [
            Csp::POLICY_SELF,
            Csp::POLICY_UNSAFE_INLINE,
            "'sha256-frbhOkbdoP2Zl7nVhBbp3HLelnsaDP4ll9sX8vt6XEE'",
            self::TEST_HASH_VALUE,
          ],
          'script-src-elem' => [
            Csp::POLICY_SELF,
            Csp::POLICY_UNSAFE_INLINE,
            "'sha256-frbhOkbdoP2Zl7nVhBbp3HLelnsaDP4ll9sX8vt6XEE'",
            self::TEST_HASH_VALUE,
          ],
          // Should match original script-src value.
          'script-src-attr' => [
            Csp::POLICY_SELF,
            Csp::POLICY_UNSAFE_INLINE,
            "'sha256-frbhOkbdoP2Zl7nVhBbp3HLelnsaDP4ll9sX8vt6XEE'",
          ],
        ],
      ],
      'unsafe script, strict dynamic' => [
        [
          'script-src' => [
            Csp::POLICY_SELF,
            Csp::POLICY_UNSAFE_INLINE,
            Csp::POLICY_STRICT_DYNAMIC,
          ],
        ],
        ['script' => 'elem'],
        [
          'default-src' => FALSE,
          'script-src' => [
            Csp::POLICY_SELF,
            Csp::POLICY_UNSAFE_INLINE,
            Csp::POLICY_STRICT_DYNAMIC,
            self::TEST_HASH_VALUE,
          ],
          'script-src-elem' => [
            Csp::POLICY_SELF,
            Csp::POLICY_UNSAFE_INLINE,
            Csp::POLICY_STRICT_DYNAMIC,
            self::TEST_HASH_VALUE,
          ],
          // Should match original script-src value.
          'script-src-attr' => [
            Csp::POLICY_SELF,
            Csp::POLICY_UNSAFE_INLINE,
            Csp::POLICY_STRICT_DYNAMIC,
          ],
        ],
      ],

      // This initial state shouldn't happen, and is probably bad, but this is
      // the best we can treat it.
      'safe script, unsafe elem, append elem' => [
        [
          'script-src' => [Csp::POLICY_SELF],
          'script-src-elem' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
        ],
        ['script' => 'elem'],
        [
          'default-src' => FALSE,
          'script-src' => [Csp::POLICY_SELF, self::TEST_HASH_VALUE],
          'script-src-elem' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE, self::TEST_FALLBACK_VALUE],
          'script-src-attr' => [Csp::POLICY_SELF],
        ],
      ],
      'unsafe default, append elem' => [
        [
          'default-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
        ],
        ['script' => 'elem'],
        [
          'default-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          'script-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE, self::TEST_FALLBACK_VALUE],
          'script-src-elem' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE, self::TEST_FALLBACK_VALUE],
          'script-src-attr' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
        ],
      ],
      'unsafe default, safe script, append elem' => [
        [
          'default-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          'script-src' => [Csp::POLICY_SELF],
        ],
        ['script' => 'elem'],
        [
          'default-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          'script-src' => [Csp::POLICY_SELF, self::TEST_HASH_VALUE],
          'script-src-elem' => [Csp::POLICY_SELF, self::TEST_HASH_VALUE],
          'script-src-attr' => [Csp::POLICY_SELF],
        ],
      ],
    ];
  }

  /**
   * Test appending a hash.
   *
   * @param array<string, string[]> $policyDirectives
   *   An array of directive values to initiate the policy.
   * @param array<string, string> $appendTo
   *   An array with the directives to append to.
   *   Keys are either 'script' or 'style', and values are either 'elem' or
   *   'attr'.
   * @param array<string, string[]|bool> $expectedDirectives
   *   An array of expected directive values after the hash has attempted to
   *   be applied.
   *
   * @dataProvider hashPolicyProvider
   */
  public function testAppendHash(array $policyDirectives, array $appendTo, array $expectedDirectives) {
    $helper = new PolicyHelper($this->nonce);

    $policy = new Csp();
    foreach ($policyDirectives as $directive => $value) {
      $policy->setDirective($directive, $value);
    }

    foreach ($appendTo as $appendToName => $appendToType) {
      $helper->appendHash($policy, $appendToName, $appendToType, self::TEST_FALLBACK_VALUE, self::TEST_HASH_VALUE);
    }

    $this->assertPolicyEquals($expectedDirectives, $policy);
  }

  /**
   * Test multiple hash values on a directive.
   */
  public function testMultipleHashes(): void {
    $helper = new PolicyHelper($this->nonce);

    $policy = new Csp();
    $policy->setDirective('script-src', Csp::POLICY_SELF);

    // cspell:disable
    $helper->appendHash($policy, 'script', 'elem', self::TEST_FALLBACK_VALUE, "'sha256-abcd'");
    $helper->appendHash($policy, 'script', 'elem', self::TEST_FALLBACK_VALUE, "'sha256-efgh'");
    $helper->appendHash($policy, 'script', 'attr', self::TEST_FALLBACK_VALUE, "'sha256-ijkl'");
    $helper->appendHash($policy, 'script', 'attr', self::TEST_FALLBACK_VALUE, "'sha256-mnop'");

    $this->assertPolicyEquals(
      [
        'default-src' => FALSE,
        'script-src' => [
          Csp::POLICY_SELF,
          Csp::POLICY_UNSAFE_HASHES,
          "'sha256-abcd'",
          "'sha256-efgh'",
          "'sha256-ijkl'",
          "'sha256-mnop'",
        ],
        'script-src-elem' => [
          Csp::POLICY_SELF,
          "'sha256-abcd'",
          "'sha256-efgh'",
        ],
        'script-src-attr' => [
          Csp::POLICY_SELF,
          Csp::POLICY_UNSAFE_HASHES,
          "'sha256-ijkl'",
          "'sha256-mnop'",
        ],
      ],
      $policy
    );
    // cspell:enable
  }

  /**
   * Test validating value of directive parameter.
   *
   * @param string $directive
   *   The directive to append to.
   * @param bool $valid
   *   If the directive should be valid.
   *
   * @dataProvider directiveValidationProvider
   */
  public function testDirectiveValidation(string $directive, bool $valid): void {
    $policyHelper = new PolicyHelper($this->nonce);
    $policy = new Csp();

    if (!$valid) {
      $this->expectException(\InvalidArgumentException::class);
    }

    $policyHelper->appendHash($policy, $directive, 'elem', self::TEST_FALLBACK_VALUE, self::TEST_HASH_VALUE);
  }

  /**
   * Test validating value of type parameter.
   *
   * @param string $type
   *   The type to append to.
   * @param bool $valid
   *   If the directive should be valid.
   *
   * @dataProvider typeValidationProvider
   */
  public function testTypeValidation(string $type, bool $valid): void {
    $policyHelper = new PolicyHelper($this->nonce);
    $policy = new Csp();

    if (!$valid) {
      $this->expectException(\InvalidArgumentException::class);
    }

    $policyHelper->appendHash($policy, 'script', $type, self::TEST_FALLBACK_VALUE, self::TEST_HASH_VALUE);
  }

  /**
   * Data provider for testing hash validation.
   */
  public static function hashValidationProvider(): array {
    return [
      'empty string' => ['', FALSE],
      'valid' => [self::TEST_HASH_VALUE, TRUE],
      'unquoted' => [
        trim(self::TEST_HASH_VALUE, "'"),
        FALSE,
      ],
      'unprefixed' => [
        str_replace("sha256-", "", self::TEST_HASH_VALUE),
        FALSE,
      ],
      'missing value' => ["'sha256-'", FALSE],
      'valid base64' => [
        "'sha256-abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890-_/+=='",
        TRUE,
      ],
      'invalid base64' => ["'sha256-ab#cde'", FALSE],
      'sha384' => ["'sha384-abcde'", TRUE],
      'sha512' => ["'sha512-abcde'", TRUE],
      'invalid algo' => ["'crc32-abcde'", FALSE],
    ];
  }

  /**
   * Test validating value of hash parameter.
   *
   * @param string $value
   *   The hash value.
   * @param bool $valid
   *   If the value should be valid.
   *
   * @dataProvider hashValidationProvider
   */
  public function testHashValidation(string $value, bool $valid): void {
    $policyHelper = new PolicyHelper($this->nonce);
    $policy = new Csp();

    if (!$valid) {
      $this->expectException(\InvalidArgumentException::class);
    }

    $policyHelper->appendHash($policy, 'script', 'elem', self::TEST_FALLBACK_VALUE, $value);
  }

}
