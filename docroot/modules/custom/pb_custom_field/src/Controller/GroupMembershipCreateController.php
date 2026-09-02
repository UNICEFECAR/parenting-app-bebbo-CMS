<?php

namespace Drupal\pb_custom_field\Controller;

use Drupal\group\Entity\Controller\GroupRelationshipController;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\Storage\GroupRelationshipTypeStorageInterface;

/**
 * Creates group membership for a NEW user account via the creation wizard.
 *
 * Identical to the parent controller except that wizard step 1 for the
 * group_membership plugin uses the user entity's "register" form operation
 * (ports the Group 1.x manage-users patch, issue #2949408).
 */
class GroupMembershipCreateController extends GroupRelationshipController {

  /**
   * {@inheritdoc}
   */
  public function createForm(GroupInterface $group, $plugin_id) {
    if ($plugin_id !== 'group_membership') {
      return parent::createForm($group, $plugin_id);
    }

    $wizard_id = 'group_entity';
    $store = $this->privateTempStoreFactory->get($wizard_id);
    $store_id = $plugin_id . ':' . $group->id();

    $group_relation = $group->getGroupType()->getPlugin($plugin_id);
    $config = $group_relation->getConfiguration();
    $extra['group_wizard'] = $config['use_creation_wizard'];
    $extra['group_wizard_id'] = $wizard_id;
    $extra['group'] = $group;
    $extra['group_relation'] = $plugin_id;
    $extra['store_id'] = $store_id;

    $step2 = $extra['group_wizard'] && $store->get("$store_id:step") === 2;

    if (!$step2) {
      // Wizard step 1: the user REGISTER form (not the default profile form).
      $storage = $this->entityTypeManager()->getStorage('user');
      if (!$entity = $store->get("$store_id:entity")) {
        $entity = $storage->create([]);
      }
      $operation = 'register';
    }
    else {
      // Wizard step 2: the relationship add form (same as parent).
      $relationship_type_storage = $this->entityTypeManager()->getStorage('group_relationship_type');
      assert($relationship_type_storage instanceof GroupRelationshipTypeStorageInterface);
      $entity = $this->entityTypeManager()->getStorage('group_relationship')->create([
        'type' => $relationship_type_storage->getRelationshipTypeId($group->bundle(), $plugin_id),
        'gid' => $group->id(),
      ]);
      $operation = 'add';
    }

    return $this->entityFormBuilder()->getForm($entity, $operation, $extra);
  }

}
