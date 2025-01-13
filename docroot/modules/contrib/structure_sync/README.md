# Structure Sync

- The Structure Sync module provides Drush commands and admin interface screens
  for synchronizing content that could also be considered configuration. Including
  menu items, custom blocks and taxonomy terms.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/structure_sync).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/structure_sync).


## Table of contents

- Requirements
- Installation
- Configuration
- Maintainers


## Requirements

This module requires no modules outside of Drupal core.


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

1. Navigate to `Administration > Extend` and enable the module.

**The available Drush commands are:**

- export-taxonomies (et) - Export taxonomy terms to configuration
- import-taxonomies (it) - Import taxonomy terms from configuration
- export-blocks (eb) - Export custom blocks to configuration
- import-blocks (ib) - Import custom blocks from configuration
- export-menus (em) - Export menu links to configuration
- import-menus (im) - Import menu links from configuration
- export-all (ea) - Export taxonomy terms, custom blocks and menu links to
- configuration
- import-all (ia) - Import taxonomy terms, custom blocks and menu links from
  configuration


2. The access to the admin screens is restricted with the permission to
'`Administer site configuration`'.

**The available admin interface screens are:**

- General options for this module - `/admin/structure/structure-sync/general`
- Import/export taxonomy  - `terms/admin/structure/structure-sync/taxonomies`
- Import/export custom blocks - `/admin/structure/structure-sync/blocks`
- Import/export menu links - `/admin/structure/structure-sync/menu-links`


## Maintainers

- Colan Schwartz - [colan](https://www.drupal.org/u/colan)
- Fido van den Bos - [fidovdbos](https://www.drupal.org/u/fidovdbos)
- Joachim Noreiko - [joachim](https://www.drupal.org/u/joachim)
- M Parker - [mparker17](https://www.drupal.org/u/mparker17)
- Derek Laventure - [spiderman](https://www.drupal.org/u/spiderman)
- Tim Kruijsen - [timKruijsen](https://www.drupal.org/u/timkruijsen)
- Vincent van den Berg - [vinlaurens](https://www.drupal.org/u/vinlaurens)
- Louis Cuny - [louis-cuny](https://www.drupal.org/u/louis-cuny)

**Supporting organization:**

- Ordina Digital Services - [https://www.drupal.org/u/fidodido06](https://www.drupal.org/u/fidodido06)
