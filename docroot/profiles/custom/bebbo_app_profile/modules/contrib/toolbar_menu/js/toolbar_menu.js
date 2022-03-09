/**
 * @file
 * Defines the behavior of the toolbar_menu module.
 *
 * Rewrite a piece of toolbar.js
 * to render all toolbar element in collapsible menu.
 */

(function ($, Drupal, drupalSettings) {

    'use strict';

    // Merge run-time settings with the defaults.
    var options = $.extend(
        {
            breakpoints: {
                'toolbar.narrow': '',
                'toolbar.standard': '',
                'toolbar.wide': ''
            }
        },
        drupalSettings.toolbar,
        // Merge strings on top of drupalSettings so that they are not mutable.
        {
            strings: {
                horizontal: Drupal.t('Horizontal orientation'),
                vertical: Drupal.t('Vertical orientation')
            }
        }
    );

    Drupal.behaviors.toolbar_menu = {
        attach: function (context) {
            // Process the administrative toolbar.
            $(context).find('#toolbar-administration').once('toolbar-menu').each(function () {
                // Establish the toolbar models and views.
                var model = Drupal.toolbar.models.toolbarModel = new Drupal.toolbar.ToolbarModel({
                    locked: JSON.parse(localStorage.getItem('Drupal.toolbar.trayVerticalLocked')) || false,
                    activeTab: document.getElementById(JSON.parse(localStorage.getItem('Drupal.toolbar.activeTabID')))
                });

                // Render collapsible menus.
                var menuModel = Drupal.toolbar.models.menuModel = new Drupal.toolbar.MenuModel();
                Drupal.toolbar.views.menuVisualView = new Drupal.toolbar.MenuVisualView({
                    el: $(this).find('.toolbar-menu-administration'),
                    model: menuModel,
                    strings: options.strings
                });

                // Handle the resolution of Drupal.toolbar.setSubtrees.
                // This is handled with a deferred so that the function may be invoked
                // asynchronously.
                Drupal.toolbar.setSubtrees.done(function (subtrees) {
                    menuModel.set('subtrees', subtrees);
                    var theme = drupalSettings.ajaxPageState.theme;
                    localStorage.setItem('Drupal.toolbar.subtrees.' + theme, JSON.stringify(subtrees));
                    // Indicate on the toolbarModel that subtrees are now loaded.
                    model.set('areSubtreesLoaded', true);
                });

            });
        }
    };

}(jQuery, Drupal, drupalSettings));
