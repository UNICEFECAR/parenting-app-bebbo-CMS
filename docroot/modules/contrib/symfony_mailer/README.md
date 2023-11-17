# Drupal Symfony Mailer

This module provides a new mail-system based on the popular
[Symfony Mailer library] giving full support of HTML mails, file attachments,
embedded images, 3rd-party delivery integrations, load-balancing/failover,
signing/encryption, async sending and more. Other libraries add capability for
CSS inlining and HTML to text conversion.

[Symfony Mailer library]: https://symfony.com/doc/current/mailer.html

- For a full description of the module, visit the [project page].
- To submit bug reports and feature suggestions, or to track changes, use the
  [issue queue].

[Project page]: https://www.drupal.org/project/symfony_mailer
[issue queue]: https://www.drupal.org/project/issues/symfony_mailer

This file provides a brief introduction. Readers are strongly encouraged to
read the [full documentation] that is regularly updated and expanded.

[Full documentation]: https://www.drupal.org/docs/contributed-modules/symfony-mailer-0

## Requirements

This module requires libraries which will be automatically installed by the
supported installation methods Composer or Ludwig. Manual installation is not
supported.

## Installation

- Install as you would normally install a contributed Drupal module. For further
  information, see _[Installing Drupal Modules]_.

[Installing Drupal Modules]: https://www.drupal.org/docs/extending-drupal/installing-modules

## Configuration

### Mailer Policy

This module provides a GUI to customise outgoing emails in many different ways.
Known as the Mailer Policy, it can be set at "Configuration » System » Mailer".

There are many possible policies to apply including: subject; body; addresses
(from, to, ...); theme, transport, convert to plain text. Each policy can be
set globally or for emails of a specific type.

### Mailer Transport

By default, Symfony Mailer uses the *sendmail* transport. You can configure a
different transport such as SMTP at "Configuration » System » Mailer »
Transport".

This module provides a GUI for the built-in [Symfony transports]. 3rd-party
transports can be configured using the "DSN" transport, and entering the DSN
directly.

[Symfony transports]: https://symfony.com/doc/current/mailer.html#transport-setup
