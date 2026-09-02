<?php

declare(strict_types=1);

namespace Drupal\Tests\bebbo_serializer\Unit;

use Drupal\bebbo_serializer\Plugin\Field\FieldFormatter\RawTargetIdFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests that the raw target ID formatter prints IDs without entity loads.
 *
 * @group bebbo_serializer
 */
class RawTargetIdFormatterTest extends UnitTestCase {

  /**
   * Builds a formatter with a mocked field definition.
   */
  private function formatter(): RawTargetIdFormatter {
    return new RawTargetIdFormatter(
      'bebbo_raw_target_id',
      [],
      $this->createMock(FieldDefinitionInterface::class),
      [],
      'hidden',
      'default',
      [],
    );
  }

  /**
   * Builds an item list whose items expose only target_id — no entity.
   */
  private function items(array $ids): FieldItemListInterface {
    $items = [];
    foreach ($ids as $id) {
      $items[] = (object) ['target_id' => $id];
    }
    $list = $this->getMockBuilder(FieldItemList::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getIterator'])
      ->getMock();
    $list->method('getIterator')->willReturn(new \ArrayIterator($items));
    return $list;
  }

  /**
   * Each item becomes a plain-text element carrying its target ID only.
   */
  public function testEmitsTargetIdsAsPlainText(): void {
    $elements = $this->formatter()->viewElements($this->items([12, '345', 6]), 'ru');
    $this->assertSame([
      ['#plain_text' => '12'],
      ['#plain_text' => '345'],
      ['#plain_text' => '6'],
    ], $elements);
  }

  /**
   * Empty field yields no elements.
   */
  public function testEmptyListYieldsNothing(): void {
    $this->assertSame([], $this->formatter()->viewElements($this->items([]), 'en'));
  }

}
