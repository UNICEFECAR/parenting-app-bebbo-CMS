<?php

namespace Drupal\Tests\bebbo_serializer\Unit;

use Drupal\bebbo_serializer\Plugin\views\style\BebboSerializer;
use Drupal\bebbo_serializer\Plugin\views\style\BebboV1Serializer;
use Drupal\Tests\UnitTestCase;

/**
 * Tests machine name derivation from term labels.
 *
 * The category endpoints return field_type_of_article as a machine name, and
 * the type_of_article vocabulary has no field_unique_name to read, so the
 * value is derived from the English label. V1 and V2 must agree.
 *
 * @group bebbo_serializer
 */
class SlugifyTermNameTest extends UnitTestCase {

  /**
   * Invokes the private slugifyTermName() on a fresh plugin instance.
   */
  private function slugify(string $class, string $name): string {
    $plugin = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod($class, 'slugifyTermName');
    $method->setAccessible(TRUE);
    return $method->invoke($plugin, $name);
  }

  /**
   * Provides labels and the machine names they must produce.
   */
  public static function labelProvider(): array {
    return [
      'live label' => ['Article for birth to 6 years', 'article_for_birth_to_6_years'],
      'live label with tools' => ['Article for health and growth tools', 'article_for_health_and_growth_tools'],
      'already lowercase' => ['Article for pregnancy', 'article_for_pregnancy'],
      'punctuation collapses' => ['Health  &  Well-being!', 'health_well_being'],
      'leading and trailing junk trimmed' => ['  -- Vaccination -- ', 'vaccination'],
      'non-latin has no alphanumerics left' => ['Питание', ''],
      'empty stays empty' => ['', ''],
    ];
  }

  /**
   * Both serializers must derive identical machine names.
   *
   * @dataProvider labelProvider
   */
  public function testSlugifyMatchesAcrossSerializers(string $label, string $expected): void {
    $this->assertSame($expected, $this->slugify(BebboSerializer::class, $label));
    $this->assertSame($expected, $this->slugify(BebboV1Serializer::class, $label));
  }

}
