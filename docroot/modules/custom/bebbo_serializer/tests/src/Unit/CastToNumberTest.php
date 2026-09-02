<?php

namespace Drupal\Tests\bebbo_serializer\Unit;

use Drupal\bebbo_serializer\Plugin\views\style\BebboSerializer;
use Drupal\Tests\UnitTestCase;

/**
 * Tests numeric casting of REST row fields.
 *
 * Regression: empty decimal fields (average_height/average_weight) arrive as
 * empty strings from Views, and "" + 0 throws a TypeError under PHP 8.
 *
 * @group bebbo_serializer
 */
class CastToNumberTest extends UnitTestCase {

  /**
   * Invokes the private castToNumber() on a fresh plugin instance.
   */
  private function castToNumber(array $row, array $fields): array {
    $plugin = (new \ReflectionClass(BebboSerializer::class))
      ->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod(BebboSerializer::class, 'castToNumber');
    $method->setAccessible(TRUE);
    $method->invokeArgs($plugin, [&$row, $fields]);
    return $row;
  }

  /**
   * Empty strings must not throw and must default to 0.
   */
  public function testEmptyStringBecomesZero(): void {
    $row = $this->castToNumber(
      ['average_height' => '', 'average_weight' => '1.5'],
      ['average_height', 'average_weight']
    );
    $this->assertSame(0, $row['average_height']);
    $this->assertSame(1.5, $row['average_weight']);
  }

  /**
   * Natural int/float types are preserved (no rounding, no float coercion).
   */
  public function testPreservesNaturalNumericType(): void {
    $row = $this->castToNumber(
      ['h' => '12', 'w' => '1.50'],
      ['h', 'w']
    );
    $this->assertSame(12, $row['h']);
    $this->assertSame(1.5, $row['w']);
  }

  /**
   * Non-numeric strings default to 0 instead of throwing.
   */
  public function testNonNumericBecomesZero(): void {
    $row = $this->castToNumber(['x' => 'n/a'], ['x']);
    $this->assertSame(0, $row['x']);
  }

}
