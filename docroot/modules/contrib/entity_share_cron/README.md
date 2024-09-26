# Entity Share Cron

## Introduction

This module allows configuring content to be automatically synchronized with
another Drupal site on Cron runs.

It extends the [Entity Share](https://www.drupal.org/project/entity_share)
module by allowing the user to select the Remotes and Channels that should be
automatically synchronized with Cron.

## Requirements

This module requires the following module:
* [Entity Share](https://www.drupal.org/project/entity_share)

## Installation

Install and enable this module like any other Drupal module.

## Configuration

Go to the configuration page (`admin/config/services/entity_share/cron`) and
enable one or more remotes and channels to be synchronized with Cron.

You may also change the time interval between synchronizations

The user must have the "Administer Entity Share Cron module" permission to
access this page.

## Maintainers

Current maintainers:
* [Daniel C. Biscalchin (dbiscalchin)](https://www.drupal.org/user/3081151)
* [Florent Torregrosa (Grimreaper)](https://www.drupal.org/user/2388214)
* [Ivan Vujović (ivan.vujovic)](https://www.drupal.org/user/382945)
* [Yarik Lutsiuk (yarik.lutsiuk)](https://www.drupal.org/user/3212333)

This project has been sponsored by:
* [Smile](https://www.drupal.org/smile): maintenance
