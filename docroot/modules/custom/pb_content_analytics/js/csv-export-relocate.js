(function (Drupal, once) {
  'use strict';

  function addIncludeBodyCheckbox(wrapper, feedLinks) {
    var viewWrapper = wrapper.closest('.view');
    if (!viewWrapper) {
      return;
    }
    var viewClasses = viewWrapper.className || '';
    if (viewClasses.indexOf('view-country-reports') === -1) {
      return;
    }

    var label = document.createElement('label');
    label.className = 'include-body-text-label';
    label.style.display = 'flex';
    label.style.alignItems = 'center';
    label.style.gap = '4px';
    label.style.cursor = 'pointer';
    label.style.fontSize = '0.9em';

    var checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.className = 'include-body-text-checkbox';
    checkbox.style.width = '24px';
    checkbox.style.height = '24px';

    var labelText = document.createTextNode('Include body text');
    label.appendChild(checkbox);
    label.appendChild(labelText);

    wrapper.insertBefore(label, wrapper.firstChild);

    checkbox.addEventListener('change', function () {
      feedLinks.forEach(function (link) {
        var url = new URL(link.href, window.location.origin);
        if (checkbox.checked) {
          url.searchParams.set('include_body', '1');
        }
        else {
          url.searchParams.delete('include_body');
        }
        link.href = url.toString();
      });
    });
  }

  function styleLinks(feedLinks, targetContainer) {
    var wrapper = document.createElement('div');
    wrapper.className = 'csv-export-buttons';
    wrapper.style.marginLeft = 'auto';
    wrapper.style.display = 'flex';
    wrapper.style.gap = '8px';
    wrapper.style.alignItems = 'center';

    feedLinks.forEach(function (feedLink) {
      feedLink.className = 'button button--secondary';
      if (!feedLink.textContent.trim() || feedLink.textContent.trim() === feedLink.href) {
        feedLink.textContent = 'Export data in CSV';
      }
      wrapper.appendChild(feedLink);
    });

    targetContainer.appendChild(wrapper);
    addIncludeBodyCheckbox(wrapper, feedLinks);
  }

  function cleanupEmptyFeedIcons(scope) {
    scope.querySelectorAll('.feed-icons').forEach(function (container) {
      if (!container.querySelector('a')) {
        container.remove();
      }
    });
  }

  Drupal.behaviors.contentAnalyticsCsvRelocate = {
    attach: function (context) {
      var forms = once('csv-relocate', '.views-exposed-form', context);

      forms.forEach(function (form) {
        var viewWrapper = form.closest('.view') || form.parentElement;
        var feedLinks = viewWrapper
          ? viewWrapper.querySelectorAll('.feed-icons .csv-feed a')
          : document.querySelectorAll('.feed-icons .csv-feed a');

        if (!feedLinks.length) {
          return;
        }

        styleLinks(feedLinks, form);
        cleanupEmptyFeedIcons(viewWrapper || document);
      });

      if (forms.length) {
        return;
      }

      var feedContainers = once('csv-relocate-nf', '.feed-icons', context);
      feedContainers.forEach(function (feedContainer) {
        var feedLinks = feedContainer.querySelectorAll('.csv-feed a');
        if (!feedLinks.length) {
          return;
        }

        var viewWrapper = feedContainer.closest('.view');
        var viewHeader = viewWrapper
          ? viewWrapper.querySelector('.view-header')
          : NULL;

        if (!viewHeader && viewWrapper) {
          viewHeader = document.createElement('div');
          viewHeader.className = 'view-header';
          viewWrapper.insertBefore(viewHeader, viewWrapper.firstChild);
        }

        styleLinks(feedLinks, viewHeader || feedContainer.parentElement);
        cleanupEmptyFeedIcons(viewWrapper || feedContainer.parentElement);
      });
    }
  };
})(Drupal, once);
