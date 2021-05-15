<?php

namespace Drupal\Tests\feeds\Unit\Feeds\Target;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\feeds\Exception\ReferenceNotFoundException;
use Drupal\feeds\Feeds\Target\ConfigEntityReference;
use Drupal\feeds\FeedTypeInterface;

/**
 * @coversDefaultClass \Drupal\feeds\Feeds\Target\ConfigEntityReference
 * @group feeds
 */
class ConfigEntityReferenceTest extends ConfigEntityReferenceTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->buildContainer();

    $configuration = [
      'feed_type' => $this->createMock(FeedTypeInterface::class),
      'target_definition' => $this->createTargetDefinitionMock(),
      'reference_by' => 'id',
    ];
    $this->targetPlugin = new ConfigEntityReference($configuration, 'config_entity_reference', [], $this->entityTypeManager->reveal(), $this->entityRepository->reveal(), $this->transliteration->reveal(), $this->typedConfigManager->reveal());
  }

  /**
   * {@inheritdoc}
   */
  protected function getTargetClass() {
    return ConfigEntityReference::class;
  }

  /**
   * {@inheritdoc}
   */
  protected function createReferencableEntityType() {
    $referenceable_entity_type = $this->prophesize(ConfigEntityTypeInterface::class);
    $referenceable_entity_type->entityClassImplements(ConfigEntityInterface::class)->willReturn(TRUE)->shouldBeCalled();
    $referenceable_entity_type->getKey('label')->willReturn('label');
    $referenceable_entity_type->getConfigPrefix()->willReturn('foo.foo');

    return $referenceable_entity_type;
  }

  /**
   * Tests finding an entity by ID.
   *
   * @covers ::prepareValue
   * @covers ::findEntity
   */
  public function testPrepareValue() {
    // Entity query.
    $entity_query = $this->prophesize(QueryInterface::class);
    $entity_query->condition('id', 'foo')->willReturn($entity_query);
    $entity_query->range(0, 1)->willReturn($entity_query);
    $entity_query->execute()->willReturn(['foo']);
    $this->entityStorage->getQuery()->willReturn($entity_query)->shouldBeCalled();

    $method = $this->getProtectedClosure($this->targetPlugin, 'prepareValue');
    $values = ['target_id' => 'foo'];
    $method(0, $values);
    $this->assertSame($values, ['target_id' => 'foo']);
  }

  /**
   * Tests prepareValue() method without match.
   *
   * @covers ::prepareValue
   * @covers ::findEntity
   */
  public function testPrepareValueReferenceNotFound() {
    // Entity query.
    $entity_query = $this->prophesize(QueryInterface::class);
    $entity_query->condition('id', 'bar')->willReturn($entity_query);
    $entity_query->range(0, 1)->willReturn($entity_query);
    $entity_query->execute()->willReturn([]);
    $this->entityStorage->getQuery()->willReturn($entity_query)->shouldBeCalled();

    $method = $this->getProtectedClosure($this->targetPlugin, 'prepareValue');
    $values = ['target_id' => 'bar'];
    $this->expectException(ReferenceNotFoundException::class);
    $this->expectExceptionMessage("Referenced entity not found for field <em class=\"placeholder\">id</em> with value <em class=\"placeholder\">bar</em>.");
    $method(0, $values);
  }

  /**
   * @covers ::getSummary
   */
  public function testGetSummary() {
    $expected = [
      'Reference by: <em class="placeholder">ID</em>',
    ];
    $summary = $this->targetPlugin->getSummary();
    foreach ($summary as $key => $value) {
      $summary[$key] = (string) $value;
    }
    $this->assertEquals($expected, $summary);
  }

  /**
   * @covers ::getSummary
   */
  public function testGetSummaryNoReferenceBySet() {
    $config = $this->targetPlugin->getConfiguration();
    $config['reference_by'] = NULL;
    $this->targetPlugin->setConfiguration($config);

    $expected = [
      'Please select a field to reference by.',
    ];
    $summary = $this->targetPlugin->getSummary();
    foreach ($summary as $key => $value) {
      $summary[$key] = (string) $value;
    }
    $this->assertEquals($expected, $summary);
  }

}
