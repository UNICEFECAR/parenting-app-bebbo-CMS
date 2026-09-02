/**
 * @file
 * Toggles the admin toolbar language switcher dropdown.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.pbToolbarLanguageSwitcher = {
    attach: function (context) {
      const tabs = once(
        'pb-language-switcher',
        '.pb-language-switcher__tab',
        context
      );

      tabs.forEach(function (tab) {
        const tray = document.getElementById('toolbar-item-pb-language-switcher-tray');
        if (!tray) {
          return;
        }

        const close = function () {
          tray.classList.remove('is-active');
          tab.setAttribute('aria-expanded', 'false');
        };

        const open = function () {
          tray.classList.add('is-active');
          tab.setAttribute('aria-expanded', 'true');
        };

        tab.addEventListener('click', function (event) {
          event.preventDefault();
          event.stopPropagation();
          if (tray.classList.contains('is-active')) {
            close();
          } else {
            open();
          }
        });

        document.addEventListener('click', function (event) {
          if (!tray.contains(event.target) && event.target !== tab) {
            close();
          }
        });

        document.addEventListener('keydown', function (event) {
          if (event.key === 'Escape') {
            close();
          }
        });
      });
    }
  };
})(Drupal, once);
