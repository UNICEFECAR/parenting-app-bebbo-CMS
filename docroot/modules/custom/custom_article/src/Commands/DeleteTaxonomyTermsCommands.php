<?php

namespace Drupal\custom_article\Commands;

use Drush\Commands\DrushCommands;
use Drupal\taxonomy\Entity\Term;

/**
 * A Drush commandfile.
 */
class DeleteTaxonomyTermsCommands extends DrushCommands {

  /**
   * Delete specific taxonomy terms by TID.
   *
   * @command custom_article:delete-terms
   * @aliases dlt
   * @usage custom_article:delete-terms
   *   Deletes the taxonomy terms with the specified TIDs.
   */
  public function deleteTerms() {
  
    // List of TIDs to delete.
    $tids = [731];

    foreach ($tids as $tid) {
      $term = Term::load($tid);
      if ($term) {
        $term->delete();
        $this->output()->writeln("Deleted taxonomy term with TID: {$tid}");
      }
      else {
        $this->output()->writeln("Taxonomy term with TID: {$tid} does not exist."); 
      }
    }
  }
}
