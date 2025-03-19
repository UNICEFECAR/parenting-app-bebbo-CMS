/**
 * @file
 * Defines Javascript behaviors for the csp module admin form.
 */

(function ($, Drupal) {
  /**
   * Sets summary of policy tabs.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches summary behavior for policy form tabs.
   */
  Drupal.behaviors.cspPolicySummary = {
    attach(context) {
      $(context)
        .find('[data-drupal-selector="edit-policies"] > details')
        .each(function () {
          const $details = $(this);
          const elementPrefix = $details.data('drupal-selector');
          const createPolicyElementSelector = function (name) {
            return `[data-drupal-selector="${elementPrefix}-${name}"]`;
          };

          $details.drupalSetSummary(function () {
            const directivesElementSelector =
              createPolicyElementSelector('directives');
            const directiveCount = $details.find(
              `${directivesElementSelector} [name$="[enable]"]:checked`,
            ).length;

            const status = $details
              .find(createPolicyElementSelector('enable'))
              .prop('checked')
              ? Drupal.t('Enabled')
              : Drupal.t('Disabled');

            const directives = Drupal.formatPlural(
              directiveCount,
              '@count directive',
              '@count directives',
              { '@count': directiveCount },
            );

            return `${status}, ${directives}`;
          });
        });
    },
  };

  /**
   * If upgrade-insecure-requests is enabled, block-all-mixed-content should be
   * forced as disabled.
   *
   * Form states handles disabling the field, but it will be left checked.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.cspBlockAllMixedDisabled = {
    attach(context) {
      const $mixedContent = $(context).find(
        'input[data-drupal-selector="edit-enforce-directives-block-all-mixed-content-enable"]',
      );

      $(context)
        .find(
          'input[data-drupal-selector="edit-enforce-directives-upgrade-insecure-requests-enable"]',
        )
        .on('change', function () {
          $mixedContent.prop('checked', false);
        });
    },
  };
})(jQuery, Drupal);
