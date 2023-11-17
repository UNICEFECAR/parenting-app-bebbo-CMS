export class TBMegaMenu {
  constructor(id) {
    this.id = id;
    this.navParent = document.getElementById(this.id);
    this.isTouch = window.matchMedia('(pointer: coarse)').matches;

    const menuSettings = drupalSettings['TBMegaMenu'][this.id];
    this.hasArrows = menuSettings['arrows'] === '1';

    const mm_duration = this.navParent.getAttribute('data-duration')
      ? parseInt(this.navParent.getAttribute('data-duration'))
      : 0;

    this.mm_timeout = mm_duration ? 100 + mm_duration : 500;
  }

  // We have to define this as a getter because it can change as the browser resizes.
  get isMobile() {
    return this.navParent.classList.contains('tbm--mobile');
  }

  keyDownHandler(k) {
    const _this = this;
    const menuId = this.id;

    // Determine Key
    switch (k.keyCode) {
      // TAB
      case 9:
        // On mobile, we can follow the natural tab order.
        if (!_this.isMobile) {
          nav_tab(k);
        }
        break;

      // ENTER
      case 13:
        nav_enter();
        break;

      // ESC
      case 27:
        nav_esc();
        break;

      // LEFT
      case 37:
        k.preventDefault();
        nav_left(k);
        break;

      // UP
      case 38:
        k.preventDefault();
        nav_up(k);
        break;

      // RIGHT
      case 39:
        k.preventDefault();
        nav_right(k);
        break;

      // DOWN
      case 40:
        k.preventDefault();
        nav_down(k);
        break;

      // Else
      default:
      // Do nothing
    }

    /* Keypress Functions */
    // Tab
    function nav_tab(k) {
      k.preventDefault();

      if (nav_is_toplink()) {
        if (k.shiftKey || k.keyCode === 38 || k.keyCode === 37) {
          nav_prev_toplink();
        } else {
          nav_next_toplink();
        }
      } else {
        if (k.shiftKey || k.keyCode === 38 || k.keyCode === 37) {
          Drupal.TBMegaMenu.getNextPrevElement('prev').focus();
        } else {
          Drupal.TBMegaMenu.getNextPrevElement('next').focus();
        }
      }
    }

    // Escape
    function nav_esc() {
      _this.closeMenu();
    }

    // Enter
    function nav_enter() {
      if (document.activeElement.classList.contains('no-link')) {
        document.activeElement.click();
      }
    }

    // Left
    function nav_left(k) {
      if (nav_is_toplink()) {
        nav_prev_toplink();
      } else {
        // TODO/NICE TO HAVE - Go to previous column
        nav_up(k);
      }
    }

    // Right
    function nav_right(k) {
      if (nav_is_toplink()) {
        nav_next_toplink();
      } else {
        // TODO/NICE TO HAVE - Go to previous column
        nav_down(k);
      }
    }

    // Up
    function nav_up(k) {
      if (nav_is_toplink()) {
        // Do nothing.
      } else {
        nav_tab(k);
      }
    }

    // Down
    function nav_down(k) {
      if (nav_is_toplink()) {
        Drupal.TBMegaMenu.getNextPrevElement('next').focus();
      } else if (
        // If the next element takes the user out of this top level, then do nothing.
        Drupal.TBMegaMenu.getNextPrevElement('next').closest(
          '.tbm-item.level-1',
        ) !== document.activeElement.closest('.tbm-item.level-1')
      ) {
        // Do nothing.
      } else {
        nav_tab(k);
      }
    }

    /* Helper Functions */
    // Determine Link Level
    function nav_is_toplink() {
      const topLevel = Drupal.TBMegaMenu[menuId]['topLevel'];
      return topLevel.indexOf(document.activeElement) > -1;
    }

    function nav_is_last_toplink() {
      const topLevel = Drupal.TBMegaMenu[menuId]['topLevel'];
      return topLevel.indexOf(document.activeElement) === topLevel.length - 1;
    }

    function nav_is_first_toplink() {
      const topLevel = Drupal.TBMegaMenu[menuId]['topLevel'];
      return topLevel.indexOf(document.activeElement) === 0;
    }

    // Next Toplink
    function nav_next_toplink() {
      if (!nav_is_last_toplink()) {
        const topLevel = Drupal.TBMegaMenu[menuId]['topLevel'];
        const index = topLevel.indexOf(document.activeElement);

        if (index > -1) {
          topLevel[index + 1].focus();
        }
      } else {
        // Focus on the next element.
        Drupal.TBMegaMenu.getNextPrevElement('next', true).focus();
      }
    }

    // Previous Toplink
    function nav_prev_toplink() {
      if (!nav_is_first_toplink()) {
        const topLevel = Drupal.TBMegaMenu[menuId]['topLevel'];
        const index = topLevel.indexOf(document.activeElement);

        if (index > -1) {
          topLevel[index - 1].focus();
        }
      } else {
        // Focus on the previous element.
        Drupal.TBMegaMenu.getNextPrevElement('prev', true).focus();
      }
    }
  }

  // Define actions for touch devices.
  handleTouch(item) {
    const _this = this;
    const link = item
      .querySelector(':scope > .tbm-link-container')
      .querySelector(':scope > .tbm-link');
    const tbitem = link.closest('.tbm-item');

    link.addEventListener('click', (event) => {
      if (!_this.isMobile && _this.isTouch && !_this.hasArrows) {
        // If the menu link has already been clicked once...
        if (link.classList.contains('tbm-clicked')) {
          const uri = link.getAttribute('href');

          // If the menu link has a URI, go to the link.
          // <nolink> menu items will not have a URI.
          if (uri) {
            window.location.href = uri;
          } else {
            link.classList.remove('tbm-clicked');
            _this.hideMenu(tbitem, _this.mm_timeout);
          }
        } else {
          event.preventDefault();

          // Hide any already open menus which are not parents of the
          // currently clicked menu item.
          // const openParents = item.parents('.open');
          const allOpen = _this.navParent.querySelectorAll('.open');

          // Loop through all open items and check to see if they are
          // parents of the clicked item.
          allOpen.forEach((element) => {
            if (element.contains(link)) {
              // do nothing
            } else {
              element.classList.remove('open');
            }
          });

          // Apply aria attributes.
          _this.ariaCheck();

          // Remove any existing tmb-clicked classes.
          _this.navParent
            .querySelectorAll('.tbm-clicked')
            .forEach((element) => {
              element.classList.remove('tbm-clicked');
            });

          // Open the submenu and apply the tbm-clicked class.
          link.classList.add('tbm-clicked');
          _this.showMenu(tbitem, _this.mm_timeout);
        }
      }
    });

    // Anytime there's a click outside the menu, close the menu.
    document.addEventListener('click', (event) => {
      if (
        !event.target.closest('.tbm') &&
        _this.navParent.classList.contains('tbm--mobile-show')
      ) {
        _this.closeMenu();
      }
    });

    // When focus lands outside the menu close the menu.
    document.addEventListener('focusin', (event) => {
      if (!event.target.closest('.tbm')) {
        _this.closeMenu();
      }
    });
  }

  // Close Mega Menu
  closeMenu() {
    this.navParent.classList.remove('tbm--mobile-show');
    this.navParent
      .querySelector('.tbm-button')
      .setAttribute('aria-expanded', 'false');
    this.navParent.querySelectorAll('.open').forEach((element) => {
      element.classList.remove('open');
    });
    this.navParent.querySelectorAll('.tbm-clicked').forEach((element) => {
      element.classList.remove('tbm-clicked');
    });
    this.ariaCheck();
  }

  ariaCheck() {
    const toggleElement = (element, value) => {
      element
        .querySelectorAll('.tbm-toggle, .tbm-submenu-toggle')
        .forEach((toggle) => {
          toggle.setAttribute('aria-expanded', value);
        });
    };

    this.navParent.querySelectorAll('.tbm-item').forEach((element) => {
      if (element.classList.contains('tbm-group')) {
        // Mega menu item has mega class (it's a true mega menu)
        if (!element.closest('.open')) {
          // Mega menu item has mega class and its ancestor is closed, so apply appropriate ARIA attributes
          toggleElement(element, 'false');
        } else if (element.closest('.open')) {
          // Mega menu item has mega class and its ancestor is open, so apply appropriate ARIA attributes
          toggleElement(element, 'true');
        }
      } else if (
        element.classList.contains('tbm-item--has-dropdown') ||
        element.classList.contains('tbm-item--has-flyout')
      ) {
        // Mega menu item has dropdown (it's a flyout menu)
        if (!element.classList.contains('open')) {
          // Mega menu item has dropdown class and is closed, so apply appropriate ARIA attributes
          toggleElement(element, 'false');
        } else if (element.classList.contains('open')) {
          // Mega menu item has dropdown class and is open, so apply appropriate ARIA attributes
          toggleElement(element, 'true');
        }
      } else {
        // Mega menu item is neither a mega or dropdown class, so remove ARIA attributes (it doesn't have children)
        element
          .querySelectorAll('.tbm-toggle, .tbm-submenu-toggle')
          .forEach((toggle) => {
            toggle.removeAttribute('aria-expanded');
          });
      }
    });
  }

  showMenu(listItem, mm_timeout) {
    const _this = this;

    if (listItem.classList.contains('level-1')) {
      listItem.classList.add('animating');
      clearTimeout(listItem.animatingTimeout);
      listItem.animatingTimeout = setTimeout(function () {
        listItem.classList.remove('animating');
      }, mm_timeout);
      clearTimeout(listItem.hoverTimeout);
      listItem.hoverTimeout = setTimeout(function () {
        listItem.classList.add('open');
        _this.ariaCheck();
      }, 100);
    } else {
      clearTimeout(listItem.hoverTimeout);
      listItem.hoverTimeout = setTimeout(function () {
        listItem.classList.add('open');
        _this.ariaCheck();
      }, 100);
    }
  }

  hideMenu(listItem, mm_timeout) {
    const _this = this;

    listItem
      .querySelectorAll('.tbm-toggle, .tbm-submenu-toggle')
      .forEach((element) => {
        element.setAttribute('aria-expanded', false);
      });

    if (listItem.classList.contains('level-1')) {
      listItem.classList.add('animating');
      clearTimeout(listItem.animatingTimeout);
      listItem.animatingTimeout = setTimeout(function () {
        listItem.classList.remove('animating');
      }, mm_timeout);
      clearTimeout(listItem.hoverTimeout);
      listItem.hoverTimeout = setTimeout(function () {
        listItem.classList.remove('open');
        _this.ariaCheck();
      }, 100);
    } else {
      clearTimeout(listItem.hoverTimeout);
      listItem.hoverTimeout = setTimeout(function () {
        listItem.classList.remove('open');
        _this.ariaCheck();
      }, 100);
    }
  }

  init() {
    const _this = this;

    // Open and close the menu when the hamburger is clicked.
    document.querySelectorAll('.tbm-button').forEach((element) => {
      element.addEventListener('click', (event) => {
        // If the menu is currently open, collapse all open dropdowns before
        // hiding the menu.
        if (_this.navParent.classList.contains('tbm--mobile-show')) {
          _this.closeMenu();
        } else {
          // Toggle the menu visibility.
          _this.navParent.classList.add('tbm--mobile-show');
          event.currentTarget.setAttribute('aria-expanded', 'true');
        }
      });
    });

    if (!this.isTouch) {
      // Show dropdowns and flyouts on hover.
      this.navParent.querySelectorAll('.tbm-item').forEach((element) => {
        element.addEventListener('mouseenter', (event) => {
          if (!_this.isMobile && !_this.hasArrows) {
            _this.showMenu(element, _this.mm_timeout);
          }
        });

        element.addEventListener('mouseleave', (event) => {
          if (!_this.isMobile && !_this.hasArrows) {
            _this.hideMenu(element, _this.mm_timeout);
          }
        });
      });

      // Show dropdwons and flyouts on focus.
      this.navParent.querySelectorAll('.tbm-toggle').forEach((element) => {
        element.addEventListener('focus', (event) => {
          if (!_this.isMobile && !_this.hasArrows) {
            const listItem = event.currentTarget.closest('li');
            _this.showMenu(listItem, _this.mm_timeout);

            // If the focus moves outside of the subMenu, close it.
            document.addEventListener('focusin', (event) => {
              if (!_this.isMobile && !_this.hasArrows) {
                if (
                  event.target !== listItem &&
                  !listItem.contains(event.target)
                ) {
                  document.removeEventListener('focusin', event);
                  _this.hideMenu(listItem, _this.mm_timeout);
                }
              }
            });
          }
        });
      });
    }

    // Add touch functionality.
    this.navParent.querySelectorAll('.tbm-item').forEach((item) => {
      if (item.querySelector(':scope > .tbm-submenu')) {
        _this.handleTouch(item);
      }
    });

    // Toggle submenus.
    this.navParent
      .querySelectorAll('.tbm-submenu-toggle, .tbm-link.no-link')
      .forEach((toggleElement) => {
        toggleElement.addEventListener('click', (event) => {
          if (_this.isMobile) {
            const parentItem = event.currentTarget.closest('.tbm-item');

            if (parentItem.classList.contains('open')) {
              _this.hideMenu(parentItem, _this.mm_timeout);
            } else {
              _this.showMenu(parentItem, _this.mm_timeout);
            }
          }

          // Do not add a click listener if we are on a touch device with no
          // arrows and the element is a no-link element. In that case, we
          // want to use touch menu handler.
          if (
            !_this.isMobile &&
            !(
              _this.isTouch &&
              !_this.hasArrows &&
              event.currentTarget.classList.contains('no-link')
            )
          ) {
            const parentItem = event.currentTarget.closest('.tbm-item');

            if (parentItem.classList.contains('open')) {
              _this.hideMenu(parentItem, _this.mm_timeout);

              // Hide any children.
              parentItem.querySelectorAll('.open').forEach((element) => {
                _this.hideMenu(element, _this.mm_timeout);
              });
            } else {
              _this.showMenu(parentItem, _this.mm_timeout);

              // Find any siblings and close them.
              let prevSibling = parentItem.previousElementSibling;
              while (prevSibling) {
                _this.hideMenu(prevSibling, _this.mm_timeout);

                // Hide any children.
                prevSibling.querySelectorAll('.open').forEach((item) => {
                  _this.hideMenu(item, _this.mm_timeout);
                });

                prevSibling = prevSibling.previousElementSibling;
              }

              let nextSibling = parentItem.nextElementSibling;
              while (nextSibling) {
                _this.hideMenu(nextSibling, _this.mm_timeout);

                // Hide any children.
                nextSibling.querySelectorAll('.open').forEach((item) => {
                  _this.hideMenu(item, _this.mm_timeout);
                });

                nextSibling = nextSibling.nextElementSibling;
              }
            }
          }
        });
      });

    // Add keyboard listeners.
    this.navParent.addEventListener('keydown', this.keyDownHandler.bind(this));
  }
}
