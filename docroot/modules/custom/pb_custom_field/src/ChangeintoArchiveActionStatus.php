<?php

namespace Drupal\pb_custom_field;

use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;


/**
 * Parses and verifies the doc comments for files.
 *
 * PHP version 5
 *
 * @category PHP
 *
 * @package PHP_CodeSnifferzs
 * @copyright 2006-2012 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license https://github.com/squizlabs/blob/master/licence.txt BSD Licence
 * @link http://pear.php.net/package/PHP_CodeSniffer
 */
class ChangeintoArchiveActionStatus {

  /**
   * Processes each required or optional tag.
   */
  public static function offLoadCountryProcessd($n_language, &$context) {
    $message = 'Changing status...';
    $results = [];
    $uid = \Drupal::currentUser()->id();
    $user = User::load($uid);
    $grp_membership_service = \Drupal::service('group.membership_loader');
    $grps = $grp_membership_service->loadByUser($user);
    $success_msg = 0;
    $country_error = 0;
    $same_status_error = 0;
     
    if (!empty($grps)) {
      foreach ($grps as $grp) {
        $groups = $grp->getGroup();
      }
      $grp_country_language = $groups->get('field_language')->getValue();
      $grp_country_new_array = array_column($grp_country_language, 'value');
    }
    foreach ($n_language as $key => $langs) {
      $draft_node = Node::load($key);
      foreach ($langs as $lang) {  
      $node_lang_draft = $draft_node->getTranslation($lang);
      $current_state = $node_lang_draft->moderation_state->value;

      if($current_state !== 'archive' && empty($grps)){
        $node_lang_draft->set('moderation_state', 'archive');
        $node_lang_draft->set('uid', $uid);
        $node_lang_draft->set('content_translation_source', $lang);
        $node_lang_draft->set('changed', time());
        $node_lang_draft->set('created', time());

        $node_lang_draft->setNewRevision(TRUE);
        $node_lang_draft->revision_log = 'Content Changed Into Archive';
        $node_lang_draft->setRevisionCreationTime(REQUEST_TIME);
        $node_lang_draft->setRevisionUserId($uid);
        $node_lang_draft->setRevisionTranslationAffected(NULL);
        $node_lang_draft->save();
        $success_msg++;
      }elseif($current_state !== 'archive' && !empty($grps)){
        if (in_array($lang, $grp_country_new_array)) {
        $node_lang_draft->set('moderation_state', 'archive');
        $node_lang_draft->set('uid', $uid);
        $node_lang_draft->set('content_translation_source', $lang);
        $node_lang_draft->set('changed', time());
        $node_lang_draft->set('created', time());

        $node_lang_draft->setNewRevision(TRUE);
        $node_lang_draft->revision_log = 'Content Changed Into Archive';
        $node_lang_draft->setRevisionCreationTime(REQUEST_TIME);
        $node_lang_draft->setRevisionUserId($uid);
        $node_lang_draft->setRevisionTranslationAffected(NULL);
        $node_lang_draft->save();
        $success_msg++;
      }else{
        $country_error++;
      }
    }else{
        $same_status_error++;

      }
    }
     
      $results[] = $draft_node->save();
    }
    if($success_msg > 0){
      $Succ_message = "Content Changed into Archive successfully (" . $success_msg . ")";
      drupal_set_message(t($Succ_message), 'status');
    }
    if($same_status_error > 0){
      $msg = "Selected content is already in Archive state (" . $same_status_error . ")";
        drupal_set_message(t($msg), 'error');
    }
    if($country_error > 0){
      $country_msg = "This content belongs to Master content and cannot be edited. It has to be assigned to your country to allow for further editing and contextualization. (" . $country_error . ")";
        drupal_set_message(t($country_msg), 'error');
    }
    $context['message'] = $message;
    $context['results'] = $results;
  }

  /**
   * Processes each required or optional tag.
   */
  public static function offLoadsCountryProcessFinishedCallback($success, $results, $operations) {
    // The 'success' parameter means no fatal PHP errors were detected. All
    // other error management should be handled using 'results'.
    if ($success) {
      $message = \Drupal::translation()->formatPlural(
      count($results),
      'One post processed.', '@count posts processed.'
            );
    }
    else {
      $message = t('Finished with an error.');
    }
    drupal_set_message($message);
  }

}
