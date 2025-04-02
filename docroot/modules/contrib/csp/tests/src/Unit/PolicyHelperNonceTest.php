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
class PolicyHelperNonceTest extends PolicyHelperTestBase {
  // cspell:disable-next-line
  private const TEST_NONCE_VALUE = "'nonce-onVJmdhRq20YD0zVBXOoOA'";
  private const TEST_FALLBACK_VALUE = 'fallback.example.com';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->nonce->method('getSource')
      ->willReturn(self::TEST_NONCE_VALUE);
  }

  /**
   * The nonce service should be used if value is not provided.
   */
  public function testNonceService(): void {
    $helper = new PolicyHelper($this->nonce);

    $policy = new Csp();
    $policy->setDirective('default-src', [Csp::POLICY_SELF]);

    $helper->appendNonce($policy, 'script', self::TEST_FALLBACK_VALUE);

    // cspell:disable-next-line
    $customNonce = "'nonce-za7qH1oXLmYCFgNFeus--A'";
    $helper->appendNonce($policy, 'style', self::TEST_FALLBACK_VALUE, $customNonce);

    $this->assertPolicyEquals(
      [
        'default-src' => [Csp::POLICY_SELF],
        'script-src' => [Csp::POLICY_SELF, self::TEST_NONCE_VALUE],
        'script-src-elem' => [Csp::POLICY_SELF, self::TEST_NONCE_VALUE],
        'script-src-attr' => FALSE,
        'style-src' => [Csp::POLICY_SELF, $customNonce],
        'style-src-elem' => [Csp::POLICY_SELF, $customNonce],
        'style-src-attr' => FALSE,
      ],
      $policy
    );
  }

  /**
   * Data Provider for nonce tests.
   *
   * @return array<string, array{array<string, string[]>, array<string, string[]>, array<string, string[]|bool>}>
   *   An array of test values.
   */
  public static function noncePolicyProvider(): array {
    return [
      'empty' => [
        [],
        ['script', 'style'],
        [
          'default-src' => FALSE,
          'script-src' => FALSE,
          'script-src-elem' => FALSE,
          'style-src' => FALSE,
          'style-src-elem' => FALSE,
          'style-src-attr' => FALSE,
        ],
      ],
      'default fallback' => [
        [
          'default-src' => [Csp::POLICY_SELF],
        ],
        ['script'],
        [
          'default-src' => [Csp::POLICY_SELF],
          'script-src' => [Csp::POLICY_SELF, self::TEST_NONCE_VALUE],
          'script-src-elem' => [Csp::POLICY_SELF, self::TEST_NONCE_VALUE],
          'script-src-attr' => FALSE,
        ],
      ],
      'script fallback' => [
        [
          'script-src' => [Csp::POLICY_SELF],
        ],
        ['script'],
        [
          'default-src' => FALSE,
          'script-src' => [Csp::POLICY_SELF, self::TEST_NONCE_VALUE],
          'script-src-elem' => [Csp::POLICY_SELF, self::TEST_NONCE_VALUE],
          'script-src-attr' => FALSE,
        ],
      ],
      'safe script, safe elem' => [
        [
          'script-src' => [Csp::POLICY_SELF],
          'script-src-elem' => [Csp::POLICY_SELF],
        ],
        ['script'],
        [
          'default-src' => FALSE,
          'script-src' => [Csp::POLICY_SELF, self::TEST_NONCE_VALUE],
          'script-src-elem' => [Csp::POLICY_SELF, self::TEST_NONCE_VALUE],
          'script-src-attr' => FALSE,
        ],
      ],
      'unsafe script, safe elem' => [
        [
          'script-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          'script-src-elem' => [Csp::POLICY_SELF],
        ],
        ['script'],
        [
          'default-src' => FALSE,
          'script-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE, self::TEST_FALLBACK_VALUE],
          'script-src-elem' => [Csp::POLICY_SELF, self::TEST_NONCE_VALUE],
          'script-src-attr' => FALSE,
        ],
      ],
      'unsafe script, unsafe elem' => [
        [
          'script-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          'script-src-elem' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
        ],
        ['script'],
        [
          'default-src' => FALSE,
          'script-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE, self::TEST_FALLBACK_VALUE],
          'script-src-elem' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE, self::TEST_FALLBACK_VALUE],
          'script-src-attr' => FALSE,
        ],
      ],
      'unsafe script, existing nonce' => [
        // cspell:disable
        [
          'script-src' => [
            Csp::POLICY_SELF,
            Csp::POLICY_UNSAFE_INLINE,
            "'nonce-BJupcbPc_LCG8--OUgiFmA'",
          ],
        ],
        ['script'],
        [
          'default-src' => FALSE,
          'script-src' => [
            Csp::POLICY_SELF,
            Csp::POLICY_UNSAFE_INLINE,
            "'nonce-BJupcbPc_LCG8--OUgiFmA'",
            self::TEST_NONCE_VALUE,
          ],
          'script-src-elem' => [
            Csp::POLICY_SELF,
            Csp::POLICY_UNSAFE_INLINE,
            "'nonce-BJupcbPc_LCG8--OUgiFmA'",
            self::TEST_NONCE_VALUE,
          ],
          'script-src-attr' => FALSE,
        ],
        // cspell:enable
      ],
      'unsafe script, strict dynamic' => [
        [
          'script-src' => [
            Csp::POLICY_SELF,
            Csp::POLICY_UNSAFE_INLINE,
            Csp::POLICY_STRICT_DYNAMIC,
          ],
        ],
        ['script'],
        [
          'default-src' => FALSE,
          'script-src' => [
            Csp::POLICY_SELF,
            Csp::POLICY_UNSAFE_INLINE,
            Csp::POLICY_STRICT_DYNAMIC,
            self::TEST_NONCE_VALUE,
          ],
          'script-src-elem' => [
            Csp::POLICY_SELF,
            Csp::POLICY_UNSAFE_INLINE,
            Csp::POLICY_STRICT_DYNAMIC,
            self::TEST_NONCE_VALUE,
          ],
          'script-src-attr' => FALSE,
        ],
      ],

      // Start of initial states that *shouldn't* happen, but handle them the
      // best as possible.
      'safe script, unsafe elem' => [
        [
          'script-src' => [Csp::POLICY_SELF],
          'script-src-elem' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
        ],
        ['script'],
        [
          'default-src' => FALSE,
          'script-src' => [Csp::POLICY_SELF, self::TEST_NONCE_VALUE],
          'script-src-elem' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE, self::TEST_FALLBACK_VALUE],
          'script-src-attr' => FALSE,
        ],
      ],
      'unsafe default' => [
        [
          'default-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
        ],
        ['script'],
        [
          'default-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          'script-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE, self::TEST_FALLBACK_VALUE],
          'script-src-elem' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE, self::TEST_FALLBACK_VALUE],
          'script-src-attr' => FALSE,
        ],
      ],
      'unsafe default, safe script' => [
        [
          'default-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          'script-src' => [Csp::POLICY_SELF],
        ],
        ['script'],
        [
          'default-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          'script-src' => [Csp::POLICY_SELF, self::TEST_NONCE_VALUE],
          'script-src-elem' => [Csp::POLICY_SELF, self::TEST_NONCE_VALUE],
          'script-src-attr' => FALSE,
        ],
      ],
      'unsafe default, safe elem' => [
        [
          'default-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          'script-src-elem' => [Csp::POLICY_SELF],
        ],
        ['script'],
        [
          'default-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          'script-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE, self::TEST_FALLBACK_VALUE],
          'script-src-elem' => [Csp::POLICY_SELF, self::TEST_NONCE_VALUE],
          'script-src-attr' => FALSE,
        ],
      ],
    ];
  }

  /**
   * Test appending a nonce.
   *
   * @param array<string, string[]> $policyDirectives
   *   An array of directive values to initiate the policy.
   * @param string[] $appendTo
   *   An array with the string value 'script' and/or 'style' to attempt
   *   appending a nonce to.
   * @param array<string, string[]|bool> $expectedDirectives
   *   An array of expected directive values after the nonce has attempted to
   *   be applied.
   *
   * @dataProvider noncePolicyProvider
   */
  public function testAppendNonce(array $policyDirectives, array $appendTo, array $expectedDirectives): void {
    $helper = new PolicyHelper($this->nonce);

    $policy = new Csp();
    foreach ($policyDirectives as $directive => $value) {
      $policy->setDirective($directive, $value);
    }

    foreach ($appendTo as $appendToName) {
      $helper->appendNonce($policy, $appendToName, self::TEST_FALLBACK_VALUE, NULL);
    }

    $this->assertPolicyEquals($expectedDirectives, $policy);
  }

  /**
   * Test multiple nonces.
   */
  public function testMultipleNonces(): void {
    $helper = new PolicyHelper($this->nonce);

    $policy = new Csp();
    $policy->setDirective('script-src', [Csp::POLICY_SELF]);

    $helper->appendNonce($policy, 'script', self::TEST_FALLBACK_VALUE);
    // cspell:disable-next-line
    $customNonce = "'nonce-za7qH1oXLmYCFgNFeus--A'";
    $helper->appendNonce($policy, 'script', self::TEST_FALLBACK_VALUE, $customNonce);

    $this->assertPolicyEquals(
      [
        'default-src' => FALSE,
        'script-src' => [Csp::POLICY_SELF, self::TEST_NONCE_VALUE, $customNonce],
        'script-src-elem' => [Csp::POLICY_SELF, self::TEST_NONCE_VALUE, $customNonce],
        'script-src-attr' => FALSE,
      ],
      $policy
    );
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
    else {
      $this->expectNotToPerformAssertions();
    }

    $policyHelper->appendNonce($policy, $directive, self::TEST_FALLBACK_VALUE);
  }

  /**
   * Data provider for testing nonce validation.
   */
  public static function nonceValidationProvider(): array {
    return [
      'empty string' => ['', FALSE],
      'valid' => [self::TEST_NONCE_VALUE, TRUE],
      'unquoted' => [
        trim(self::TEST_NONCE_VALUE, "'"),
        FALSE,
      ],
      'unprefixed' => [
        str_replace("nonce-", "", self::TEST_NONCE_VALUE),
        FALSE,
      ],
      'missing value' => ["'nonce-'", FALSE],
      'valid base64' => [
        "'nonce-abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890-_/+=='",
        TRUE,
      ],
      'invalid base64' => ["'nonce-ab#cde'", FALSE],
    ];
  }

  /**
   * Test validating value of nonce parameter.
   *
   * @param string $value
   *   The nonce value.
   * @param bool $valid
   *   If the value should be valid.
   *
   * @dataProvider nonceValidationProvider
   */
  public function testNonceValidation(string $value, bool $valid): void {
    $policyHelper = new PolicyHelper($this->nonce);
    $policy = new Csp();

    if (!$valid) {
      $this->expectException(\InvalidArgumentException::class);
    }
    else {
      $this->expectNotToPerformAssertions();
    }

    $policyHelper->appendNonce($policy, 'script', self::TEST_FALLBACK_VALUE, $value);
  }

}
