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
class ChangeintoPublishActionStatus {

  /**
   * Processes each required or optional tag.
   */
  public static function offLoadCountryProcessd($n_language, $all_nids, &$context) {
    $message = 'Changing Status...';
    $results = [];
    $uid = \Drupal::currentUser()->id();
    $user = User::load($uid);
    $grp_membership_service = \Drupal::service('group.membership_loader');
    $grps = $grp_membership_service->loadByUser($user);
     
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

      if($current_state !== 'published' && empty($grps)){
        $node_lang_draft->set('moderation_state', 'published');
        $node_lang_draft->set('uid', $uid);
        $node_lang_draft->set('content_translation_source', $lang);
        $node_lang_draft->set('changed', time());
        $node_lang_draft->set('created', time());

        $node_lang_draft->setNewRevision(TRUE);
        $node_lang_draft->revision_log = 'Content changed into Published';
        $node_lang_draft->setRevisionCreationTime(REQUEST_TIME);
        $node_lang_draft->setRevisionUserId($uid);
        $node_lang_draft->setRevisionTranslationAffected(NULL);
        $node_lang_draft->save();
      }elseif($current_state !== 'published' && !empty($grps)){
        if (in_array($lang, $grp_country_new_array)) {
        $node_lang_draft->set('moderation_state', 'published');
        $node_lang_draft->set('uid', $uid);
        $node_lang_draft->set('content_translation_source', $lang);
        $node_lang_draft->set('changed', time());
        $node_lang_draft->set('created', time());

        $node_lang_draft->setNewRevision(TRUE);
        $node_lang_draft->revision_log = 'Content changed into Published';
        $node_lang_draft->setRevisionCreationTime(REQUEST_TIME);
        $node_lang_draft->setRevisionUserId($uid);
        $node_lang_draft->setRevisionTranslationAffected(NULL);
        $node_lang_draft->save();
      }else{
         $msg = "This content belongs to Master content and cannot be edited. It has to be assigned to your country to allow for further editing and contextualization.";
        drupal_set_message(t($msg), 'error');
      }


      }else{
        $msg = "Selected Content Is Allready In Published State";
        drupal_set_message(t($msg), 'error');

      }
    }
   
      $results[] = $draft_node->save();
    }
      // die();
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
