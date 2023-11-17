<?php

namespace Drupal\symfony_mailer;

use Drupal\Core\Site\Settings;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\SendmailTransportFactory;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Provides a replacement sendmail transport factory that checks the command.
 */
final class ReplacementSendmailTransportFactory extends AbstractTransportFactory {

  /**
   * {@inheritdoc}
   */
  public function create(Dsn $dsn): TransportInterface {
    if ($command = $dsn->getOption('command')) {
      $commands = Settings::get('mailer_sendmail_commands', []);
      if (!in_array($command, $commands)) {
        throw new \RuntimeException("Unsafe sendmail command {$command}");
      }
    }

    return (new SendmailTransportFactory())->create($dsn);
  }

  /**
   * {@inheritdoc}
   */
  protected function getSupportedSchemes(): array {
    return ['sendmail', 'sendmail+smtp'];
  }

}
