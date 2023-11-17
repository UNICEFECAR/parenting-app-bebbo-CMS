<?php

namespace Drupal\symfony_mailer\Plugin\EmailAdjuster;

use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Processor\EmailAdjusterBase;

/**
 * Defines the Default headers Email Adjuster.
 *
 * @EmailAdjuster(
 *   id = "mailer_hooks",
 *   label = @Translation("Hooks"),
 *   description = @Translation("Call hooks."),
 *   automatic = TRUE,
 * )
 */
class HooksEmailAdjuster extends EmailAdjusterBase {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public function init(EmailInterface $email) {
    $this->moduleHandler = \Drupal::moduleHandler();

    foreach (EmailInterface::PHASE_NAMES as $phase => $name) {
      if ($phase == EmailInterface::PHASE_INIT) {
        // Call init hooks immediately.
        $this->invokeHooks($email);
      }
      else {
        // Add processor to invoke hooks later.
        $email->addProcessor([$this, 'invokeHooks'], $phase, EmailInterface::DEFAULT_WEIGHT, "hook_mailer_$name");
      }
    }
  }

  /**
   * Invokes hooks for an email.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email.
   *
   * @see hook_mailer_PHASE()
   * @see hook_mailer_TYPE_PHASE()
   * @see hook_mailer_TYPE__SUBTYPE_PHASE()
   */
  public function invokeHooks(EmailInterface $email) {
    $name = EmailInterface::PHASE_NAMES[$email->getPhase()];
    $type = $email->getType();
    $sub_type = $email->getSubType();
    $hooks = ["mailer", "mailer_$type", "mailer_{$type}__$sub_type"];

    foreach ($hooks as $hook_variant) {
      $this->moduleHandler->invokeAll("{$hook_variant}_$name", [$email]);
    }
  }

}
