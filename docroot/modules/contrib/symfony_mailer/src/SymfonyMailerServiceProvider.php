<?php

namespace Drupal\symfony_mailer;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Modifies the mail manager service.
 */
class SymfonyMailerServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $definition = $container->getDefinition('plugin.manager.mail');
    // Cancel any method calls, for example from mailsystem.
    $definition->setClass('Drupal\symfony_mailer\MailManagerReplacement')
      ->addArgument(new Reference('email_factory'))
      ->addArgument(new Reference('plugin.manager.email_builder'))
      ->addArgument(new Reference('symfony_mailer.legacy_helper'))
      ->setMethodCalls([]);
  }

}
