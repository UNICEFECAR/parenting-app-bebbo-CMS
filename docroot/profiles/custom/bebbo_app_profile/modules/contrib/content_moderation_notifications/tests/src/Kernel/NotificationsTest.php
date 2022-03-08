<?php

namespace Drupal\Tests\content_moderation_notifications\Kernel;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\entity_test\Entity\EntityTestRev;
use Drupal\KernelTests\KernelTestBase;

/**
 * Test sending of notifications for moderation state changes.
 *
 * @group content_moderation_notifications
 */
class NotificationsTest extends KernelTestBase {

  use AssertMailTrait;
  use ContentModerationNotificationCreateTrait;
  use ContentModerationNotificationTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'content_moderation',
    'content_moderation_notifications',
    'content_moderation_notifications_test',
    'entity_test',
    'filter',
    'filter_test',
    'system',
    'user',
    'workflows',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('entity_test_rev');
    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('user');
    $this->installConfig(['content_moderation', 'filter_test']);

    // Setup site email.
    $this->config('system.site')->set('mail', 'admin@example.com')->save();

    // Attach workflow to entity test.
    $this->enableModeration();
  }

  /**
   * Test sending of emails.
   */
  public function testEmailDelivery() {
    // No emails should be sent for content without notifications.
    $entity = EntityTestRev::create();
    $entity->save();
    $this->assertEmpty($this->getMails());

    // Add a notification.
    $long_email = $this->randomMachineName(128) . '@example.com';
    $notification = $this->createNotification([
      'emails' => 'foo@example.com, bar@example.com' . "\r\n" . $long_email,
      'transitions' => [
        'create_new_draft' => 'create_new_draft',
        'publish' => 'publish',
        'archived_published' => 'archived_published',
      ],
    ]);

    $entity = \Drupal::entityTypeManager()->getStorage('entity_test_rev')->loadUnchanged($entity->id());
    $this->assertEquals('draft', $entity->moderation_state->value);
    $entity->save();
    $this->assertMail('from', 'admin@example.com');
    $this->assertMail('to', 'admin@example.com');
    $this->assertBccRecipients('foo@example.com,bar@example.com,' . $long_email);
    $this->assertMail('id', 'content_moderation_notifications_content_moderation_notification');
    $this->assertMail('subject', PlainTextOutput::renderFromHtml($notification->getSubject()));
    $this->assertCount(1, $this->getMails());

    $entity->moderation_state = 'published';
    $entity->save();
    $this->assertMail('from', 'admin@example.com');
    $this->assertMail('to', 'admin@example.com');
    $this->assertBccRecipients('foo@example.com,bar@example.com,' . $long_email);
    $this->assertMail('id', 'content_moderation_notifications_content_moderation_notification');
    $this->assertMail('subject', PlainTextOutput::renderFromHtml($notification->getSubject()));
    $this->assertCount(2, $this->getMails());

    // No mail should be sent for irrelevant transition.
    $entity = \Drupal::entityTypeManager()->getStorage('entity_test_rev')->loadUnchanged($entity->id());
    $this->assertEquals('published', $entity->moderation_state->value);
    $entity->moderation_state = 'archived';
    $entity->save();
    $this->assertCount(2, $this->getMails());

    // Verify alter hook is functioning.
    // @see content_moderation_notifications_test_content_moderation_notification_mail_data_alter
    \Drupal::state()->set('content_moderation_notifications_test.alter', TRUE);
    $entity = \Drupal::entityTypeManager()->getStorage('entity_test_rev')->loadUnchanged($entity->id());
    $this->assertEquals('archived', $entity->moderation_state->value);
    $entity->moderation_state = 'published';
    $entity->save();
    $this->assertMail('from', 'admin@example.com');
    $this->assertMail('to', 'admin@example.com');
    $this->assertBccRecipients('altered@example.com,foo' . $entity->id() . '@example.com');
    $this->assertMail('id', 'content_moderation_notifications_content_moderation_notification');
    $this->assertMail('subject', PlainTextOutput::renderFromHtml($notification->getSubject()));
    $this->assertCount(3, $this->getMails());

    // Do not send notifications to the site email address if settings enabled.
    $notification->set('site_mail', TRUE)->save();
    $entity = \Drupal::entityTypeManager()->getStorage('entity_test_rev')->loadUnchanged($entity->id());
    $entity->moderation_state = 'published';
    $entity->save();
    $this->assertMail('from', 'admin@example.com');
    $this->assertMail('to', '');
    $this->assertBccRecipients('altered@example.com,foo' . $entity->id() . '@example.com');
    $this->assertMail('id', 'content_moderation_notifications_content_moderation_notification');
    $this->assertMail('subject', PlainTextOutput::renderFromHtml($notification->getSubject()));

    // Send notication to the site email address if settings disabled.
    $notification->set('site_mail', FALSE)->save();
    $entity = \Drupal::entityTypeManager()->getStorage('entity_test_rev')->loadUnchanged($entity->id());
    $entity->moderation_state = 'published';
    $entity->save();
    $this->assertMail('from', 'admin@example.com');
    $this->assertMail('to', 'admin@example.com');
    $this->assertBccRecipients('altered@example.com,foo' . $entity->id() . '@example.com');
    $this->assertMail('id', 'content_moderation_notifications_content_moderation_notification');
    $this->assertMail('subject', PlainTextOutput::renderFromHtml($notification->getSubject()));
  }

  /**
   * Helper method to assert the Bcc recipients.
   *
   * @param string $recipients
   *   The expected recipients.
   */
  protected function assertBccRecipients($recipients) {
    $mails = $this->getMails();
    $mail = end($mails);
    $this->assertNotEmpty($mail['headers']['Bcc']);
    $this->assertEquals($recipients, $mail['headers']['Bcc']);
  }

}
