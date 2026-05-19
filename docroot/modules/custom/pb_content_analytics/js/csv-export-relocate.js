(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.contentAnalyticsCsvRelocate = {
    attach: function () {
      var form = document.querySelector('.views-exposed-form');
      if (!form) {
        return;
      }

      var processed = once('csv-relocate', form);
      if (!processed.length) {
        return;
      }

      var feedLink = document.querySelector('.feed-icons .csv-feed a');
      if (!feedLink) {
        return;
      }

      feedLink.className = 'button button--secondary';
      feedLink.textContent = 'Export data in CSV';

      var wrapper = document.createElement('div');
      wrapper.style.marginLeft = 'auto';
      wrapper.appendChild(feedLink);
      form.appendChild(wrapper);

      var feedIcons = document.querySelector('.feed-icons');
      if (feedIcons && !feedIcons.querySelector('a')) {
        feedIcons.remove();
      }
    }
  };
})(Drupal, once);
