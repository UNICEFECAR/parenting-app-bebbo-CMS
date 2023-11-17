/******/ (function(modules) { // webpackBootstrap
/******/ 	// The module cache
/******/ 	var installedModules = {};
/******/
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/
/******/ 		// Check if module is in cache
/******/ 		if(installedModules[moduleId]) {
/******/ 			return installedModules[moduleId].exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = installedModules[moduleId] = {
/******/ 			i: moduleId,
/******/ 			l: false,
/******/ 			exports: {}
/******/ 		};
/******/
/******/ 		// Execute the module function
/******/ 		modules[moduleId].call(module.exports, module, module.exports, __webpack_require__);
/******/
/******/ 		// Flag the module as loaded
/******/ 		module.l = true;
/******/
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/
/******/
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = modules;
/******/
/******/ 	// expose the module cache
/******/ 	__webpack_require__.c = installedModules;
/******/
/******/ 	// define getter function for harmony exports
/******/ 	__webpack_require__.d = function(exports, name, getter) {
/******/ 		if(!__webpack_require__.o(exports, name)) {
/******/ 			Object.defineProperty(exports, name, { enumerable: true, get: getter });
/******/ 		}
/******/ 	};
/******/
/******/ 	// define __esModule on exports
/******/ 	__webpack_require__.r = function(exports) {
/******/ 		if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 			Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 		}
/******/ 		Object.defineProperty(exports, '__esModule', { value: true });
/******/ 	};
/******/
/******/ 	// create a fake namespace object
/******/ 	// mode & 1: value is a module id, require it
/******/ 	// mode & 2: merge all properties of value into the ns
/******/ 	// mode & 4: return value when already ns object
/******/ 	// mode & 8|1: behave like require
/******/ 	__webpack_require__.t = function(value, mode) {
/******/ 		if(mode & 1) value = __webpack_require__(value);
/******/ 		if(mode & 8) return value;
/******/ 		if((mode & 4) && typeof value === 'object' && value && value.__esModule) return value;
/******/ 		var ns = Object.create(null);
/******/ 		__webpack_require__.r(ns);
/******/ 		Object.defineProperty(ns, 'default', { enumerable: true, value: value });
/******/ 		if(mode & 2 && typeof value != 'string') for(var key in value) __webpack_require__.d(ns, key, function(key) { return value[key]; }.bind(null, key));
/******/ 		return ns;
/******/ 	};
/******/
/******/ 	// getDefaultExport function for compatibility with non-harmony modules
/******/ 	__webpack_require__.n = function(module) {
/******/ 		var getter = module && module.__esModule ?
/******/ 			function getDefault() { return module['default']; } :
/******/ 			function getModuleExports() { return module; };
/******/ 		__webpack_require__.d(getter, 'a', getter);
/******/ 		return getter;
/******/ 	};
/******/
/******/ 	// Object.prototype.hasOwnProperty.call
/******/ 	__webpack_require__.o = function(object, property) { return Object.prototype.hasOwnProperty.call(object, property); };
/******/
/******/ 	// __webpack_public_path__
/******/ 	__webpack_require__.p = "";
/******/
/******/
/******/ 	// Load entry module and return exports
/******/ 	return __webpack_require__(__webpack_require__.s = "./js/frontend.js");
/******/ })
/************************************************************************/
/******/ ({

/***/ "./js/frontend.js":
/*!************************!*\
  !*** ./js/frontend.js ***!
  \************************/
/*! no exports provided */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _plugin_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./plugin.js */ "./js/plugin.js");

(function (Drupal) {
  'use strict';

  Drupal.TBMegaMenu = Drupal.TBMegaMenu || {};
  const focusableSelector = 'a:not([disabled]):not([tabindex="-1"]), button:not([disabled]):not([tabindex="-1"]), input:not([disabled]):not([tabindex="-1"]), select:not([disabled]):not([tabindex="-1"]), textarea:not([disabled]):not([tabindex="-1"]), details:not([disabled]):not([tabindex="-1"]), [tabindex]:not([disabled]):not([tabindex="-1"])';
  const throttle = function (func, timeFrame) {
    var lastTime = 0;
    return function () {
      var now = new Date();
      if (now - lastTime >= timeFrame) {
        func(...arguments);
        lastTime = now;
      }
    };
  };
  const updateTBMenus = () => {
    document.querySelectorAll('.tbm').forEach(thisMenu => {
      const menuId = thisMenu.getAttribute('id');
      Drupal.TBMegaMenu[menuId] = {};
      const breakpoint = parseInt(thisMenu.getAttribute('data-breakpoint'));
      if (window.matchMedia(`(max-width: ${breakpoint}px)`).matches) {
        thisMenu.classList.add('tbm--mobile');
      } else {
        thisMenu.classList.remove('tbm--mobile');
      }
      let focusable = document.querySelectorAll(focusableSelector);
      focusable = [...focusable];
      let topLevel = thisMenu.querySelectorAll('.tbm-link.level-1, .tbm-link.level-1 + .tbm-submenu-toggle');
      topLevel = [...topLevel];
      topLevel = topLevel.filter(element => {
        return element.offsetWidth > 0 && element.offsetHeight > 0;
      });
      Drupal.TBMegaMenu['focusable'] = focusable;
      Drupal.TBMegaMenu[menuId]['topLevel'] = topLevel;
    });
  };
  const throttled = throttle(updateTBMenus, 100);
  ['load', 'resize'].forEach(event => {
    window.addEventListener(event, throttled);
  });
  Drupal.TBMegaMenu.getNextPrevElement = function (direction) {
    let excludeSubnav = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : false;
    const current = document.activeElement;
    let nextElement = null;
    if (current) {
      let focusable = document.querySelectorAll(focusableSelector);
      focusable = [...focusable];
      focusable = Drupal.TBMegaMenu['focusable'].filter(element => {
        if (excludeSubnav) {
          return !element.closest('.tbm-subnav') && element.offsetWidth > 0 && element.offsetHeight > 0;
        }
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
    attach: context => {
      context.querySelectorAll('.tbm').forEach(menu => {
        if (!menu.getAttribute('data-initialized')) {
          menu.setAttribute('data-initialized', 'true');
          const tbMega = new _plugin_js__WEBPACK_IMPORTED_MODULE_0__["TBMegaMenu"](menu.getAttribute('id'));
          tbMega.init();
        }
      });
    }
  };
  Drupal.behaviors.tbMegaMenuRespond = {
    attach: context => {
      updateTBMenus();
    }
  };
})(Drupal);

/***/ }),

/***/ "./js/plugin.js":
/*!**********************!*\
  !*** ./js/plugin.js ***!
  \**********************/
/*! exports provided: TBMegaMenu */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "TBMegaMenu", function() { return TBMegaMenu; });
class TBMegaMenu {
  constructor(id) {
    this.id = id;
    this.navParent = document.getElementById(this.id);
    this.isTouch = window.matchMedia('(pointer: coarse)').matches;
    const menuSettings = drupalSettings['TBMegaMenu'][this.id];
    this.hasArrows = menuSettings['arrows'] === '1';
    const mm_duration = this.navParent.getAttribute('data-duration') ? parseInt(this.navParent.getAttribute('data-duration')) : 0;
    this.mm_timeout = mm_duration ? 100 + mm_duration : 500;
  }
  get isMobile() {
    return this.navParent.classList.contains('tbm--mobile');
  }
  keyDownHandler(k) {
    const _this = this;
    const menuId = this.id;
    switch (k.keyCode) {
      case 9:
        if (!_this.isMobile) {
          nav_tab(k);
        }
        break;
      case 13:
        nav_enter();
        break;
      case 27:
        nav_esc();
        break;
      case 37:
        k.preventDefault();
        nav_left(k);
        break;
      case 38:
        k.preventDefault();
        nav_up(k);
        break;
      case 39:
        k.preventDefault();
        nav_right(k);
        break;
      case 40:
        k.preventDefault();
        nav_down(k);
        break;
      default:
    }
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
    function nav_esc() {
      _this.closeMenu();
    }
    function nav_enter() {
      if (document.activeElement.classList.contains('no-link')) {
        document.activeElement.click();
      }
    }
    function nav_left(k) {
      if (nav_is_toplink()) {
        nav_prev_toplink();
      } else {
        nav_up(k);
      }
    }
    function nav_right(k) {
      if (nav_is_toplink()) {
        nav_next_toplink();
      } else {
        nav_down(k);
      }
    }
    function nav_up(k) {
      if (nav_is_toplink()) {} else {
        nav_tab(k);
      }
    }
    function nav_down(k) {
      if (nav_is_toplink()) {
        Drupal.TBMegaMenu.getNextPrevElement('next').focus();
      } else if (Drupal.TBMegaMenu.getNextPrevElement('next').closest('.tbm-item.level-1') !== document.activeElement.closest('.tbm-item.level-1')) {} else {
        nav_tab(k);
      }
    }
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
    function nav_next_toplink() {
      if (!nav_is_last_toplink()) {
        const topLevel = Drupal.TBMegaMenu[menuId]['topLevel'];
        const index = topLevel.indexOf(document.activeElement);
        if (index > -1) {
          topLevel[index + 1].focus();
        }
      } else {
        Drupal.TBMegaMenu.getNextPrevElement('next', true).focus();
      }
    }
    function nav_prev_toplink() {
      if (!nav_is_first_toplink()) {
        const topLevel = Drupal.TBMegaMenu[menuId]['topLevel'];
        const index = topLevel.indexOf(document.activeElement);
        if (index > -1) {
          topLevel[index - 1].focus();
        }
      } else {
        Drupal.TBMegaMenu.getNextPrevElement('prev', true).focus();
      }
    }
  }
  handleTouch(item) {
    const _this = this;
    const link = item.querySelector(':scope > .tbm-link-container').querySelector(':scope > .tbm-link');
    const tbitem = link.closest('.tbm-item');
    link.addEventListener('click', event => {
      if (!_this.isMobile && _this.isTouch && !_this.hasArrows) {
        if (link.classList.contains('tbm-clicked')) {
          const uri = link.getAttribute('href');
          if (uri) {
            window.location.href = uri;
          } else {
            link.classList.remove('tbm-clicked');
            _this.hideMenu(tbitem, _this.mm_timeout);
          }
        } else {
          event.preventDefault();
          const allOpen = _this.navParent.querySelectorAll('.open');
          allOpen.forEach(element => {
            if (element.contains(link)) {} else {
              element.classList.remove('open');
            }
          });
          _this.ariaCheck();
          _this.navParent.querySelectorAll('.tbm-clicked').forEach(element => {
            element.classList.remove('tbm-clicked');
          });
          link.classList.add('tbm-clicked');
          _this.showMenu(tbitem, _this.mm_timeout);
        }
      }
    });
    document.addEventListener('click', event => {
      if (!event.target.closest('.tbm') && _this.navParent.classList.contains('tbm--mobile-show')) {
        _this.closeMenu();
      }
    });
    document.addEventListener('focusin', event => {
      if (!event.target.closest('.tbm')) {
        _this.closeMenu();
      }
    });
  }
  closeMenu() {
    this.navParent.classList.remove('tbm--mobile-show');
    this.navParent.querySelector('.tbm-button').setAttribute('aria-expanded', 'false');
    this.navParent.querySelectorAll('.open').forEach(element => {
      element.classList.remove('open');
    });
    this.navParent.querySelectorAll('.tbm-clicked').forEach(element => {
      element.classList.remove('tbm-clicked');
    });
    this.ariaCheck();
  }
  ariaCheck() {
    const toggleElement = (element, value) => {
      element.querySelectorAll('.tbm-toggle, .tbm-submenu-toggle').forEach(toggle => {
        toggle.setAttribute('aria-expanded', value);
      });
    };
    this.navParent.querySelectorAll('.tbm-item').forEach(element => {
      if (element.classList.contains('tbm-group')) {
        if (!element.closest('.open')) {
          toggleElement(element, 'false');
        } else if (element.closest('.open')) {
          toggleElement(element, 'true');
        }
      } else if (element.classList.contains('tbm-item--has-dropdown') || element.classList.contains('tbm-item--has-flyout')) {
        if (!element.classList.contains('open')) {
          toggleElement(element, 'false');
        } else if (element.classList.contains('open')) {
          toggleElement(element, 'true');
        }
      } else {
        element.querySelectorAll('.tbm-toggle, .tbm-submenu-toggle').forEach(toggle => {
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
    listItem.querySelectorAll('.tbm-toggle, .tbm-submenu-toggle').forEach(element => {
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
    document.querySelectorAll('.tbm-button').forEach(element => {
      element.addEventListener('click', event => {
        if (_this.navParent.classList.contains('tbm--mobile-show')) {
          _this.closeMenu();
        } else {
          _this.navParent.classList.add('tbm--mobile-show');
          event.currentTarget.setAttribute('aria-expanded', 'true');
        }
      });
    });
    if (!this.isTouch) {
      this.navParent.querySelectorAll('.tbm-item').forEach(element => {
        element.addEventListener('mouseenter', event => {
          if (!_this.isMobile && !_this.hasArrows) {
            _this.showMenu(element, _this.mm_timeout);
          }
        });
        element.addEventListener('mouseleave', event => {
          if (!_this.isMobile && !_this.hasArrows) {
            _this.hideMenu(element, _this.mm_timeout);
          }
        });
      });
      this.navParent.querySelectorAll('.tbm-toggle').forEach(element => {
        element.addEventListener('focus', event => {
          if (!_this.isMobile && !_this.hasArrows) {
            const listItem = event.currentTarget.closest('li');
            _this.showMenu(listItem, _this.mm_timeout);
            document.addEventListener('focusin', event => {
              if (!_this.isMobile && !_this.hasArrows) {
                if (event.target !== listItem && !listItem.contains(event.target)) {
                  document.removeEventListener('focusin', event);
                  _this.hideMenu(listItem, _this.mm_timeout);
                }
              }
            });
          }
        });
      });
    }
    this.navParent.querySelectorAll('.tbm-item').forEach(item => {
      if (item.querySelector(':scope > .tbm-submenu')) {
        _this.handleTouch(item);
      }
    });
    this.navParent.querySelectorAll('.tbm-submenu-toggle, .tbm-link.no-link').forEach(toggleElement => {
      toggleElement.addEventListener('click', event => {
        if (_this.isMobile) {
          const parentItem = event.currentTarget.closest('.tbm-item');
          if (parentItem.classList.contains('open')) {
            _this.hideMenu(parentItem, _this.mm_timeout);
          } else {
            _this.showMenu(parentItem, _this.mm_timeout);
          }
        }
        if (!_this.isMobile && !(_this.isTouch && !_this.hasArrows && event.currentTarget.classList.contains('no-link'))) {
          const parentItem = event.currentTarget.closest('.tbm-item');
          if (parentItem.classList.contains('open')) {
            _this.hideMenu(parentItem, _this.mm_timeout);
            parentItem.querySelectorAll('.open').forEach(element => {
              _this.hideMenu(element, _this.mm_timeout);
            });
          } else {
            _this.showMenu(parentItem, _this.mm_timeout);
            let prevSibling = parentItem.previousElementSibling;
            while (prevSibling) {
              _this.hideMenu(prevSibling, _this.mm_timeout);
              prevSibling.querySelectorAll('.open').forEach(item => {
                _this.hideMenu(item, _this.mm_timeout);
              });
              prevSibling = prevSibling.previousElementSibling;
            }
            let nextSibling = parentItem.nextElementSibling;
            while (nextSibling) {
              _this.hideMenu(nextSibling, _this.mm_timeout);
              nextSibling.querySelectorAll('.open').forEach(item => {
                _this.hideMenu(item, _this.mm_timeout);
              });
              nextSibling = nextSibling.nextElementSibling;
            }
          }
        }
      });
    });
    this.navParent.addEventListener('keydown', this.keyDownHandler.bind(this));
  }
}

/***/ })

/******/ });
//# sourceMappingURL=frontend.js.map