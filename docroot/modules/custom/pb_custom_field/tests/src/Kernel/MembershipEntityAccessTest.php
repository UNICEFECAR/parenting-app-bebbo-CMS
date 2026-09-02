<?php

namespace Drupal\Tests\pb_custom_field\Kernel;

use Drupal\Tests\group\Kernel\GroupKernelTestBase;

/**
 * Tests the uid-1/self guards on membership entity access.
 *
 * @group pb_custom_field
 */
class MembershipEntityAccessTest extends GroupKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['pb_custom_field'];

  /**
   * Group admins must never gain via-group access to uid 1 or themselves.
   */
  public function testUidOneAndSelfAreGuarded(): void {
    $admin = $this->createUser();
    $group_type = $this->createGroupType();
    // Grant the entity permissions our relation-type alter hook enables.
    $role = $this->createGroupRole([
      'group_type' => $group_type->id(),
      'scope' => 'individual',
      'permissions' => [
        'update any group_membership entity',
        'view group_membership entity',
      ],
    ]);

    $group = $this->createGroup(['type' => $group_type->id()]);
    $group->addMember($admin, ['group_roles' => [$role->id()]]);

    $member = $this->createUser();
    $group->addMember($member);

    $uid1 = $this->container->get('entity_type.manager')->getStorage('user')->load(1);
    $group->addMember($uid1);

    $access_control = $this->pluginManager->getAccessControlHandler('group_membership');

    // Regular member: group admin may update.
    $this->assertTrue($access_control->entityAccess($member, 'update', $admin));
    // Uid 1: never via group permissions.
    $this->assertFalse($access_control->entityAccess($uid1, 'update', $admin));
    // Self: never via group permissions.
    $this->assertFalse($access_control->entityAccess($admin, 'update', $admin));
  }

}
