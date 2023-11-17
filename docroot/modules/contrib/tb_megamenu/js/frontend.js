import { TBMegaMenu } from './plugin.js';

/**
 * @file
 * Defines Javascript behaviors for MegaMenu frontend.
 */

(function (Drupal) {
  'use strict';

  Drupal.TBMegaMenu = Drupal.TBMegaMenu || {};

  const focusableSelector =
    'a:not([disabled]):not([tabindex="-1"]), button:not([disabled]):not([tabindex="-1"]), input:not([disabled]):not([tabindex="-1"]), select:not([disabled]):not([tabindex="-1"]), textarea:not([disabled]):not([tabindex="-1"]), details:not([disabled]):not([tabindex="-1"]), [tabindex]:not([disabled]):not([tabindex="-1"])';

  const throttle = function (func, timeFrame) {
    var lastTime = 0;
    return function (...args) {
      var now = new Date();
      if (now - lastTime >= timeFrame) {
        func(...args);
        lastTime = now;
      }
    };
  };

  // On load and on resize set the mobile class and get the list of top level links.
  const updateTBMenus = () => {
    document.querySelectorAll('.tbm').forEach((thisMenu) => {
      const menuId = thisMenu.getAttribute('id');
      Drupal.TBMegaMenu[menuId] = {};
      const breakpoint = parseInt(thisMenu.getAttribute('data-breakpoint'));

      if (window.matchMedia(`(max-width: ${breakpoint}px)`).matches) {
        thisMenu.classList.add('tbm--mobile');
      } else {
        thisMenu.classList.remove('tbm--mobile');
      }

      // Build the list of tabbable elements as these may change between mobile
      // and desktop.
      let focusable = document.querySelectorAll(focusableSelector);
      focusable = [...focusable];

      let topLevel = thisMenu.querySelectorAll(
        '.tbm-link.level-1, .tbm-link.level-1 + .tbm-submenu-toggle'
      );
      topLevel = [...topLevel];
      topLevel = topLevel.filter((element) => {
        // Check if the element is visible.
        return element.offsetWidth > 0 && element.offsetHeight > 0;
      });

      Drupal.TBMegaMenu['focusable'] = focusable;
      Drupal.TBMegaMenu[menuId]['topLevel'] = topLevel;
    });
  };

  const throttled = throttle(updateTBMenus, 100);

  // Run the the throttled code on load and on resize.
  ['load', 'resize'].forEach((event) => {
    window.addEventListener(event, throttled);
  });

  Drupal.TBMegaMenu.getNextPrevElement = (direction, excludeSubnav = false) => {
    // Add all the elements we want to include in our selection
    const current = document.activeElement;
    let nextElement = null;

    if (current) {
      let focusable = document.querySelectorAll(focusableSelector);
      focusable = [...focusable];

      focusable = Drupal.TBMegaMenu['focusable'].filter((element) => {
        if (excludeSubnav) {
          return (
            !element.closest('.tbm-subnav') &&
            element.offsetWidth > 0 &&
            element.offsetHeight > 0
          );
        }

        // Check if the element is visible.
        return element.offsetWidth > 0 && element.offsetHeight > 0;
      });

      const index = focusable.indexOf(current);

      if (index > -1) {
        if (direction === 'next') {
          nextElement = focusable[index + 1] || focusable[0];
        } else {
          nextElement = focusable[index - 1] || focusable[0];
        }
      }
    }

    return nextElement;
  };

  Drupal.behaviors.tbMegaMenuInit = {
    attach: (context) => {
      context.querySelectorAll('.tbm').forEach((menu) => {
        // Look for an initialized attribute so that we do not have to worry
        // about attaching once() or jQuery.once().
        if (!menu.getAttribute('data-initialized')) {
          menu.setAttribute('data-initialized', 'true');

          const tbMega = new TBMegaMenu(menu.getAttribute('id'));
          tbMega.init();
        }
      });
    },
  };

  // Add a behavior to call updateTBMenus so that anytime the DOM is updated,
  // the list of tabbable links is rebuilt.
  Drupal.behaviors.tbMegaMenuRespond = {
    attach: (context) => {
      updateTBMenus();
    },
  };
})(Drupal);
