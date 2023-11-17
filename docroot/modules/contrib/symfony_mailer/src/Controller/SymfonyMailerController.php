<?php

namespace Drupal\symfony_mailer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\symfony_mailer\Entity\MailerPolicy;
use Drupal\symfony_mailer\MailerTransportInterface;
use Drupal\symfony_mailer\Processor\OverrideManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Route controller for symfony mailer.
 */
class SymfonyMailerController extends ControllerBase {

  /**
   * The override manager.
   *
   * @var \Drupal\symfony_mailer\Processor\OverrideManagerInterface
   */
  protected $overrideManager;

  /**
   * Constructs the MailerCommands object.
   *
   * @param \Drupal\symfony_mailer\Processor\OverrideManagerInterface $override_manager
   *   The override manager.
   */
  public function __construct(OverrideManagerInterface $override_manager) {
    $this->overrideManager = $override_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('symfony_mailer.override_manager')
    );
  }

  /**
   * Returns a page about the config import status.
   *
   * @return array
   *   Render array.
   *
   * @deprecated in symfony_mailer:1.3.0 and is removed from symfony_mailer:2.0.0.
   * Instead you should use overrideStatus().
   *
   * @see https://www.drupal.org/node/3354665
   */
  public function importStatus() {
    @trigger_error('The route symfony_mailer.import.status is deprecated in symfony_mailer:1.3.0 and is removed from symfony_mailer:2.0.0. Instead you should use symfony_mailer.override.status. See https://www.drupal.org/node/3354665', E_USER_DEPRECATED);
    return $this->redirect('symfony_mailer.override.status');
  }

  /**
   * Returns a page about override management status.
   *
   * @return array
   *   Render array.
   */
  public function overrideStatus() {
    $info = $this->overrideManager->getInfo();
    if ($info) {
      $info[OverrideManagerInterface::ALL_OVERRIDES] = $this->overrideManager->getInfo(OverrideManagerInterface::ALL_OVERRIDES);
    }

    // Show a warning for unsupported combinations, fixed in v2.
    // @see https://www.drupal.org/project/symfony_mailer/issues/3366091
    $unsupported = [
      'simplenews' => 'simplenews_newsletter',
      'contact' => 'contact_form',
    ];
    foreach ($unsupported as $a => $b) {
      if (isset($info[$a]['state']) && isset($info[$b]['state'])) {
        if (($info[$a]['state'] != OverrideManagerInterface::STATE_DISABLED) && ($info[$b]['state'] == OverrideManagerInterface::STATE_DISABLED)) {
          $this->messenger()->addError($this->t('Enabling %a but not %b is not supported', ['%a' => $info[$a]['name'], '%b' => $info[$b]['name']]));
        }
      }
    }

    $build = [
      '#type' => 'table',
      '#header' => [
        'name' => $this->t('Name'),
        'state_name' => $this->t('State'),
        'import' => $this->t('Import'),
        'operations' => $this->t('Operations'),
      ],
      '#rows' => $info,
      '#empty' => $this->t('There are no overrides available.'),
    ];

    foreach ($build['#rows'] as $id => &$row) {
      $operations = [];

      // Calculate the available operations.
      foreach ($row['action_names'] as $action => $label) {
        if ($label) {
          $operations[$action] = [
            'title' => $label,
            'url' => Url::fromRoute('symfony_mailer.override.action', ['action' => $action, 'id' => $id]),
          ];
        }
      }

      $row['operations']['data'] = [
        '#type' => 'operations',
        '#links' => $operations,
      ];

      if ($row['warning']) {
        // Combine the warning into the name column.
        $row['name'] = [
          'data' => [
            '#type' => 'inline_template',
            '#template' => '{{ name }}<br><em>Warning: {{ warning }}</em>',
            '#context' => $row,
          ],
        ];
      }

      if ($row['import_warning']) {
        // Combine the import warning into the import column.
        $row['import'] = [
          'data' => [
            '#type' => 'inline_template',
            '#template' => '{{ import }}<br><em>Warning: {{ import_warning }}</em>',
            '#context' => $row,
          ],
        ];
      }

      // Remove any extra keys.
      $row = array_intersect_key($row, $build['#header']);
    }

    return $build;
  }

  /**
   * Sets the transport as the default.
   *
   * @param \Drupal\symfony_mailer\MailerTransportInterface $mailer_transport
   *   The mailer transport entity.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect to the transport listing page.
   */
  public function setAsDefault(MailerTransportInterface $mailer_transport) {
    $mailer_transport->setAsDefault();
    $this->messenger()->addStatus($this->t('The default transport is now %label.', ['%label' => $mailer_transport->label()]));
    return $this->redirect('entity.mailer_transport.collection');
  }

  /**
   * Creates a policy and redirects to the edit page.
   *
   * @param string $policy_id
   *   The policy ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect to the policy edit page.
   */
  public function createPolicy(string $policy_id, Request $request = NULL) {
    MailerPolicy::create(['id' => $policy_id])->save();
    $options = [];
    $query = $request->query;
    if ($query->has('destination')) {
      $options['query']['destination'] = $query->get('destination');
      $query->remove('destination');
    }
    return $this->redirect('entity.mailer_policy.edit_form', ['mailer_policy' => $policy_id], $options);
  }

}
