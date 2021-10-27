<?php

namespace Drupal\pb_custom_field\Plugin\Action;

use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\group\Entity\Group;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\AjaxResponse;

/*
use Drupal\group\Entity;
use Drupal\node\Entity\Node;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Language\LanguageManager;
use Drupal\group\Entity\GroupContent;
use Drupal\Core\Url;
 */
/**
 * Action description.
 *
 * @Action(
 *   id = "pb_custom_field_assign_action",
 *   label = @Translation("Assign Content to Country"),
 *   type = "node",
 *   confirm = FALSE
 * )
 */
class AssigncontentAction extends ViewsBulkOperationsActionBase {

  use StringTranslationTrait;
  /**
   * Get the total translated count.
   *
   * @var int
   */
  public $assigned = 0;
  /**
   * Get the total non translated count.
   *
   * @var int
   */
  public $nonAssigned = 0;
  /**
   * Get the total items processed.
   *
   * @var int
   */
  public $processItem = 0;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    /* get the logged in user details */
    $currentAccount = \Drupal::currentUser();
    $cur_user_roles = $currentAccount->getRoles();
    $authorized_roles = ['se', 'sme', 'editor', 'reviewer'];

    /* get all the country list */
    $country_list = [];
    $country_list[''] = "Select Country";
    $group = Group :: loadMultiple();
    foreach ($group as $grp) {
      $country_list[$grp->id()] = $grp->label();
    }

    $language_options = [];
    /* Check the user roles */
    if (count(array_intersect($cur_user_roles, $authorized_roles)) != 0) {
      $grp_membership_service = \Drupal::service('group.membership_loader');
      $grps = $grp_membership_service->loadByUser($currentAccount);

      if (!empty($grps)) {
        $country_list = [];
        foreach ($grps as $grp) {
          $groups = $grp->getGroup();
          $country_list[$groups->id()] = $groups->label();
        }
        $languages = $groups->get('field_language')->getString();
        $language_arr = explode(",", $languages);
        $language_arr = array_map('trim', explode(',', $languages));
        $language_options = [];
        foreach (\Drupal::languageManager()->getLanguages(LanguageInterface::STATE_CONFIGURABLE) as $langcode => $language) {
          if (in_array($langcode, $language_arr)) {
            $language_options[$langcode] = $language->getName();
          }
        }
      }
    }
    $form['country_option'] = [
      '#title' => $this->t('Select Country'),
      '#type' => 'select',
      '#options' => $country_list,
      '#required' => TRUE,
      '#default_value' => '',
      '#ajax' => [
        'callback' => [$this, 'getlanguages'],
        'event' => 'change',
        'method' => 'html',
        'wrapper' => 'language_option',
        'progress' => [
          'type' => 'throbber',
          'message' => NULL,
        ],
      ],
    ];

    $form['language_option'] = [
      '#title' => $this->t('Select Language'),
      '#type' => 'select',
      '#options' => $language_options,
      '#required' => TRUE,
      '#attributes' => ["id" => 'language_option'],
      '#validated' => TRUE,
      '#placeholder' => 'Select Language',
    ];

    return $form;
  }

  /**
   * Ajax method to get the language data.
   */
  public function getlanguages(array &$element, FormStateInterface $form_state) {
    $triggeringElement = $form_state->getTriggeringElement();
    $value = $triggeringElement['#value'];
    $renderedField = '';
    $language_options = [];
    $language_options[''] = "Select Language";
    if (!empty($value)) {
      /* load group */
      $groups = Group::load($value);
      $languages = $groups->get('field_language')->getString();
      $language_arr = explode(",", $languages);
      $language_arr = array_map('trim', explode(',', $languages));
      foreach (\Drupal::languageManager()->getLanguages(LanguageInterface::STATE_CONFIGURABLE) as $langcode => $language) {
        if (in_array($langcode, $language_arr)) {
          $language_options[$langcode] = $language->getName();
        }
      }

      foreach ($language_options as $key => $value) {
        $renderedField .= "<option value='" . $key . "'>" . $value . "</option>";
      }
    }
    $wrapper_id = $triggeringElement["#ajax"]["wrapper"];
    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand("#" . $wrapper_id, $renderedField));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $country_code = $form_state->getvalue('country_option');
    if (empty($country_code)) {
      $form_state->setErrorByName('country_option', $this->t('Please select the Country.'));
    }
    $language_code = $form_state->getvalue('language_option');
    if (empty($language_code)) {
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
    $context = $this->context;
    $total_selected = $context['selected_count'];
    $langoption = $this->configuration['language_option'];
    $countryoption = $this->configuration['country_option'];
    $this->processItem = $this->processItem + 1;
    $message = "";
    $error_message = "";
    if (!empty($langoption) && !empty($countryoption)) {
      $current_language = $entity->get('langcode')->value;
      $nid = $entity->get('nid')->getString();
      $node = node_load($nid);
      $uid = \Drupal::currentUser()->id();
      $uname = \Drupal::currentUser()->getDisplayName();
      if (!$node->hasTranslation($langoption)) {
        $node_lang = $node->getTranslation($current_language);
        /* $node->setRevisionTranslationAffected(FALSE); */
        $node_es = $node->addTranslation($langoption, $node_lang->toArray());
        $node_es->set('moderation_state', 'draft');
        $node_es->set('langcode', $langoption);
        $node_es->set('uid', $uid);
        $node_es->set('content_translation_source', $current_language);
        $node_es->set('changed', time());
        $node_es->set('created', time());
        /* Set new Revision */
        $node_es->setNewRevision(TRUE);
        $node_es->revision_log = 'content assigned from Assign Content to Country option from ' . $current_language . ' by ' . $uname;
        $node_es->setRevisionCreationTime(REQUEST_TIME);
        $node_es->setRevisionUserId($uid);
        $node_es->save();
        $node->save();
        $this->assigned = $this->assigned + 1;
      }
      else {
        $this->nonAssigned = $this->nonAssigned + 1;
      }
      if ($this->assigned > 0) {
        $message = $this->t("Content assigned to country ( @assigned ) <br/>", ['@assigned' => $this->assigned]);
      }
      if ($this->nonAssigned > 0) {
        $error_message = $this->t("Content already exists in country ( @nonassigned ) <br/>", ['@nonassigned' => $this->nonAssigned]);
      }
    }

    if ($total_selected == $this->processItem) {
      if (!empty($message)) {
        drupal_set_message($message, 'status');
      }
      if (!empty($error_message)) {
        drupal_set_message($error_message, 'error');
      }
    }
    return $this->t("Total content selected");
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
