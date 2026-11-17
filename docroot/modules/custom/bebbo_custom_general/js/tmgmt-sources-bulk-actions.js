/**
 * @file
 * Selected-items bulk actions bar on the TMGMT sources overview.
 *
 * Reuses the admin theme's Views bulk actions bar (Drupal.ClaroBulkActions)
 * and points it at the existing row checkboxes of the sources table.
 */

(($, Drupal, once) => {
  Drupal.behaviors.bebboTmgmtSourcesBulkActions = {
    attach(context) {
      if (typeof Drupal.ClaroBulkActions !== 'function') {
        return;
      }
      once(
        'bebbo-tmgmt-bulk-actions',
        '[data-bebbo-tmgmt-bulk-actions]',
        context,
      ).forEach((bar) => {
        const bulkActions = new Drupal.ClaroBulkActions(bar);
        bulkActions.checkboxes = bulkActions.form.querySelectorAll(
          '[data-drupal-selector="edit-items"] tbody input[type="checkbox"]',
        );
        $(bulkActions.checkboxes).on('change', (event) =>
          bulkActions.rowCheckboxHandler(event),
        );
      });
    },
  };
})(jQuery, Drupal, once);
