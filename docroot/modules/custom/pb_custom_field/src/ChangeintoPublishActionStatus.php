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

    $grp_country_new_array = [];
    if (!empty($grps)) {
      // Collect languages from ALL groups the user belongs to.
      foreach ($grps as $grp) {
        $group = $grp->getGroup();
        if ($group && $group->hasField('field_language') && !$group->get('field_language')->isEmpty()) {
          $grp_country_language = $group->get('field_language')->getValue();
          $group_languages = array_column($grp_country_language, 'value');
          $grp_country_new_array = array_merge($grp_country_new_array, $group_languages);
        }
      }
      $grp_country_new_array = array_unique($grp_country_new_array);
    }
    foreach ($n_language as $key => $langs) {
      $draft_node = Node::load($key);
      foreach ($langs as $lang) {
        $node_lang_draft = $draft_node->getTranslation($lang);
        $current_state = $node_lang_draft->moderation_state->value;

        if ($current_state !== 'published' && empty($grps)) {
          $node_lang_draft->set('moderation_state', 'published');
          $node_lang_draft->set('uid', $uid);
          $node_lang_draft->set('content_translation_source', $lang);
          $node_lang_draft->set('changed', time());
          $node_lang_draft->set('created', time());

          $node_lang_draft->setNewRevision(TRUE);
          $node_lang_draft->revision_log = 'Content changed into Published';
          $node_lang_draft->setRevisionCreationTime(\Drupal::time()->getRequestTime());
          $node_lang_draft->setRevisionUserId($uid);
          $node_lang_draft->setRevisionTranslationAffected(NULL);
          $node_lang_draft->save();
          $success_msg++;
        }
        elseif ($current_state !== 'published' && !empty($grps)) {
          if (in_array($lang, $grp_country_new_array)) {
            $node_lang_draft->set('moderation_state', 'published');
            $node_lang_draft->set('uid', $uid);
            $node_lang_draft->set('content_translation_source', $lang);
            $node_lang_draft->set('changed', time());
            $node_lang_draft->set('created', time());

            $node_lang_draft->setNewRevision(TRUE);
            $node_lang_draft->revision_log = 'Content changed into Published';
            $node_lang_draft->setRevisionCreationTime(\Drupal::time()->getRequestTime());
            $node_lang_draft->setRevisionUserId($uid);
            $node_lang_draft->setRevisionTranslationAffected(NULL);
            $node_lang_draft->save();
            $success_msg++;
          }
          else {
            $country_error++;
          }

        }
        else {
          $same_status_error++;
        }
      }

      $results[] = $draft_node->save();
    }
    if ($success_msg > 0) {
      $succ_message = "Content changed into Published successfully (" . $success_msg . ")";
      // drupal_set_message(t($succ_message), 'status');.
      \Drupal::messenger()->addStatus($succ_message);
    }
    if ($same_status_error > 0) {
      $msg = "Selected content is already in Published state (" . $same_status_error . ")";
      // drupal_set_message(t($msg), 'error');.
      \Drupal::messenger()->addError($msg);
    }
    if ($country_error > 0) {
      $country_msg = "This content belongs to Master content and cannot be edited. It has to be assigned to your country to allow for further editing and contextualization. (" . $country_error . ")";
      // drupal_set_message(t($country_msg), 'error');.
      \Drupal::messenger()->addError($country_msg);
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
      // drupal_set_message($message);
      \Drupal::messenger()->addMessage($message);
    }

  }

}
