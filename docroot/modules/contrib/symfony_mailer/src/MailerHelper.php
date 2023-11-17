<?php

namespace Drupal\symfony_mailer;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Token;
use Drupal\symfony_mailer\Processor\EmailAdjusterManagerInterface;
use Drupal\symfony_mailer\Processor\EmailBuilderManagerInterface;
use Html2Text\Html2Text;

/**
 * Provides the mailer helper service.
 */
class MailerHelper implements MailerHelperInterface {

  use StringTranslationTrait;

  /**
   * Regular expression for parsing addresses.
   *
   * Matches a string like 'Name <email@address.com>' Anything between the
   * first < and last > counts as the email address. This does not try to cover
   * all edge cases for address.
   */
  protected const FROM_STRING_PATTERN = '~(?<displayName>[^<]*)<(?<addrSpec>.*)>[^>]*~';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The email adjuster manager.
   *
   * @var \Drupal\symfony_mailer\Processor\EmailAdjusterManagerInterface
   */
  protected $adjusterManager;

  /**
   * The email builder manager.
   *
   * @var \Drupal\symfony_mailer\Processor\EmailBuilderManagerInterface
   */
  protected $builderManager;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Array of form alter configuration.
   *
   * The key is the form ID and the value is an array of alterations.
   *
   * @var array
   */
  protected $formAlter = NULL;

  /**
   * Constructs the MailerHelper object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\symfony_mailer\Processor\EmailAdjusterManagerInterface $email_adjuster_manager
   *   The email adjuster manager.
   * @param \Drupal\symfony_mailer\Processor\EmailBuilderManagerInterface $email_builder_manager
   *   The email builder manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EmailAdjusterManagerInterface $email_adjuster_manager, EmailBuilderManagerInterface $email_builder_manager, ConfigFactoryInterface $config_factory, Token $token) {
    $this->entityTypeManager = $entity_type_manager;
    $this->adjusterManager = $email_adjuster_manager;
    $this->builderManager = $email_builder_manager;
    $this->configFactory = $config_factory;
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public function parseAddress(string $encoded, string $langcode = NULL) {
    foreach (explode(',', $encoded) as $part) {
      // Code copied from \Symfony\Component\Mime\Address::create().
      if (strpos($part, '<')) {
        if (!preg_match(self::FROM_STRING_PATTERN, $part, $matches)) {
          throw new \InvalidArgumentException("Could not parse $part as an address.");
        }
        $addresses[] = new Address($matches['addrSpec'], trim($matches['displayName'], ' \'"'), $langcode);
      }
      else {
        $addresses[] = new Address($part, NULL, $langcode);
      }
    }
    return $addresses ?: [];
  }

  /**
   * {@inheritdoc}
   */
  public function policyFromAddresses(array $addresses) {
    $site_mail = $this->configFactory->get('system.site')->get('mail');

    foreach ($addresses as $address) {
      $value = $address->getEmail();
      if ($value == $site_mail) {
        $value = '<site>';
      }
      elseif ($user = $address->getAccount()) {
        $value = $user->id();
      }
      else {
        $display = $address->getDisplayName();
      }

      $config['addresses'][] = [
        'value' => $value,
        'display' => $display ?? '',
      ];
    }

    return $config ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function htmlToText(string $html) {
    // Convert to plain text.
    // - Core uses MailFormatHelper::htmlToText(). However this is old code
    //   that's not actively maintained there's no need for a Drupal-specific
    //   version of this generic code.
    // - Symfony Mailer library uses league/html-to-markdown. This is a bigger
    //   step away from what's been done in Drupal before, so we won't do that.
    // - Swiftmailer uses html2text/html2text, and that's what we do.
    return (new Html2Text($html))->getText();
  }

  /**
   * {@inheritdoc}
   */
  public function config() {
    return $this->configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public function renderEntityPolicy(ConfigEntityInterface $entity, string $sub_type) {
    $type = $entity->getEntityTypeId();
    $policy_id = "$type.$sub_type";
    $entities = [$policy_id];
    if (!$entity->isNew()) {
      $entities[] = $policy_id . '.' . $entity->id();
    }
    $element = $this->renderCommon($type);
    $element['listing'] = $this->entityTypeManager->getListBuilder('mailer_policy')
      ->overrideEntities($entities)
      ->hideColumns(['type', 'sub_type'])
      ->render();

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function renderTypePolicy(string $type) {
    $element = $this->renderCommon($type);
    $entities = [$type];
    foreach (array_keys($this->builderManager->getDefinition($type)['sub_types']) as $sub_type) {
      $entities[] = "$type.$sub_type";
    }

    $element['listing'] = $this->entityTypeManager->getListBuilder('mailer_policy')
      ->overrideEntities($entities)
      ->hideColumns(['type', 'entity'])
      ->render();

    return $element;
  }

  /**
   * Implementation for hook_form_alter().
   *
   * @internal
   */
  public function formAlter(&$form, FormStateInterface $form_state, $form_id) {
    if (is_null($this->formAlter)) {
      $this->formAlter = [];
      foreach ($this->builderManager->getDefinitions() as $builder_id => $definition) {
        foreach ($definition['form_alter'] as $match => $alter) {
          $alter += ['remove' => [], 'default' => [], 'entity_sub_type' => NULL, 'type' => NULL];
          $ids = ($match == '*') ? ["{$builder_id}_edit_form", "{$builder_id}_add_form"] : [$match];
          foreach ($ids as $id) {
            // Merge existing values.
            $this->formAlter[$id] = NestedArray::mergeDeep($alter, $this->formAlter[$id] ?? []);
          }
        }
      }
    }

    if ($alter = $this->formAlter[$form_id] ?? NULL) {
      // Hide fields that are replaced by Mailer Policy.
      foreach ($alter['remove'] as $key) {
        $form[$key]['#access'] = FALSE;
      }

      // Set defaults for hidden fields.
      foreach ($alter['default'] as $key => $default) {
        if (empty($form[$key]['#default_value'])) {
          $form[$key]['#default_value'] = $this->token->replace($default);
        }
      }

      // Add policy elements on entity forms.
      if ($sub_type = $alter['entity_sub_type']) {
        $form['mailer_policy'] = $this->renderEntityPolicy($form_state->getFormObject()->getEntity(), $sub_type);
      }

      // Add policy elements on settings forms.
      if ($type = $alter['type']) {
        $form['mailer_policy'] = $this->renderTypePolicy($type);
      }
    }
  }

  /**
   * Renders common parts for policy elements.
   *
   * @param string $type
   *   Type of the policies to show.
   *
   * @return array
   *   The render array.
   */
  protected function renderCommon(string $type) {
    $element = [
      '#type' => 'fieldset',
      '#title' => $this->t('Mailer policy'),
      '#collapsible' => FALSE,
      '#description' => $this->t('If you have made changes on this page, please save them before editing policy.'),
    ];

    $definition = $this->builderManager->getDefinition($type);
    $element['explanation'] = [
      '#prefix' => '<p>',
      '#markup' => $this->t('Configure Mailer policy records to customise the emails sent for @label.', ['@label' => $definition['label']]),
      '#suffix' => '</p>',
    ];

    foreach ($definition['common_adjusters'] as $adjuster_id) {
      $adjuster_names[] = $this->adjusterManager->getDefinition($adjuster_id)['label'];
    }

    if (!empty($adjuster_names)) {
      $element['explanation']['#markup'] .= ' ' . $this->t('You can set the @adjusters and more.', ['@adjusters' => implode(', ', $adjuster_names)]);
    }

    return $element;
  }

}
