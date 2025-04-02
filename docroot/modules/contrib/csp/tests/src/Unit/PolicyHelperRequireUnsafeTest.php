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
class PolicyHelperRequireUnsafeTest extends PolicyHelperTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->nonce
      ->expects($this->never())
      ->method('getSource');
  }

  /**
   * Data Provider for unsafe-inline tests.
   *
   * @return array<string, array{array<string, string[]>, array<string, string[]>, array<string, string[]|bool>}>
   *   An array of test values.
   */
  public static function policyProvider(): array {
    return [
      'empty policy' => [
        [],
        [
          'script' => ['elem', 'attr'],
          'style' => ['elem', 'attr'],
        ],
        [
          'default-src' => FALSE,
          'script-src' => FALSE,
          'script-src-elem' => FALSE,
          'script-src-attr' => FALSE,
          'style-src' => FALSE,
          'style-src-elem' => FALSE,
          'style-src-attr' => FALSE,
        ],
      ],
      'default fallback, require script-elem' => [
        [
          'default-src' => [Csp::POLICY_SELF],
        ],
        ['script' => ['elem']],
        [
          'default-src' => [Csp::POLICY_SELF],
          'script-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          'script-src-elem' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          'script-src-attr' => [Csp::POLICY_SELF],
        ],
      ],
      'default fallback, require script-attr' => [
        [
          'default-src' => [Csp::POLICY_SELF],
        ],
        ['script' => ['attr']],
        [
          'default-src' => [Csp::POLICY_SELF],
          'script-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          'script-src-elem' => [Csp::POLICY_SELF],
          'script-src-attr' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
        ],
      ],
      'script fallback, require script-elem' => [
        [
          'script-src' => [Csp::POLICY_SELF],
        ],
        ['script' => ['elem']],
        [
          'default-src' => FALSE,
          'script-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          'script-src-elem' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          'script-src-attr' => [Csp::POLICY_SELF],
        ],
      ],
      'script fallback, require script-attr' => [
        [
          'script-src' => [Csp::POLICY_SELF],
        ],
        ['script' => ['attr']],
        [
          'default-src' => FALSE,
          'script-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          'script-src-elem' => [Csp::POLICY_SELF],
          'script-src-attr' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
        ],
      ],
      'script fallback, require both script' => [
        [
          'script-src' => [Csp::POLICY_SELF],
        ],
        ['script' => ['elem', 'attr']],
        [
          'default-src' => FALSE,
          'script-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          'script-src-elem' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          'script-src-attr' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
        ],
      ],
      'script fallback with nonce, require script-elem' => [
        [
          'script-src' => [Csp::POLICY_SELF, "'nonce-abcde'"],
        ],
        ['script' => ['elem']],
        [
          'default-src' => FALSE,
          'script-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          'script-src-elem' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          'script-src-attr' => [Csp::POLICY_SELF],
        ],
      ],
      'script fallback with nonce, require script-attr' => [
        [
          'script-src' => [Csp::POLICY_SELF, "'nonce-abcde'"],
        ],
        ['script' => ['attr']],
        [
          'default-src' => FALSE,
          'script-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          'script-src-elem' => [Csp::POLICY_SELF, "'nonce-abcde'"],
          'script-src-attr' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
        ],
      ],
      'script fallback with hash, require script-elem' => [
        [
          'script-src' => [Csp::POLICY_SELF, "'sha256-abcde'"],
        ],
        ['script' => ['elem']],
        [
          'default-src' => FALSE,
          'script-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          'script-src-elem' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          // Hash shouldn't be copied if 'unsafe-hashes' is not present.
          'script-src-attr' => [Csp::POLICY_SELF],
        ],
      ],
      'script fallback with hash & unsafe-hashes, require script-elem' => [
        [
          'script-src' => [Csp::POLICY_SELF, "'sha256-abcde'", Csp::POLICY_UNSAFE_HASHES],
        ],
        ['script' => ['elem']],
        [
          'default-src' => FALSE,
          'script-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          // 'unsafe-hashes' shouldn't be copied to element attribute.
          'script-src-elem' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          'script-src-attr' => [Csp::POLICY_SELF, "'sha256-abcde'", Csp::POLICY_UNSAFE_HASHES],
        ],
      ],
      'script fallback with hash, require script-attr' => [
        [
          'script-src' => [Csp::POLICY_SELF, "'sha256-abcde'"],
        ],
        ['script' => ['attr']],
        [
          'default-src' => FALSE,
          'script-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          'script-src-elem' => [Csp::POLICY_SELF, "'sha256-abcde'"],
          // Hash shouldn't be copied if 'unsafe-hashes' is not present.
          'script-src-attr' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
        ],
      ],
      'script fallback with hash & unsafe-hashes, require script-attr' => [
        [
          'script-src' => [Csp::POLICY_SELF, "'sha256-abcde'", Csp::POLICY_UNSAFE_HASHES],
        ],
        ['script' => ['attr']],
        [
          'default-src' => FALSE,
          'script-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          // 'unsafe-hashes' shouldn't be copied to element attribute.
          'script-src-elem' => [Csp::POLICY_SELF, "'sha256-abcde'"],
          // 'unsafe-hashes' should be removed since 'unsafe-inline' is more
          // lenient.
          'script-src-attr' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
        ],
      ],

      'empty script fallback, require script-elem' => [
        [
          'script-src' => [],
        ],
        ['script' => ['elem']],
        [
          'default-src' => FALSE,
          'script-src' => [Csp::POLICY_UNSAFE_INLINE],
          'script-src-elem' => [Csp::POLICY_UNSAFE_INLINE],
          'script-src-attr' => [],
        ],
      ],
      'empty script fallback, require script-attr' => [
        [
          'script-src' => [],
        ],
        ['script' => ['attr']],
        [
          'default-src' => FALSE,
          'script-src' => [Csp::POLICY_UNSAFE_INLINE],
          'script-src-elem' => [],
          'script-src-attr' => [Csp::POLICY_UNSAFE_INLINE],
        ],
      ],

      'script fallback with strict-dynamic, require script-elem' => [
        [
          'script-src' => [Csp::POLICY_SELF, Csp::POLICY_STRICT_DYNAMIC],
        ],
        ['script' => ['elem']],
        [
          'default-src' => FALSE,
          'script-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          'script-src-elem' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          'script-src-attr' => [Csp::POLICY_SELF, Csp::POLICY_STRICT_DYNAMIC],
        ],
      ],
      'script fallback with strict-dynamic, require script-attr' => [
        [
          'script-src' => [Csp::POLICY_SELF, Csp::POLICY_STRICT_DYNAMIC],
        ],
        ['script' => ['attr']],
        [
          'default-src' => FALSE,
          'script-src' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
          'script-src-elem' => [Csp::POLICY_SELF, Csp::POLICY_STRICT_DYNAMIC],
          'script-src-attr' => [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
        ],
      ],
    ];
  }

  /**
   * Test requiring 'unsafe-inline'.
   *
   * @param array<string, string[]> $policyDirectives
   *   An array of directive values to initiate the policy.
   * @param array<string, string[]> $requireFor
   *   An array with the keys 'script' and/or 'style', and values an array of
   *   'elem' and/or 'attr'.
   * @param array<string, string[]|bool> $expectedDirectives
   *   An array of expected directive values after.
   *
   * @dataProvider policyProvider
   */
  public function testRequireUnsafeInline(array $policyDirectives, array $requireFor, array $expectedDirectives): void {
    $helper = new PolicyHelper($this->nonce);

    $policy = new Csp();
    foreach ($policyDirectives as $directive => $value) {
      $policy->setDirective($directive, $value);
    }

    foreach ($requireFor as $requireForName => $requireForTypes) {
      foreach ($requireForTypes as $requireForType) {
        $helper->requireUnsafeInline($policy, $requireForName, $requireForType);
      }
    }

    $this->assertPolicyEquals($expectedDirectives, $policy);
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

    $policyHelper->requireUnsafeInline($policy, $directive, 'elem');
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

    $policyHelper->requireUnsafeInline($policy, 'script', $type);
  }

}
