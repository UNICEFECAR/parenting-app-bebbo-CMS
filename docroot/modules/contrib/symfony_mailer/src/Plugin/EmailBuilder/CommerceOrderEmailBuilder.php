<?php

namespace Drupal\symfony_mailer\Plugin\EmailBuilder;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderType;
use Drupal\symfony_mailer\Address;
use Drupal\symfony_mailer\EmailFactoryInterface;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Entity\MailerPolicy;
use Drupal\symfony_mailer\MailerHelperTrait;
use Drupal\symfony_mailer\Processor\EmailBuilderBase;

/**
 * Defines the Email Builder plug-in for commerce order module.
 *
 * @EmailBuilder(
 *   id = "commerce_order_type",
 *   label = "Commerce order",
 *   sub_types = {
 *    "receipt" = @Translation("Receipt"),
 *    "resend_receipt" = @Translation("Resend receipt"),
 *   },
 *   has_entity = TRUE,
 *   override = {"commerce.order_receipt"},
 *   override_warning = @Translation("Experimental, may change"),
 *   override_config = {
 *     "core.entity_view_mode.commerce_order.email",
 *     "core.entity_view_display.commerce_order.default.email",
 *   },
 *   common_adjusters = {"email_subject", "email_body", "email_bcc"},
 *   import = @Translation("Order type settings"),
 *   form_alter = {
 *     "*" = {
 *       "remove" = { "emails" },
 *       "entity_sub_type" = "receipt",
 *     },
 *   },
 * )
 *
 * The template variable 'body' is generated from the order type settings for
 * "Manage Display" (1). The "Order item table" formatter is generated from the
 * "Order items" view (2).
 * (1) /admin/commerce/config/order-types/XXX/edit/display/email
 * (2) /admin/structure/views/view/commerce_order_item_table
 *
 * @todo Notes for adopting Symfony Mailer into commerce. It should be possible
 * to remove the MailHandler service and classes such as OrderReceiptMail. The
 * commerce_order_receipt template could be retired, switching instead to use
 * email__commerce_order_type__receipt or by editing Mailer Policy for
 * commerce_order_type.
 */
class CommerceOrderEmailBuilder extends EmailBuilderBase {

  use MailerHelperTrait;

  /**
   * Saves the parameters for a newly created email.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to modify.
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   */
  public function createParams(EmailInterface $email, OrderInterface $order = NULL) {
    assert($order != NULL);
    $email->setParam('commerce_order_item', $order);
  }

  /**
   * {@inheritdoc}
   */
  public function fromArray(EmailFactoryInterface $factory, array $message) {
    $order = $message['params']['order'];
    $order_type = OrderType::load($order->bundle());
    $sub_type = empty($message['params']['resend']) ? 'receipt' : 'resend_receipt';
    return $factory->newEntityEmail($order_type, $sub_type, $order);
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email) {
    $order = $email->getParam('commerce_order_item');
    $store = $order->getStore();
    $customer = $order->getCustomer();
    $to = $customer->isAuthenticated() ? $customer : $order->getEmail();

    $email->setTo($to)
      ->setBodyEntity($order, 'email')
      ->addLibrary('symfony_mailer/commerce_order')
      ->setVariable('order_number', $order->getOrderNumber())
      ->setVariable('store', $store->getName());

    // Get the actual email value without picking up the default from the site
    // mail. Instead we prefer to default from Mailer policy.
    if ($store_email = $store->get('mail')->value) {
      $email->setFrom($store_email);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function import() {
    $helper = $this->helper();

    foreach (OrderType::loadMultiple() as $id => $order_type) {
      $config = [];
      if ($bcc = $order_type->getReceiptBcc()) {
        $config['email_bcc'] = $helper->policyFromAddresses([new Address($bcc)]);
      }
      if (!$order_type->shouldSendReceipt()) {
        $config['email_skip_sending']['message'] = 'Receipt disabled in settings';
      }
      MailerPolicy::import("commerce_order_type.receipt.$id", $config);
    }
  }

}
