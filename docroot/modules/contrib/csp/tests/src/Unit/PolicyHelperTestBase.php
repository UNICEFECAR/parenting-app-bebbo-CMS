<?php

namespace Drupal\Tests\csp\Unit;

use Drupal\csp\Nonce;
use Drupal\Tests\UnitTestCase;

/**
 * Base class for PolicyHelper tests.
 */
class PolicyHelperTestBase extends UnitTestCase {

  use AssertPolicyTrait;

  /**
   * A Nonce service mock.
   *
   * @var \Drupal\csp\Nonce|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $nonce;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->nonce = $this->createMock(Nonce::class);
  }

  /**
   * Data provider for testing directive validation.
   */
  public static function directiveValidationProvider(): array {
    return [
      'empty string' => ['', FALSE],
      'default' => ['default', FALSE],
      'script' => ['script', TRUE],
      'script-src' => ['script-src', FALSE],
      'script-src-elem' => ['script-src-elem', FALSE],
      'script-src-attr' => ['script-src-attr', FALSE],
      'style' => ['style', TRUE],
      'style-src' => ['style-src', FALSE],
      'style-src-elem' => ['style-src-elem', FALSE],
      'style-src-attr' => ['style-src-attr', FALSE],
      'font' => ['font', FALSE],
    ];
  }

  /**
   * Data provider for testing type validation.
   */
  public static function typeValidationProvider(): array {
    return [
      'empty string' => ['', FALSE],
      'src' => ['src', FALSE],
      'elem' => ['elem', TRUE],
      '-elem' => ['-elem', FALSE],
      'attr' => ['attr', TRUE],
      '-attr' => ['-attr', FALSE],
    ];
  }

}
