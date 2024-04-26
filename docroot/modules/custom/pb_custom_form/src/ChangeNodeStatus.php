<?php

namespace Drupal\pb_custom_form;

use Drupal\node\Entity\Node;

/**
 * Parses and verifies the doc comments for files.
 *
 * PHP version 5
 *
 * @category PHP
 *
 * @package PHP_CodeSniffer
 * @copyright 2006-2012 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license https://github.com/squizlabs/blob/master/licence.txt BSD Licence
 * @link http://pear.php.net/package/PHP_CodeSniffer
 */
class ChangeNodeStatus {

  /**
   * Processes each required or optional tag.
   */
  public static function offLoadCountryProcess($langcodess, &$context) {
    $message = 'Changing Status...';
    $results = [];
    foreach ($langcodess as $key => $nidss) {
      foreach ($nidss as $nid) {
        $node = Node::load($key);
        $node_lang_archive = $node->getTranslation($nid);
        $uid = \Drupal::currentUser()->id();
        $node_lang_archive->setNewRevision(TRUE);
        $node_lang_archive->revision_log = 'Content changed to “Archive” through Country Offload';
        $node_lang_archive->setRevisionCreationTime(\Drupal::time()->getRequestTime());
        $node_lang_archive->setRevisionUserId($uid);
        $node_lang_archive->setRevisionTranslationAffected(NULL);
        $node_lang_archive->save();
        $node_lang_archive->set('moderation_state', 'archive');
        $node_lang_archive->save();
        $node->save();
      }
      $results[] = $node->save();
    }
    $context['message'] = $message;
    $context['results'] = $results;
  }

  /**
   * Processes each required or optional tag.
   */
  public static function offLoadCountryProcessFinishedCallback($success, $results, $operations) {
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
    // drupal_set_message($message);
    \Drupal::messenger()->addMessage($message);
  }

}
