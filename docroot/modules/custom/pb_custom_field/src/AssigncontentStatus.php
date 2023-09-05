<?php

namespace Drupal\pb_custom_field;

use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drupal\Core\StringTranslation\StringTranslationTrait;

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
class AssigncontentStatus {

  use StringTranslationTrait;

  /**
   * Processes each required or optional tag.
   */
  public static function assignlanguage($n_language, $langoption, &$context) {
    $message = 'Performing Assign Content to Country....';
    $results = [];
    $uid = \Drupal::currentUser()->id();
    $uname = \Drupal::currentUser()->getDisplayName();
    $success_msg = 0;
    $same_status_error = 0;
    foreach ($n_language as $key => $langs) {
      $current_language = $langs[0];
      $node = node_load($key);
      if (!$node->hasTranslation($langoption)) {
        $node_lang = $node->getTranslation($current_language);
        $node_es = $node->addTranslation($langoption, $node_lang->toArray());
        $node_es->set('moderation_state', 'draft');
        $node_es->set('langcode', $langoption);
        $node_es->set('uid', $uid);
        $node_es->set('content_translation_source', $current_language);
        $node_es->set('changed', time());
        $node_es->set('created', time());
        $node_es->setNewRevision(TRUE);
        $node_es->revision_log = 'content assigned from Assign Content to Country option from ' . $current_language . ' by ' . $uname;
        $node_es->setRevisionCreationTime(\Drupal::time()->getRequestTime());
        $node_es->setRevisionUserId($uid);
        //$node_es->save();
		$results_save = $node->save();
		
		$success_msg++;
      }
      else {
        $same_status_error++;
      }
	  $results[] = 1;
    }
	
    if ($success_msg > 0) {
      $Succ_message = "Content assigned to country (" . $success_msg . ") <br/>";
      drupal_set_message(t($Succ_message), 'status');
    }
    if ($same_status_error > 0) {
      $msg = "Content already exists in country (" . $same_status_error . ")<br/>";
      drupal_set_message(t($msg), 'error');
    }
    $context['message'] = $message;
	$context['results'] = $results;
	
  }

  /**
   * Processes each required or optional tag.
   */
  public static function assignlanguageFinishedCallback($success, $results, $operations) {
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
	  drupal_set_message($message);
    }
  }

}
