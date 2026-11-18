/**
 * @file
 * Enter in a TMGMT sources filter field runs the Search button.
 *
 * Without this, the browser submits with the form's first submit button, the
 * hidden "Choose" source button, which redirects and drops the filters.
 */

((Drupal, once) => {
  Drupal.behaviors.bebboTmgmtSourcesSearch = {
    attach(context) {
      once('bebbo-tmgmt-sources-search', '.tmgmt-sources-wrapper', context).forEach(
        (wrapper) => {
          const searchButton = wrapper.querySelector(
            '[data-drupal-selector="edit-search-submit"]',
          );
          if (!searchButton) {
            return;
          }
          wrapper.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && event.target.matches('input')) {
              event.preventDefault();
              searchButton.click();
            }
          });
        },
      );
    },
  };
})(Drupal, once);
