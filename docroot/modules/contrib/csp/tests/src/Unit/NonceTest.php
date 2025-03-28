<?php

namespace Drupal\Tests\csp\Unit;

use Drupal\csp\Nonce;
use Drupal\Tests\UnitTestCase;

/**
 * Test the Nonce service.
 *
 * @coversDefaultClass \Drupal\csp\Nonce
 * @group csp
 */
class NonceTest extends UnitTestCase {

  /**
   * The hasValue() method should return the expected value.
   */
  public function testHas() {
    $nonce = new Nonce();

    $this->assertFalse($nonce->hasValue());
    $nonce->getValue();
    $this->assertTrue($nonce->hasValue());
  }

  /**
   * The nonce value should be statically cached.
   */
  public function testValue() {
    $nonce = new Nonce();

    $value1 = $nonce->getValue();
    $value2 = $nonce->getValue();

    $this->assertIsString($value1);
    $this->assertEquals($value1, $value2);
  }

  /**
   * The source value should be properly formatted.
   */
  public function testSource() {
    $nonce = new Nonce();

    // 16 bytes will encode to ceil(16 * 8/6) = 22 characters.
    $this->assertMatchesRegularExpression(
      "/'nonce-[A-Za-z0-9_-]{22}'/",
      $nonce->getSource()
    );
  }

}
