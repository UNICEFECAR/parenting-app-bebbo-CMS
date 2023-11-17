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
/******/ 	return __webpack_require__(__webpack_require__.s = "./js/toolbox.js");
/******/ })
/************************************************************************/
/******/ ({

/***/ "./js/toolbox.js":
/*!***********************!*\
  !*** ./js/toolbox.js ***!
  \***********************/
/*! no static exports found */
/***/ (function(module, exports) {

Drupal.TBMegaMenu = Drupal.TBMegaMenu || {};
(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.tbMegaMenuBackendAction = {
    attach: function (context) {
      $('select[name="tbm-animation"]').change(function () {
        $('#tbm-duration-wrapper').css({
          display: $(this).val() == 'none' ? 'none' : 'inline-block'
        });
        $('#tbm-delay-wrapper').css({
          display: $(this).val() == 'none' ? 'none' : 'inline-block'
        });
      });
      $('.tbm-column-inner .close').click(function () {
        $(this).parent().html('');
      });
      if (drupalSettings.TBMegaMenu.menu_name !== undefined) {
        $('#tbm-admin-mm-container').megamenuAdmin({
          menu_name: drupalSettings.TBMegaMenu.menu_name
        });
      }
    }
  };
  var currentSelected = null,
    megamenu,
    nav_items,
    nav_subs,
    nav_cols,
    nav_all;
  var modalTimeout;
  Drupal.TBMegaMenu.lockedAjax = false;
  Drupal.TBMegaMenu.lockAjax = function () {
    Drupal.TBMegaMenu.lockedAjax = true;
  };
  Drupal.TBMegaMenu.isLockedAjax = function () {
    return Drupal.TBMegaMenu.lockedAjax;
  };
  Drupal.TBMegaMenu.releaseAjax = function () {
    Drupal.TBMegaMenu.lockedAjax = false;
  };
  $.fn.megamenuAdmin = function (options) {
    var defaultOptions = {};
    var options = $.extend(defaultOptions, options);
    megamenu = $(this).find('.tbm');
    nav_items = megamenu.find('ul[class*="level"]>li>:first-child');
    nav_subs = megamenu.find('.tbm-item-child');
    nav_cols = megamenu.find('[class*="span"]');
    nav_all = nav_items.add(nav_subs).add(nav_cols);
    nav_items.each(function () {
      var a = $(this);
      var liitem = a.closest('li');
      if (liitem.attr('data-hidesub') == 1) {
        var sub = liitem.find('.tbm-item-child:first');
        sub.css('display', 'none');
        a.removeClass('tbm-toggle').attr('data-toggle', '');
        liitem.removeClass('tbm-item--has-dropdown tbm-item--has-flyout');
      }
    });
    hide_toolbox(true);
    bindEvents(nav_all);
    $('.toolbox-action, .toolbox-toggle, .toolbox-input').unbind('focus blur click change keydown');
    $('.tbm-admin-mm-row').click(function (event) {
      event.stopPropagation();
    });
    $(document.body).click(function (event) {
      hide_toolbox(true);
    });
    $('.back-megamenu-toolbox').click(function (event) {
      hide_toolbox(true);
    });
    $('.toolbox-action').click(function (event) {
      var action = $(this).attr('data-action');
      if (action) {
        actions.datas = $(this).data();
        actions[action](options);
      }
      event.stopPropagation();
      return false;
    });
    $('.toolbox-toggle').change(function (event) {
      var action = $(this).attr('data-action');
      if (action) {
        actions.datas = $(this).data();
        actions[action](options);
      }
      event.stopPropagation();
      return false;
    });
    $('.toolbox-input').bind('focus blur click', function (event) {
      event.stopPropagation();
      return false;
    });
    $('.toolbox-input').bind('keydown', function (event) {
      if (event.keyCode == '13') {
        apply_toolbox(this);
        event.preventDefault();
      }
    });
    $('.toolbox-input').change(function (event) {
      apply_toolbox(this);
      event.stopPropagation();
      return false;
    });
    return this;
  };
  var actions = {};
  actions.data = {};
  actions.toggleSub = function () {
    if (!currentSelected) {
      return;
    }
    var liitem = currentSelected.closest('li'),
      sub = liitem.find('.tbm-item-child:first');
    if (parseInt(liitem.attr('data-group'))) {
      return;
    }
    if (sub.length == 0 || sub.css('display') == 'none') {
      if (sub.length == 0) {
        var column = ++drupalSettings.TBMegaMenu.TBElementsCounter.column;
        sub = $('<div class="tbm-submenu tbm-item-child"><div class="tbm-row"><div id=tbm-column-' + column + ' class="span12" data-width="12"><div class="tbm-column-inner"></div></div></div></div>').appendTo(liitem);
        bindEvents(sub.find('[class*="span"]'));
      } else {
        sub.css('display', '');
        liitem.attr('data-hidesub', 0);
      }
      liitem.attr('data-group', 0);
      currentSelected.addClass('tbm-toggle').attr('data-toggle', 'tbm-item--has-dropdown');
      liitem.addClass(liitem.attr('data-level') == 1 ? 'tbm-item--has-dropdown' : 'tbm-item--has-flyout');
      bindEvents(sub);
    } else {
      unbindEvents(sub);
      if (liitem.find('ul.level-' + liitem.attr('data-level')).length > 0) {
        sub.css('display', 'none');
        liitem.attr('data-hidesub', 1);
      } else {
        sub.remove();
      }
      liitem.attr('data-group', 0);
      currentSelected.removeClass('tbm-toggle').attr('data-toggle', '');
      liitem.removeClass('tbm-item--has-dropdown tbm-item--has-flyout');
    }
    update_toolbox();
  };
  actions.toggleHideMobileMenu = function () {
    var toggle = $('.toolitem-hide-mobile-menu');
    toggle.find('label').removeClass('active btn-success btn-danger btn-primary');
    if (parseInt(toggle.attr('data-hide-mobile-menu'))) {
      update_toggle(toggle, 0);
      toggle.attr('data-hide-mobile-menu', 0);
    } else {
      update_toggle(toggle, 1);
      toggle.attr('data-hide-mobile-menu', 1);
    }
  };
  actions.toggleAutoArrow = function () {
    var toggle = $('.toolitem-auto-arrow');
    toggle.find('label').removeClass('active btn-success btn-danger btn-primary');
    if (parseInt(toggle.attr('data-auto-arrow'))) {
      update_toggle(toggle, 0);
      toggle.attr('data-auto-arrow', 0);
    } else {
      update_toggle(toggle, 1);
      toggle.attr('data-auto-arrow', 1);
    }
  };
  actions.toggleAlwayShowSubmenu = function () {
    var toggle = $('.toolitem-always-show-submenu');
    toggle.find('label').removeClass('active btn-success btn-danger btn-primary');
    if (parseInt(toggle.attr('data-always-show-submenu'))) {
      update_toggle(toggle, 0);
      toggle.attr('data-always-show-submenu', 0);
    } else {
      update_toggle(toggle, 1);
      toggle.attr('data-always-show-submenu', 1);
    }
  };
  actions.showBlockTitle = function () {
    if (!currentSelected) {
      return;
    }
    var toggle = $('.toolcol-showblocktitle');
    toggle.find('label').removeClass('active btn-success btn-danger btn-primary');
    if (parseInt(currentSelected.attr('data-showblocktitle'))) {
      update_toggle(toggle, 0);
      currentSelected.attr('data-showblocktitle', 0);
    } else {
      update_toggle(toggle, 1);
      currentSelected.attr('data-showblocktitle', 1);
    }
    if ($('#tbm-block-wrapper select[name="toolcol-block"]').val() != '') {
      var value = $('#tbm-block-wrapper select[name="toolcol-block"]').val();
      $('#tbm-admin-mm-tb #toolbox-loading').show();
      callAjax({
        action: 'load_block',
        block_id: value,
        id: currentSelected.attr('id'),
        showblocktitle: parseInt(currentSelected.attr('data-showblocktitle'))
      });
    }
  };
  actions.toggleGroup = function () {
    if (!currentSelected) {
      return;
    }
    var liitem = currentSelected.parent();
    var sub = liitem.find('.tbm-item-child:first');
    if (liitem.attr('data-level') == 1) {
      return;
    }
    if (parseInt(liitem.attr('data-group'))) {
      liitem.attr('data-group', 0);
      liitem.removeClass('tbm-group').addClass('tbm-item--has-flyout');
      currentSelected.addClass('tbm-toggle').attr('data-toggle', 'tbm-item--has-dropdown');
      sub.removeClass('tbm-group-container').addClass('tbm-submenu');
      sub.css('width', sub.attr('data-width'));
      rebindEvents(sub);
    } else {
      currentSelected.removeClass('tbm-toggle').attr('data-toggle', '');
      liitem.attr('data-group', 1);
      liitem.removeClass('tbm-item--has-flyout').addClass('tbm-group');
      sub.removeClass('tbm-submenu').addClass('tbm-group-container');
      sub.css('width', '');
      rebindEvents(sub);
    }
    update_toolbox();
  };
  actions.hideWhenCollapse = function () {
    if (!currentSelected) {
      return;
    }
    var type = toolbox_type();
    if (type == 'sub') {
      var liitem = currentSelected.closest('li');
      if (parseInt(liitem.attr('data-hidewcol'))) {
        liitem.attr('data-hidewcol', 0);
        liitem.removeClass('sub-hidden-collapse');
      } else {
        liitem.attr('data-hidewcol', 1);
        liitem.addClass('sub-hidden-collapse');
      }
    } else if (type == 'col') {
      if (parseInt(currentSelected.attr('data-hidewcol'))) {
        currentSelected.attr('data-hidewcol', 0);
        currentSelected.removeClass('hidden-collapse');
      } else {
        currentSelected.attr('data-hidewcol', 1);
        currentSelected.addClass('hidden-collapse');
      }
    }
    update_toolbox();
  };
  actions.alignment = function () {
    var liitem = currentSelected.closest('li');
    liitem.removeClass('tbm-left tbm-center tbm-right tbm-justify').addClass('tbm-' + actions.datas.align);
    if (actions.datas.align == 'justify') {
      currentSelected.addClass('span12');
      currentSelected.css('width', '');
    } else {
      currentSelected.removeClass('span12');
      if (currentSelected.attr('data-width')) {
        currentSelected.css('width', currentSelected.attr('data-width'));
      }
    }
    liitem.attr('data-alignsub', actions.datas.align);
    update_toolbox();
  };
  actions.moveItemsLeft = function () {
    if (!currentSelected) {
      return;
    }
    var $item = currentSelected.closest('li'),
      $liparent = $item.parent().closest('li'),
      level = $liparent.attr('data-level'),
      $col = $item.closest('[class*="span"]'),
      $items = $col.find('ul:first > li'),
      itemidx = $items.index($item),
      $moveitems = $items.slice(0, itemidx + 1),
      itemleft = $items.length - $moveitems.length,
      $rows = $col.parent().parent().children('[class*="row"]'),
      $cols = $rows.children('[class*="span"]').filter(function () {
        return !$(this).attr('data-block');
      }),
      colidx = $cols.index($col);
    if (!$liparent.length) {
      return;
    }
    if (colidx == 0) {
      var oldSelected = currentSelected;
      currentSelected = $col;
      actions.datas.addfirst = true;
      actions.addColumn();
      $cols = $rows.children('[class*="span"]').filter(function () {
        return !$(this).attr('data-block');
      });
      currentSelected = oldSelected;
      colidx++;
    }
    var $tocol = $($cols[colidx - 1]);
    var $ul = $tocol.find('ul:first');
    if (!$ul.length) {
      $ul = $('<ul class="level' + level + ' tbm-subnav">').appendTo($tocol.children('.tbm-column-inner'));
    }
    $moveitems.appendTo($ul);
    if (itemleft == 0) {
      $col.find('ul:first').remove();
    }
    update_toolbox();
  };
  actions.moveItemsRight = function () {
    if (!currentSelected) {
      return;
    }
    var $item = currentSelected.closest('li'),
      $liparent = $item.parent().closest('li'),
      level = $liparent.attr('data-level'),
      $col = $item.closest('[class*="span"]'),
      $items = $col.find('ul:first > li'),
      itemidx = $items.index($item),
      $moveitems = $items.slice(itemidx),
      itemleft = $items.length - $moveitems.length,
      $rows = $col.parent().parent().children('[class*="row"]'),
      $cols = $rows.children('[class*="span"]').filter(function () {
        return $(this).children('.tbm-column-inner').children('.tbm-block').length == 0;
      });
    var colidx = $cols.index($col);
    if (!$liparent.length) {
      return;
    }
    if (colidx == $cols.length - 1) {
      var oldSelected = currentSelected;
      currentSelected = $col;
      actions.datas.addfirst = false;
      actions.addColumn();
      $cols = $rows.children('[class*="span"]').filter(function () {
        return $(this).children('.tbm-column-inner').children('.tbm-block').length == 0;
      }), currentSelected = oldSelected;
    }
    var $tocol = $($cols[colidx + 1]);
    var $ul = $tocol.find('.tbm-column-inner ul.tbm-subnav:first');
    if (!$ul.length) {
      $ul = $('<ul class="level' + level + ' tbm-subnav">').appendTo($tocol.children('.tbm-column-inner'));
    }
    $moveitems.prependTo($ul);
    if (itemleft == 0) {
      $col.find('ul:first').remove();
    }
    show_toolbox(currentSelected);
  };
  actions.addRow = function () {
    if (!currentSelected) {
      return;
    }
    var column = ++drupalSettings.TBMegaMenu.TBElementsCounter.column;
    var $row = $('<div class="tbm-row"><div id=tbm-column-' + column + ' class="span12"><div class="tbm-column-inner"></div></div></div>').appendTo(currentSelected.find('[class*="row"]:first').parent()),
      $col = $row.children();
    bindEvents($col);
    currentSelected = null;
    show_toolbox($col);
  };
  actions.rowUp = function () {
    if (!currentSelected) {
      return;
    }
    var $row = $(currentSelected.closest('.tbm-row'));
    var $rows = $row.parent();
    var $prevRow = $row.prev();
    if ($prevRow.length == 0) {
      return;
    }
    var trow = $row.clone();
    var trow1 = $prevRow.clone();
    $row.replaceWith(trow1);
    $prevRow.replaceWith(trow);
    megamenu = $('#tbm-admin-mm-container').find('.tbm');
    nav_items = megamenu.find('ul[class*="level"]>li>:first-child');
    nav_subs = megamenu.find('.tbm-item-child');
    nav_cols = megamenu.find('[class*="span"]');
    nav_all = nav_items.add(nav_subs).add(nav_cols);
    bindEvents(nav_all);
    currentSelected = $rows.find('.selected');
  };
  actions.rowDown = function () {
    if (!currentSelected) {
      return;
    }
    var $row = $(currentSelected.closest('.tbm-row'));
    var $rows = $row.parent();
    var $nextRow = $row.next();
    if ($nextRow.length == 0) {
      return;
    }
    var trow = $row.clone();
    var trow1 = $nextRow.clone();
    $row.replaceWith(trow1);
    $nextRow.replaceWith(trow);
    megamenu = $('#tbm-admin-mm-container').find('.tbm');
    nav_items = megamenu.find('ul[class*="level"]>li>:first-child');
    nav_subs = megamenu.find('.tbm-item-child');
    nav_cols = megamenu.find('[class*="span"]');
    nav_all = nav_items.add(nav_subs).add(nav_cols);
    bindEvents(nav_all);
    currentSelected = $rows.find('.selected');
  };
  actions.addColumn = function () {
    if (!currentSelected) {
      return;
    }
    var $cols = currentSelected.parent().children('[class*="span"]');
    var colcount = $cols.length + 1;
    var colwidths = defaultColumnsWidth(colcount);
    var column = ++drupalSettings.TBMegaMenu.TBElementsCounter.column;
    var $col = $('<div id=tbm-column-' + column + '><div class="tbm-column-inner"></div></div>');
    if (actions.datas.addfirst) {
      $col.prependTo(currentSelected.parent());
    } else {
      $col.insertAfter(currentSelected);
    }
    $cols = $cols.add($col);
    bindEvents($col);
    $cols.each(function (i) {
      $(this).removeClass('span' + $(this).attr('data-width')).addClass('tbm-column span' + colwidths[i]).attr('data-width', colwidths[i]);
    });
    show_toolbox($col);
  };
  actions.removeColumn = function () {
    if (!currentSelected) {
      return;
    }
    var $col = currentSelected,
      $row = $col.parent(),
      $rows = $row.parent().children('[class*="row"]'),
      $allcols = $rows.children('[class*="span"]'),
      $allmenucols = $allcols.filter(function () {
        return !$(this).attr('data-block');
      }),
      $haspos = $allcols.filter(function () {
        return $(this).attr('data-block');
      }).length,
      $cols = $row.children('[class*="span"]'),
      colcount = $cols.length - 1,
      colwidths = defaultColumnsWidth(colcount),
      type_menu = $col.attr('data-block') ? false : true;
    if (type_menu && (!$haspos && $allmenucols.length == 1 || $haspos && $allmenucols.length == 0) || $allcols.length == 1) {
      show_toolbox($(currentSelected).closest('.tbm-item'));
      currentSelected = $(currentSelected).closest('.tbm-item');
      currentSelected.find('.tbm-submenu').remove();
    } else {
      if (type_menu) {
        var colidx = $allmenucols.index($col),
          tocol = colidx == 0 ? $allmenucols[1] : $allmenucols[colidx - 1];
        $col.find('ul:first > li').appendTo($(tocol).find('ul:first'));
      }
      var colidx = $allcols.index($col),
        nextActiveCol = colidx == 0 ? $allcols[1] : $allcols[colidx - 1];
      if (colcount < 1) {
        $row.remove();
      } else {
        $cols = $cols.not($col);
        $cols.each(function (i) {
          $(this).removeClass('span' + $(this).attr('data-width')).addClass('span' + colwidths[i]).attr('data-width', colwidths[i]);
        });
        $col.remove();
      }
      show_toolbox($(nextActiveCol));
    }
  };
  actions.resetConfig = function (options) {
    if (Drupal.TBMegaMenu.isLockedAjax()) {
      window.setTimeout(function () {
        actions.resetConfig(options);
      }, 200);
      return;
    }
    Drupal.TBMegaMenu.lockAjax();
    $('#tbm-admin-mm-tb #toolbox-message').html('').hide();
    $('#tbm-admin-mm-tb #toolbox-loading').show();
    $.ajax({
      type: 'POST',
      url: drupalSettings.TBMegaMenu.saveConfigURL,
      contentType: 'application/json; charset=utf-8',
      data: JSON.stringify({
        action: 'load',
        theme: drupalSettings.TBMegaMenu.theme,
        menu_name: options['menu_name']
      }),
      complete: function (r) {
        switch (r.status) {
          case 0:
          case 500:
            var statusMsg = r.responseText || Drupal.t('@status reverting changes.', {
              '@status': capitalize(r.statusText)
            });
            break;
          default:
            $('#tbm-admin-mm-container').html(r.responseText).megamenuAdmin({
              menu_name: options['menu_name']
            });
            $('#tbm-admin-mm-container').find('.tbm-column-inner').children('span.close').click(function () {
              $(this).parent().html('');
            });
            var statusMsg = Drupal.t('All unsaved changes have been reverted.');
        }
        status_modal(r.status, statusMsg);
        Drupal.TBMegaMenu.releaseAjax();
      }
    });
  };
  actions.saveConfig = function (options) {
    if (Drupal.TBMegaMenu.isLockedAjax()) {
      window.setTimeout(function () {
        actions.saveConfig(options);
      }, 200);
      return;
    }
    Drupal.TBMegaMenu.lockAjax();
    var menu_config = {},
      items = megamenu.find('ul[class*="level"] > li');
    items.each(function () {
      var $this = $(this),
        id = $this.attr('data-id'),
        rows = [];
      var level = parseInt($this.attr('data-level'));
      var $sub = $this.find('.tbm-item-child:first');
      var $rows = $sub.find('[class*="row"]:first').parent().children('[class*="row"]');
      $rows.each(function () {
        var $cols = $(this).children('[class*="span"]');
        var cols = [];
        $cols.each(function () {
          var col_config = {};
          col_config['width'] = $(this).attr('data-width') ? $(this).attr('data-width') : '';
          col_config['class'] = $(this).attr('data-class') ? $(this).attr('data-class') : '';
          col_config['hidewcol'] = $(this).attr('data-hidewcol') ? $(this).attr('data-hidewcol') : '';
          col_config['showblocktitle'] = $(this).attr('data-showblocktitle') ? $(this).attr('data-showblocktitle') : '1';
          var col = {
            col_content: [],
            col_config: col_config
          };
          $(this).find('ul[class*="level"] > li').each(function () {
            var sub_level = parseInt($(this).attr('data-level'));
            if (sub_level == level + 1) {
              var ele = {};
              ele['plugin_id'] = $(this).attr('data-id');
              ele['type'] = $(this).attr('data-type');
              ele['tb_item_config'] = {};
              col['col_content'].push(ele);
            }
          });
          $(this).children('.tbm-column-inner').children('.tbm-block').each(function () {
            var ele = {};
            ele['block_id'] = $(this).attr('data-block');
            ele['type'] = $(this).attr('data-type');
            ele['tb_item_config'] = {};
            col['col_content'].push(ele);
          });
          if (col['col_content'].length) {
            cols.push(col);
          }
        });
        if (cols.length) {
          rows.push(cols);
        }
      });
      var submenu_config = {};
      submenu_config['width'] = $this.children('.tbm-submenu').attr('data-width') ? $this.children('.tbm-submenu').attr('data-width') : '';
      submenu_config['class'] = $this.children('.tbm-submenu').attr('data-class') ? $this.children('.tbm-submenu').attr('data-class') : '';
      submenu_config['group'] = $this.attr('data-group') ? $this.attr('data-group') : 0;
      var item_config = {};
      item_config['class'] = $this.attr('data-class') ? $this.attr('data-class') : '';
      item_config['xicon'] = $this.attr('data-xicon') ? $this.attr('data-xicon') : '';
      item_config['caption'] = $this.attr('data-caption') ? $this.attr('data-caption') : '';
      item_config['alignsub'] = $this.attr('data-alignsub') ? $this.attr('data-alignsub') : '';
      item_config['group'] = $this.attr('data-group') ? $this.attr('data-group') : '';
      item_config['hidewcol'] = $this.attr('data-hidewcol') ? $this.attr('data-hidewcol') : 1;
      item_config['hidesub'] = $this.attr('data-hidesub') ? $this.attr('data-hidesub') : 1;
      item_config['label'] = $this.attr('data-label') ? $this.attr('data-label') : '';
      var config = {
        rows_content: rows,
        submenu_config: submenu_config,
        item_config: item_config
      };
      menu_config[id] = config;
    });
    var block_config = {};
    block_config['animation'] = $('select[name="tbm-animation"]').val();
    block_config['duration'] = parseInt($('input[name="tbm-duration"]').val());
    block_config['delay'] = parseInt($('input[name="tbm-delay"]').val());
    block_config['breakpoint'] = parseInt($('input[name="tbm-breakpoint"]').val());
    block_config['hide-mobile-menu'] = $('#tbm-admin-mm-intro .toolitem-hide-mobile-menu').attr('data-hide-mobile-menu');
    block_config['auto-arrow'] = $('#tbm-admin-mm-intro .toolitem-auto-arrow').attr('data-auto-arrow');
    block_config['always-show-submenu'] = $('#tbm-admin-mm-intro .toolitem-always-show-submenu').attr('data-always-show-submenu');
    block_config['number-columns'] = drupalSettings.TBMegaMenu.TBElementsCounter.column;
    $('#tbm-admin-mm-tb #toolbox-message').html('').hide();
    $('#tbm-admin-mm-tb #toolbox-loading').show();
    $.ajax({
      type: 'POST',
      url: drupalSettings.TBMegaMenu.saveConfigURL,
      contentType: 'application/json; charset=utf-8',
      data: JSON.stringify({
        action: 'save',
        theme: drupalSettings.TBMegaMenu.theme,
        menu_name: options['menu_name'],
        menu_config: menu_config,
        block_config: block_config
      }),
      complete: function (r) {
        var statusMsg = r.responseText || Drupal.t('@status saving changes.', {
          '@status': capitalize(r.statusText)
        });
        status_modal(r.status, statusMsg);
        Drupal.TBMegaMenu.releaseAjax();
      }
    });
  };
  var status_modal = function (code, statusMsg) {
    clearTimeout(modalTimeout);
    $('#tbm-admin-mm-tb #toolbox-message').html('').hide();
    switch (code) {
      case 0:
      case 500:
        var msgClass = 'messages--error';
        break;
      default:
        var msgClass = 'messages--status';
    }
    $('#tbm-admin-mm-tb #toolbox-loading').hide();
    var $div = $('<div class="messages ' + msgClass + '" role="contentinfo" aria-label="Status message"><h2 class="visually-hidden">Status message</h2><span class="close fa fa-times-circle" title="Dismiss this message">&nbsp;</span>' + statusMsg + '</div>');
    $('#tbm-admin-mm-tb #toolbox-message').html($div).show();
    $('#tbm-admin-mm-tb #toolbox-message span.close').click(function () {
      $(this).parent().html('').hide();
    });
    if (code == 200) {
      modalTimeout = window.setTimeout(function () {
        $('#tbm-admin-mm-tb #toolbox-message').html('').hide();
      }, 7000);
    }
  };
  var toolbox_type = function () {
    return currentSelected ? currentSelected.hasClass('tbm-item-child') ? 'sub' : currentSelected[0].tagName == 'DIV' ? 'col' : 'item' : false;
  };
  var hide_toolbox = function (show_intro) {
    $('#tbm-admin-mm-tb .admin-toolbox').hide();
    currentSelected = null;
    if (megamenu && megamenu.data('nav_all')) {
      megamenu.data('nav_all').removeClass('selected');
    }
    megamenu.find('li').removeClass('open');
    if (show_intro) {
      $('#tbm-admin-mm-intro').show();
    } else {
      $('#tbm-admin-mm-intro').hide();
    }
  };
  var show_toolbox = function (selected) {
    if (!selected.hasClass('tbm-column') && !selected.hasClass('tbm-submenu')) {
      var level = parseInt($(selected).parent().attr('data-level'));
      if (level > 1) {
        $('#toogle-group-wrapper').show();
        $('#toogle-break-column-wrapper').show();
      } else {
        $('#toogle-group-wrapper').hide();
        $('#toogle-break-column-wrapper').hide();
      }
    }
    hide_toolbox(false);
    if (selected) {
      currentSelected = selected;
    }
    megamenu.find('ul[class*="level"] > li').each(function () {
      if (!$(this).has(currentSelected).length > 0) {
        $(this).removeClass('open');
      } else {
        $(this).addClass('open');
      }
    });
    megamenu.data('nav_all').removeClass('selected');
    currentSelected.addClass('selected');
    var type = toolbox_type();
    $('#tbm-admin-mm-tool' + type).show();
    update_toolbox(type);
    $('#tbm-admin-mm-tb').show();
  };
  var update_toolbox = function (type) {
    if (!type) {
      type = toolbox_type();
    }
    $('#tbm-admin-mm-tb .disabled').removeClass('disabled');
    $('#tbm-admin-mm-tb .active').removeClass('active');
    switch (type) {
      case 'item':
        var liitem = currentSelected.closest('li'),
          liparent = liitem.parent().closest('li'),
          sub = liitem.find('.tbm-item-child:first');
        $('.toolitem-exclass').val(liitem.attr('data-class'));
        $('.toolitem-xicon').val(liitem.attr('data-xicon'));
        $('.toolitem-caption').val(liitem.attr('data-caption'));
        var toggle = $('.toolitem-sub');
        toggle.find('label').removeClass('active btn-success btn-danger btn-primary');
        if (parseInt(liitem.attr('data-group'))) {
          toggle.addClass('disabled');
        } else if (sub.length == 0 || sub.css('display') == 'none') {
          update_toggle(toggle, 0);
        } else {
          update_toggle(toggle, 1);
        }
        var toggle = $('.toolitem-group');
        toggle.find('label').removeClass('active btn-success btn-danger btn-primary');
        if (parseInt(liitem.attr('data-level')) == 1 || sub.length == 0 || parseInt(liitem.attr('data-hidesub')) == 1) {
          $('.toolitem-group').addClass('disabled');
        } else if (parseInt(liitem.attr('data-group'))) {
          update_toggle(toggle, 1);
        } else {
          update_toggle(toggle, 0);
        }
        if (!liparent.length || !liparent.hasClass('tbm-item')) {
          $('.toolitem-moveleft, .toolitem-moveright').addClass('disabled');
        }
        break;
      case 'sub':
        var liitem = currentSelected.closest('li');
        $('.toolsub-exclass').attr('value', currentSelected.attr('data-class') || '');
        if (parseInt(liitem.attr('data-group'))) {
          $('.toolsub-width').attr('value', '').addClass('disabled');
          $('.toolitem-alignment').addClass('disabled');
        } else {
          $('.toolsub-width').val(currentSelected.attr('data-width'));
          if (parseInt(liitem.attr('data-level')) > 1) {
            $('.toolsub-align-center').addClass('disabled');
            $('.toolsub-align-justify').addClass('disabled');
          }
          if (liitem.attr('data-alignsub')) {
            $('.toolsub-align-' + liitem.attr('data-alignsub')).addClass('active');
            if (liitem.attr('data-alignsub') == 'justify') {
              $('.toolsub-width').addClass('disabled');
            }
          }
        }
        var toggle = $('.toolsub-hidewhencollapse');
        toggle.find('label').removeClass('active btn-success btn-danger btn-primary');
        if (parseInt(liitem.attr('data-hidewcol'))) {
          update_toggle(toggle, 1);
        } else {
          update_toggle(toggle, 0);
        }
        break;
      case 'col':
        $('.toolcol-block').val(currentSelected.children('.tbm-column-inner').children('.tbm-block').attr('data-block') || '');
        $('.toolcol-width').val(currentSelected.attr('data-width') || '');
        $('.toolcol-exclass').attr('value', currentSelected.attr('data-class') || '');
        if (currentSelected.find('.tbm-subnav').length > 0) {
          $('.toolcol-block').parent().addClass('disabled');
        }
        if (currentSelected.parent().children().length == 1) {
          $('.toolcol-width').parent().addClass('disabled');
        }
        var toggle = $('.toolcol-hidewhencollapse');
        toggle.find('label').removeClass('active btn-success btn-danger btn-primary');
        if (parseInt(currentSelected.attr('data-hidewcol'))) {
          update_toggle(toggle, 1);
        } else {
          update_toggle(toggle, 0);
        }
        var toggle = $('.toolcol-showblocktitle');
        toggle.find('label').removeClass('active btn-success btn-danger btn-primary');
        if (!currentSelected.attr('data-showblocktitle') || parseInt(currentSelected.attr('data-showblocktitle'))) {
          update_toggle(toggle, 1);
        } else {
          update_toggle(toggle, 0);
        }
        break;
    }
  };
  var update_toggle = function (toggle, val) {
    var $input = toggle.find('input[value="' + val + '"]');
    $input.prop('checked', true);
    $input.trigger('update');
  };
  var apply_toolbox = function (input) {
    var name = $(input).attr('data-name'),
      value = input.value,
      type = toolbox_type();
    switch (name) {
      case 'width':
        value = parseInt(value);
        if (isNaN(value)) {
          value = '';
          if (type == 'sub') {
            currentSelected.width(value);
          }
          if (type == 'col') {
            currentSelected.removeClass('span' + currentSelected.attr('data-' + name));
          }
          currentSelected.attr('data-' + name, value);
        } else {
          if (type == 'sub') {
            currentSelected.width(value);
          }
          if (type == 'col') {
            currentSelected.removeClass('span' + currentSelected.attr('data-' + name)).addClass('span' + value);
          }
          currentSelected.attr('data-' + name, value);
        }
        $(input).val(value);
        break;
      case 'duration':
        value = parseInt(value);
        if (isNaN(value)) {
          value = '';
        }
        $(input).val(value);
        break;
      case 'delay':
        value = parseInt(value);
        if (isNaN(value)) {
          value = '';
        }
        $(input).val(value);
        break;
      case 'class':
        if (type == 'item') {
          var item = currentSelected.closest('li');
        } else {
          var item = currentSelected;
        }
        item.removeClass(item.attr('data-' + name) || '').addClass(value);
        item.attr('data-' + name, value);
        break;
      case 'xicon':
        if (type == 'item') {
          currentSelected.closest('li').attr('data-' + name, value);
          currentSelected.find('i').remove();
          var escapedInputText = Drupal.checkPlain(value);
          if (value) {
            currentSelected.prepend($('<i class="' + escapedInputText + '"></i>'));
          }
        }
        break;
      case 'caption':
        if (type == 'item') {
          currentSelected.closest('li').attr('data-' + name, value);
          currentSelected.find('span.tbm-caption').remove();
          var escapedInputText = Drupal.checkPlain(value);
          if (value) {
            currentSelected.append($('<span class="tbm-caption">' + escapedInputText + '</span>'));
          }
        }
        break;
      case 'block':
        if (currentSelected.find('ul[class*="level"]').length == 0) {
          if (value) {
            $('#tbm-admin-mm-tb #toolbox-loading').show();
            callAjax({
              action: 'load_block',
              block_id: value,
              id: currentSelected.attr('id'),
              showblocktitle: parseInt(currentSelected.attr('data-showblocktitle'))
            });
          } else {
            currentSelected.find('.tbm-column-inner').html('');
          }
          currentSelected.attr('data-' + name, value);
        }
        break;
    }
  };
  var callAjax = function (data) {
    if (Drupal.TBMegaMenu.isLockedAjax()) {
      window.setTimeout(function () {
        callAjax(data);
      }, 200);
      return;
    }
    Drupal.TBMegaMenu.lockAjax();
    switch (data.action) {
      case 'load_block':
        $.ajax({
          type: 'POST',
          url: drupalSettings.TBMegaMenu.saveConfigURL,
          contentType: 'application/json; charset=utf-8',
          data: JSON.stringify(data),
          complete: function (msg) {
            var isJson = true;
            try {
              var resp = $.parseJSON(msg.responseText);
            } catch (err) {
              isJson = false;
            }
            if (isJson) {
              var content = resp.content ? resp.content : '';
              var id = resp.id ? resp.id : '';
              var close_button = $('<span class="close fa fa-times-circle" title="' + Drupal.t('Remove this block') + '">&nbsp;</span>');
              var currentElement = $('#' + id);
              if (currentElement.length) {
                currentElement.children('.tbm-column-inner').html('').append(close_button).append($(content)).find(':input').removeAttr('name');
                currentElement.children('.tbm-column-inner').children('span.close').click(function () {
                  $(this).parent().html('');
                });
              }
              $('#tbm-admin-mm-tb #toolbox-loading').hide();
            } else {
              var statusMsg = msg.responseText || Drupal.t('@status performaing ajax calls.', {
                '@status': capitalize(msg.statusText)
              });
              status_modal(msg.status, statusMsg);
            }
            Drupal.TBMegaMenu.releaseAjax();
          }
        });
        break;
      case 'load':
        break;
      default:
        break;
    }
  };
  var defaultColumnsWidth = function (count) {
    if (count < 1) {
      return null;
    }
    var total = 12,
      min = Math.floor(total / count),
      widths = [];
    for (var i = 0; i < count; i++) {
      widths[i] = min;
    }
    widths[count - 1] = total - min * (count - 1);
    return widths;
  };
  var bindEvents = function (els) {
    if (megamenu.data('nav_all')) {
      megamenu.data('nav_all', megamenu.data('nav_all').add(els));
    } else {
      megamenu.data('nav_all', els);
    }
    els.mouseover(function (event) {
      megamenu.data('nav_all').removeClass('hover');
      var $this = $(this);
      clearTimeout(megamenu.attr('data-hovertimeout'));
      megamenu.attr('data-hovertimeout', setTimeout(function () {
        $this.addClass('hover');
      }, 100));
      event.stopPropagation();
    });
    els.mouseout(function (event) {
      clearTimeout(megamenu.attr('data-hovertimeout'));
      $(this).removeClass('hover');
    });
    els.click(function (event) {
      show_toolbox($(this));
      event.stopPropagation();
      return false;
    });
  };
  var unbindEvents = function (els) {
    megamenu.data('nav_all', megamenu.data('nav_all').not(els));
    els.unbind('mouseover').unbind('mouseout').unbind('click');
  };
  var rebindEvents = function (els) {
    unbindEvents(els);
    bindEvents(els);
  };
  var capitalize = function (text) {
    return text.charAt(0).toUpperCase() + text.slice(1);
  };
  $.extend(Drupal.TBMegaMenu, {
    prepare: function () {
      $('#tbm-admin').removeClass('hidden');
    },
    tb_megamenu: function (form, ctrlelm, ctrl, rsp) {
      $('#tbm-admin-mm-container').html(rsp).megamenuAdmin().find(':input').removeAttr('name');
    },
    initPanel: function () {
      $('#jform_params_mm_panel').hide();
    },
    initPreSubmit: function () {
      var form = document.adminForm;
      if (!form) {
        return false;
      }
      var onsubmit = form.onsubmit;
      form.onsubmit = function (e) {
        $('.toolbox-saveConfig').trigger('click');
        if ($.isfunction(onsubmit)) {
          onsubmit();
        }
      };
    },
    initRadioGroup: function () {
      var tb_megamenu_instance = $('.tbm-admin');
      tb_megamenu_instance.find('.radio.btn-group label').addClass('btn');
      tb_megamenu_instance.find('.btn-group label').unbind('click').click(function () {
        var label = $(this),
          input = $('#' + label.attr('for'));
        if (!input.attr('checked')) {
          label.closest('.btn-group').find('label').removeClass('active btn-success btn-danger btn-primary');
          label.addClass('active ' + (input.val() == '' ? 'btn-primary' : input.val() == 0 ? 'btn-danger' : 'btn-success'));
          input.attr('checked', true).trigger('change');
        }
      });
      tb_megamenu_instance.find('input[type=radio]').bind('update', function () {
        if (this.checked) {
          $(this).closest('.btn-group').find('label').removeClass('active btn-success btn-danger btn-primary').filter('[for="' + this.id + '"]').addClass('active ' + ($(this).val() == '' ? 'btn-primary' : $(this).val() == 0 ? 'btn-danger' : 'btn-success'));
        }
      });
      tb_megamenu_instance.find('.btn-group input[checked=checked]').each(function () {
        if ($(this).val() == '') {
          $('label[for=' + $(this).attr('id') + ']').addClass('active btn-primary');
        } else if ($(this).val() == 0) {
          $('label[for=' + $(this).attr('id') + ']').addClass('active btn-danger');
        } else {
          $('label[for=' + $(this).attr('id') + ']').addClass('active btn-success');
        }
      });
    }
  });
  $(window).on('load', function () {
    Drupal.TBMegaMenu.initPanel();
    Drupal.TBMegaMenu.initPreSubmit();
    Drupal.TBMegaMenu.initRadioGroup();
    Drupal.TBMegaMenu.prepare();
  });
})(jQuery, Drupal, drupalSettings);

/***/ })

/******/ });
//# sourceMappingURL=toolbox.js.map