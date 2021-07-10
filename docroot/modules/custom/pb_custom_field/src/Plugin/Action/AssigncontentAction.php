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
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\AjaxResponse;

use Drupal\group\Entity;
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
    
         /* get the logged in user details */
         $currentAccount = \Drupal::currentUser();
         $cur_user_roles = $currentAccount->getRoles();
         $authorized_roles = array('se','sme','editor','reviewer');
                   
        /* get all the country list */
        $country_list = [];
        $country_list[''] = "Select Country";
        $group = \Drupal\group\Entity\Group::loadMultiple();
        foreach ($group as $grp) {
            $country_list[$grp->id()]=$grp->label();
        } 

        $language_options = [];
        /* get all the languages list 
        foreach (\Drupal::languageManager()->getLanguages(LanguageInterface::STATE_CONFIGURABLE) as $langcode => $language) {
            $language_options[$langcode] = $language->getName();
        }
        */
        /* Check the user roles */
        if (count(array_intersect($cur_user_roles, $authorized_roles)) != 0) {
          
          $grp_membership_service = \Drupal::service('group.membership_loader');
          $grps = $grp_membership_service->loadByUser($currentAccount);

          if(!empty($grps))
          {
            $country_list = [];
            foreach ($grps as $grp) {
              $groups = $grp->getGroup();
              $country_list[$groups->id()]=$groups->label();
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
          '#required' => TRUE,
          '#default_value' => '',
          '#ajax' => ['callback' => [$this, 'getlanguages'],  'event' => 'change',
                        'method' => 'html',
                        'wrapper' => 'language_option',
                        'progress' => [
                          'type' => 'throbber',
                          'message' => NULL,
                        ],
                      ],
        ];

        $form['language_option'] = [
          '#title' => t('Select Language'),
          '#type' => 'select',
          '#options' => $language_options,                    
          '#required' => TRUE,
          '#attributes' => ["id" => 'language_option'],
          '#validated' => TRUE,
          '#placeholder' => 'Select Language'
        ];
   
      return $form;  
  }

  
  /* ajax method to get the language data */
  
public function getlanguages(array &$element, FormStateInterface $form_state) {
  $triggeringElement = $form_state->getTriggeringElement();
  $value = $triggeringElement['#value'];
  $renderedField = '';
  $language_options = [];
  $language_options[''] = "Select Language";
  if(!empty($value)) 
  {
        /* load group */
        $groups = Group::load($value);
        $languages = $groups->get('field_language')->getString();
        $language_arr = explode(",",$languages);
        $language_arr = array_map('trim', explode(',', $languages));
       foreach (\Drupal::languageManager()->getLanguages(LanguageInterface::STATE_CONFIGURABLE) as $langcode => $language) {
          if(in_array($langcode,$language_arr)){
            $language_options[$langcode] = $language->getName();
          }
        }
      
        foreach ($language_options as $key => $value) {
          $renderedField .= "<option value='".$key."'>".$value."</option>";
        }
  }
  $wrapper_id = $triggeringElement["#ajax"]["wrapper"];
  $response = new AjaxResponse();
  $response->addCommand(new HtmlCommand("#".$wrapper_id, $renderedField));
  return $response;
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
       $nid = $entity->get('nid')->getString();
       $node = node_load($nid);
       /* check the translation available in this content */
       if(!$node->hasTranslation($langoption))
       {
        $node_lang = $node->getTranslation($current_language);
        $node_es = $node->addTranslation($langoption, $node_lang->toArray());
        $node_es->set('moderation_state', 'draft');
        $node_es->set('langcode',$langoption);
        $node->save();
       }
       else
       {
        /* if the translated content available check the content available in group */
        $etype = $node->getType();
        $pluginId = 'group_node:' .$etype;
        $group = Group::load($countryoption);
        $grp_obj = $group->getContentByEntityId($pluginId,$nid);
        $alexists_langcode = false;
       }
       $message = "Content assigned to country";
       return $this->t($message);
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