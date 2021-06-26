<?php

namespace Drupal\pb_custom_field\Plugin\Action;

use Drupal\node\Entity\Node;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;


use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\group\Entity\Group;
use \Drupal\group\Entity\GroupContent;
/**
 * Action description.
 *
 * @Action(
 *   id = "pb_custom_field_assign_action",
 *   label = @Translation("Assign Content to Country"),
 *   type = "node",
 *   confirm = FALSE
 *   
 * )
 */

//confirm_form_route_name = "pb_custom_field.views_language_confirm_form"
class AssigncontentAction extends ViewsBulkOperationsActionBase {

  use StringTranslationTrait;

  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
   

         $currentAccount = \Drupal::currentUser();
         $cur_user_roles = $currentAccount->getRoles();
         $authorized_roles = array('reviewer');
          
        /* get all the country list */
        $country_list = [];
        $group = \Drupal\group\Entity\Group::loadMultiple();
        foreach ($group as $grp) {
            $country_list[$grp->id()]=$grp->label();
        } 

        /* get all the languages list */
        $language_options = [];
        foreach (\Drupal::languageManager()->getLanguages(LanguageInterface::STATE_CONFIGURABLE) as $langcode => $language) {
            $language_options[$langcode] = $language->getName();
        }

        /* Check the user roles */
        if (count(array_intersect($cur_user_roles, $authorized_roles)) != 0) {
          
          $grp_membership_service = \Drupal::service('group.membership_loader');
          $grps = $grp_membership_service->loadByUser($currentAccount);
          if(!empty($grps))
          {
            $country_list = [];
            foreach ($grps as $grp) {
              $groups = $grp->getGroup();
              $country_list[]=$groups->label();
            } 
         
            $languages = $groups->get('field_language')->getString();
            $language_arr = explode(",",$languages);
            $language_arr = array_map('trim', explode(',', $languages));
            $language_options = [];
            foreach (\Drupal::languageManager()->getLanguages(LanguageInterface::STATE_CONFIGURABLE) as $langcode => $language) {
              if(in_array($langcode,$language_arr)){
                $language_options[$langcode] = $language->getName();
              }
            }
          }
        }
        $form['country_option'] = [
          '#title' => t('Select Country'),
          '#type' => 'select',
          '#options' => $country_list,
        ];

        $form['language_option'] = [
          '#title' => t('Select Language'),
          '#type' => 'select',
          '#options' => $language_options,
        ];
   
      return $form;  
  }

   /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
      $country_code = $form_state->getvalue('country_option');
      if(empty($country_code))
      {
        $form_state->setErrorByName('country_option', $this->t('Please select the Country.'));
      }
      $language_code = $form_state->getvalue('language_option');
      if(empty($language_code))
      {
        $form_state->setErrorByName('language_option', $this->t('Please select the language.'));
      }
     
  }

   /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['language_option'] = $form_state->getValue('language_option');
    $this->configuration['country_option'] = $form_state->getValue('country_option');
  }

  /**
   * {@inheritdoc}
   */
  public function execute(ContentEntityInterface $entity = NULL) {
    $langoption = $this->configuration['language_option'];
    $countryoption = $this->configuration['country_option'];
    if(!empty($langoption) && !empty($countryoption) ) {
       $current_language = $entity->get('langcode')->value;
       if($current_language != $langoption)
       {
        $nid = $entity->get('nid')->getString();
        $node = node_load($nid);
        $node_es = $node->addTranslation($langoption, $node->toArray());
        $node_es->set('moderation_state', 'draft');
        $node->save();
        $etype = $node->getType();
        $pluginId = 'group_node:' .$etype;
        $group = Group::load($countryoption);
        $group->addContent($node, $pluginId);
        return $this->t('Content assigned to country');
       }
    }
  }

  /**
   * {@inheritdoc}
  */
  
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($object->getEntityType() === 'node') {
      $access = $object->access('update', $account, TRUE)
        ->andIf($object->status->access('edit', $account, TRUE));
      return $return_as_object ? $access : $access->isAllowed();
    }

    return TRUE;
  }
 
}
