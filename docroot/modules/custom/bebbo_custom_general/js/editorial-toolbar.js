/**
 * @file
 * Makes the editorial menu parents collapsible in the vertical toolbar.
 *
 * The parents are <nolink> menu links, which Gin renders as <span>. Core's
 * toolbar.menu.js only wraps "li > a" in a .toolbar-box and only appends its
 * toggle to that box, so those items never get one. Give them the same box
 * and toggle once core has finished, and core's own click handling does the
 * rest.
 */

(function (Drupal, once) {
  const TRAY = '#toolbar-item-toolbar-menu-editorial-menu-tray';

  /**
   * Wraps a linkless parent in a toolbar box and adds the core toggle.
   *
   * @param {HTMLElement} item
   *   The li element.
   */
  function decorate(item) {
    const label = item.querySelector(':scope > span.toolbar-icon');
    if (!label || !item.querySelector(':scope > ul.toolbar-menu')) {
      return;
    }
    const box = document.createElement('div');
    box.className = 'toolbar-box';
    item.insertBefore(box, label);
    box.appendChild(label);

    const open = item.classList.contains('open');
    // Same markup core builds for linked parents; the label is escaped
    // because the theme function interpolates it verbatim.
    const wrapper = document.createElement('div');
    wrapper.innerHTML = Drupal.theme('toolbarMenuItemToggle', {
      class: `toolbar-icon toolbar-handle${open ? ' open' : ''}`,
      action: open ? Drupal.t('Collapse') : Drupal.t('Extend'),
      text: Drupal.checkPlain(label.textContent.trim()),
    });
    box.appendChild(wrapper.firstElementChild);
  }

  Drupal.behaviors.bebboEditorialToolbar = {
    attach(context) {
      once('bebbo-editorial-toolbar', TRAY, context).forEach((tray) => {
        // Core builds its boxes and toggles when the menu subtrees resolve;
        // this callback is registered later, so it runs after that.
        Drupal.toolbar.setSubtrees.done(() => {
          tray.querySelectorAll('li').forEach(decorate);
        });
      });
    },
  };
})(Drupal, once);
