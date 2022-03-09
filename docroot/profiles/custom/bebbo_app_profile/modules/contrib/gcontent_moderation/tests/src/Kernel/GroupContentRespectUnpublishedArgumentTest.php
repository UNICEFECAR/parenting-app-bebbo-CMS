<?php

namespace Drupal\Tests\gcontent_moderation\Kernel\Views;

use Drupal\group\Entity\Group;
use Drupal\node\Entity\Node;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\user\Entity\User;
use Drupal\views\Views;

/**
 * Tests the group_content_respect_unpublished argument handler.
 *
 * @see \Drupal\gcontent_moderation\Plugin\views\filter\GroupContentRespectUnpublished
 *
 * @group group
 */
class GroupContentRespectUnpublishedArgumentTest extends ViewsKernelTestBase {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'content_moderation',
    'gcontent_moderation',
    'gcontent_moderation_test',
    'gnode',
    'group',
    'group_test_config',
    'field',
    'text',
    'workflows',
    'node',
    'variationcache',
    'entity',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = FALSE) {
    parent::setUp($import_test_views);

    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('node_type');
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_type');
    $this->installEntitySchema('group_content');
    $this->installEntitySchema('group_content_type');
    $this->installEntitySchema('workflow');
    $module_configs = [
      'content_moderation',
      'gcontent_moderation',
      'gcontent_moderation_test',
      'group',
      'group_test_config',
      'field',
      'node',
      'text',
      'workflows',
    ];
    $this->installConfig($module_configs);
    $this->installSchema('node', ['node_access']);

    // Set the current user so group creation can rely on it.
    $account = User::create(['name' => $this->randomString()]);
    $account->save();
    $this->container->get('current_user')->setAccount($account);

    /** @var \Drupal\group\Entity\GroupType $type */
    $type = $this->container->get('entity_type.manager')->getStorage('group_type')->load('default');

    /** @var \Drupal\group\Entity\Storage\GroupContentTypeStorageInterface $storage */
    $storage = $this->container->get('entity_type.manager')->getStorage('group_content_type');
    $storage->createFromPlugin($type, 'group_node:default')->save();
    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = $this->container->get('entity_type.manager')->getStorage('workflow')->load('editorial');
    $workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'default');
    $workflow->save();

    $outsider_role = $type->getOutsiderRole();
    $permissions = [
      'access content overview',
      'create group_node:default entity',
      'delete own group_node:default entity',
      'leave group',
      'update own group_membership content',
      'update own group_node:default entity',
      'use editorial transition archive',
      'use editorial transition archived_draft',
      'use editorial transition archived_published',
      'use editorial transition create_new_draft',
      'view group',
      'view group_membership content',
      'view group_node:default entity',
      'view latest version',
      'view own unpublished group_node:default entity',
    ];
    $outsider_role->grantPermissions($permissions)->trustData()->save();

    $member_role = $type->getMemberRole();
    $permissions = [
      'access content overview',
      'administer members',
      'create group_node:default content',
      'create group_node:default entity',
      'delete any group_node:default content',
      'delete any group_node:default entity',
      'delete own group_node:default content',
      'delete own group_node:default entity',
      'leave group',
      'update any group_node:default content',
      'update any group_node:default entity',
      'update own group_membership content',
      'update own group_node:default content',
      'update own group_node:default entity',
      'use editorial transition archive',
      'use editorial transition archived_draft',
      'use editorial transition archived_published',
      'use editorial transition create_new_draft',
      'use editorial transition publish',
      'view group',
      'view group_membership content',
      'view group_node:default content',
      'view group_node:default entity',
      'view latest version',
      'view own unpublished group_node:default entity',
      'view unpublished group_node:default entity',
    ];
    $member_role->grantPermissions($permissions)->trustData()->save();

  }

  /**
   * Tests the group content respect unpublished argument.
   */
  public function testGroupContentRespectUnpublishedArgument() {
    $view = Views::getView('test_moderated_group_content');
    $view->setDisplay();

    /** @var \Drupal\user\UserInterface$user1 */
    $user1 = $this->container->get('current_user')->getAccount();

    /** @var \Drupal\group\Entity\GroupInterface $group1 */
    $group1 = Group::create([
      'type' => 'default',
      'label' => $this->randomMachineName(),
    ]);
    $group1->save();

    /** @var \Drupal\node\Entity\Node $node1 */
    Node::create([
      'type' => 'default',
      'title' => 'Node1',
      'moderation_state' => 'draft',
    ])->save();
    $node1 = $this->container->get('entity_type.manager')->getStorage('node')->loadByProperties(
      ['title' => 'Node1']
    );
    $node1 = current($node1);

    /** @var \Drupal\node\Entity\Node $node2 */
    Node::create([
      'type' => 'default',
      'title' => 'Node2',
      'moderation_state' => 'published',
    ])->save();
    $node2 = $this->container->get('entity_type.manager')->getStorage('node')->loadByProperties(
      ['title' => 'Node2']
    );
    $node2 = current($node2);
    $group1->addContent($node1, 'group_node:default');
    $group1->addContent($node2, 'group_node:default');
    $group1->addMember($user1);

    $view->preview();
    $this->assertEquals(0, count($view->result), 'No results when group id argument is not present.');
    $view->destroy();

    $view->preview('moderated_content', [$group1->id()]);
    $this->assertEquals(1, count($view->result), 'Member can see their own unpublished content.');

    $user2 = User::create(['name' => $this->randomString()]);
    $user2->save();
    $this->container->get('current_user')->setAccount($user2);

    /** @var \Drupal\node\Entity\Node $node3 */
    Node::create([
      'type' => 'default',
      'title' => 'Node3',
      'moderation_state' => 'draft',
    ])->save();
    $node3 = $this->container->get('entity_type.manager')->getStorage('node')->loadByProperties(
      ['title' => 'Node3']
    );
    $node3 = current($node3);
    $group1->addContent($node3, 'group_node:default');

    $view->preview('moderated_content', [$group1->id()]);
    $this->assertEquals(1, count($view->result), 'Outsider can see their own unpublished content.');
    $view->destroy();

    $this->container->get('current_user')->setAccount($user1);

    $view->preview('moderated_content', [$group1->id()]);
    $this->assertEquals(2, count($view->result), 'Member can see any (own + outsiders) unpublished content.');
    $view->destroy();

  }

}
