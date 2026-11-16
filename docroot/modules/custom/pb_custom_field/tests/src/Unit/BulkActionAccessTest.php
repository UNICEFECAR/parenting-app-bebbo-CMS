<?php

namespace Drupal\Tests\pb_custom_field\Unit;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\content_moderation\StateTransitionValidationInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\workflows\StateInterface;
use Drupal\workflows\TransitionInterface;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Verifies that bulk actions honour entity and workflow permissions.
 *
 * @group pb_custom_field
 */
class BulkActionAccessTest extends UnitTestCase {

  /**
   * Returns the existing action plugins and the states they write.
   */
  public static function actions(): array {
    return [
      ['ChangedToPublishedAction', 'published'],
      ['ChangedToArchiveAction', 'archive'],
      ['ChangeToSMEAction', 'sme_review'],
      ['ChangedToSeniorEditorAction', 'senior_editor_review'],
      ['MovefrompublishtodraftAction', 'draft'],
      ['MovefrompublishtosmeAction', 'sme_review'],
      ['MovefrompublishtosenioreditorAction', 'senior_editor_review'],
      ['AssigncontentAction', NULL],
    ];
  }

  /**
   * Tests denial, a permitted transition, and the return-object contract.
   */
  #[DataProvider('actions')]
  public function testActionAccess(string $plugin, ?string $target): void {
    $class = 'Drupal\\pb_custom_field\\Plugin\\Action\\' . $plugin;
    $action = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
    $account = $this->createMock(AccountInterface::class);
    $node = $this->createMock(NodeInterface::class);
    $node->method('getCacheContexts')->willReturn([]);
    $node->method('getCacheTags')->willReturn([]);
    $node->method('getCacheMaxAge')->willReturn(-1);
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('access')->willReturn(AccessResult::allowed());
    $node->method('get')->with('status')->willReturn($field);
    $node->method('access')->willReturn(AccessResult::allowed());
    $validator = $this->createMock(StateTransitionValidationInterface::class);
    $state = $this->createMock(StateInterface::class);
    $state->method('id')->willReturn($target ?? 'draft');
    $transition = $this->createMock(TransitionInterface::class);
    $transition->method('to')->willReturn($state);
    $validator->method('getValidTransitions')->willReturn([$transition]);
    $container = new ContainerBuilder();
    $moderation = $this->createMock(ModerationInformationInterface::class);
    $moderation->method('isModeratedEntity')->willReturn(TRUE);
    $container->set('content_moderation.moderation_information', $moderation);
    $container->set('cache_contexts_manager', new CacheContextsManager($container, ['user.permissions']));
    $container->set('content_moderation.state_transition_validation', $validator);
    \Drupal::setContainer($container);
    $this->assertTrue($action->access($node, $account));
    $this->assertTrue($action->access($node, $account, TRUE)->isAllowed());

    $denied_node = $this->createMock(NodeInterface::class);
    $denied_node->method('getCacheContexts')->willReturn([]);
    $denied_node->method('getCacheTags')->willReturn([]);
    $denied_node->method('getCacheMaxAge')->willReturn(-1);
    $denied_node->method('get')->willReturn($field);
    $denied_node->method('access')->willReturn(AccessResult::forbidden());
    $this->assertFalse($action->access($denied_node, $account));
    $this->assertFalse($action->access(new \stdClass(), $account));
    $this->assertTrue($action->access(new \stdClass(), $account, TRUE)->isForbidden());

    if ($target !== NULL) {
      $validator = $this->createMock(StateTransitionValidationInterface::class);
      $validator->method('getValidTransitions')->willReturn([]);
      $container->set('content_moderation.state_transition_validation', $validator);
      $this->assertFalse($action->access($node, $account));
    }
  }

}
