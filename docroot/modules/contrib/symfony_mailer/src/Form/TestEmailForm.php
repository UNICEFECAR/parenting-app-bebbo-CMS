<?php

namespace Drupal\symfony_mailer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\symfony_mailer\EmailFactoryInterface;
use Drupal\symfony_mailer\MailerHelperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Symfony Mailer test email form.
 */
class TestEmailForm extends FormBase {

  /**
   * The email factory service.
   *
   * @var \Drupal\symfony_mailer\EmailFactoryInterface
   */
  protected $emailFactory;

  /**
   * The mailer helper.
   *
   * @var \Drupal\symfony_mailer\MailerHelperInterface
   */
  protected $helper;

  /**
   * Constructs a new TestForm.
   *
   * @param \Drupal\symfony_mailer\EmailFactoryInterface $email_factory
   *   The email factory service.
   * @param \Drupal\symfony_mailer\MailerHelperInterface $helper
   *   The mailer helper.
   */
  public function __construct(EmailFactoryInterface $email_factory, MailerHelperInterface $helper) {
    $this->emailFactory = $email_factory;
    $this->helper = $helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('email_factory'),
      $container->get('symfony_mailer.helper'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'symfony_mailer_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#tree'] = TRUE;

    $form['recipient'] = [
      '#title' => $this->t('Recipient'),
      '#type' => 'textfield',
      '#default_value' => '',
      '#description' => $this->t('Recipient email address. Leave blank to send to yourself.'),
    ];

    $form['mailer_policy'] = $this->helper->renderTypePolicy('symfony_mailer');

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $to = $form_state->getValue('recipient') ?: $this->currentUser();
    $this->emailFactory->sendTypedEmail('symfony_mailer', 'test', $to);
    $message = is_object($to) ?
      $this->t('An attempt has been made to send an email to you.') :
      $this->t('An attempt has been made to send an email to @to.', ['@to' => $to]);
    $this->messenger()->addMessage($message);
  }

}
