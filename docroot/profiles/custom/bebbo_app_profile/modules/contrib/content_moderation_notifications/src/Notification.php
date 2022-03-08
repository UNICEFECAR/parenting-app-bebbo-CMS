<?php

namespace Drupal\content_moderation_notifications;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\token\TokenEntityMapperInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\RoleInterface;

/**
 * General service for moderation-related questions about Entity API.
 */
class Notification implements NotificationInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The notification information service.
   *
   * @var \Drupal\content_moderation_notifications\NotificationInformationInterface
   */
  protected $notificationInformation;

  /**
   * The token entity mapper, if available.
   *
   * @var \Drupal\token\TokenEntityMapperInterface
   */
  protected $tokenEntityMapper;

  /**
   * Creates a new ModerationInformation instance.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\content_moderation_notifications\NotificationInformationInterface $notification_information
   *   The notification information service.
   * @param \Drupal\token\TokenEntityMapperInterface $token_entity_mappper
   *   The token entity mapper service.
   */
  public function __construct(AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager, MailManagerInterface $mail_manager, ModuleHandlerInterface $module_handler, NotificationInformationInterface $notification_information, TokenEntityMapperInterface $token_entity_mappper = NULL) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->mailManager = $mail_manager;
    $this->moduleHandler = $module_handler;
    $this->notificationInformation = $notification_information;
    $this->tokenEntityMapper = $token_entity_mappper;
  }

  /**
   * {@inheritdoc}
   */
  public function processEntity(EntityInterface $entity) {
    $notifications = $this->notificationInformation->getNotifications($entity);
    if (!empty($notifications)) {
      $this->sendNotification($entity, $notifications);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function sendNotification(EntityInterface $entity, array $notifications) {

    /** @var \Drupal\content_moderation_notifications\ContentModerationNotificationInterface $notification */
    foreach ($notifications as $notification) {
      $data['langcode'] = $this->currentUser->getPreferredLangcode();
      $data['notification'] = $notification;
      // Setup the email subject and body content.
      $data['params']['subject'] = $notification->getSubject();
      $data['params']['message'] = check_markup($notification->getMessage(), $notification->getMessageFormat());

      // Add the entity as context to aid in token replacement.
      if ($this->tokenEntityMapper) {
        $data['params']['context'] = [
          'entity' => $entity,
          'user' => $this->currentUser,
          $this->tokenEntityMapper->getTokenTypeForEntityType($entity->getEntityTypeId()) => $entity,
        ];
      }
      else {
        $data['params']['context'] = [
          'entity' => $entity,
          'user' => $this->currentUser,
          $entity->getEntityTypeId() => $entity,
        ];
      }

      // Figure out who the email should be going to.
      $data['to'] = [];

      // Authors.
      if ($notification->author and ($entity instanceof EntityOwnerInterface)) {
        $data['to'][] = $entity->getOwner()->getEmail();
      }

      // Roles.
      foreach ($notification->getRoleIds() as $role) {
        /** @var \Drupal\Core\Entity\EntityStorageInterface $user_storage */
        $user_storage = $this->entityTypeManager->getStorage('user');
        if ($role === RoleInterface::AUTHENTICATED_ID) {
          $uids = \Drupal::entityQuery('user')
            ->condition('status', 1)
            ->accessCheck(FALSE)
            ->execute();
          /** @var \Drupal\user\UserInterface[] $role_users */
          $role_users = $user_storage->loadMultiple(array_filter($uids));
        }
        else {
          /** @var \Drupal\user\UserInterface[] $role_users */
          $role_users = $user_storage->loadByProperties(['roles' => $role]);
        }
        foreach ($role_users as $role_user) {
          $data['to'][] = $role_user->getEmail();
        }
      }

      // Adhoc emails.
      $adhoc_emails = array_map('trim', explode(',', preg_replace("/((\r?\n)|(\r\n?))/", ',', $notification->getEmails())));
      foreach ($adhoc_emails as $email) {
        $data['to'][] = $email;
      }

      // Let other modules to alter the email data.
      $this->moduleHandler->alter('content_moderation_notification_mail_data', $entity, $data);

      // Remove any null values that have crept in.
      $data['to'] = array_filter($data['to']);

      // Remove any duplicates.
      $data['to'] = array_unique($data['to']);

      // Force to BCC.
      $data['params']['headers']['Bcc'] = implode(',', $data['to']);

      $recipient = '';
      if (!$notification->disableSiteMail()) {
        $recipient = \Drupal::config('system.site')->get('mail');
      }
      if (!empty($data['params']['headers']['Bcc'])) {
        $this->mailManager->mail('content_moderation_notifications', 'content_moderation_notification', $recipient, $data['langcode'], $data['params'], NULL, TRUE);
      }
    }
  }

}
