## CONTENTS OF THIS FILE

- Introduction
- Features
- Requirements
- Installation
- Configuration
- Maintainers

## INTRODUCTION

TB Mega Menu provides a drag and drop interface for creating drop down menus
that combine Drupal menu items with rich media content. Your menu can include
internal and external links, images, videos and anything else that can appear
in a Drupal block.

Version 2.x includes the following changes from the 8.x version:

- Added source SCSS.
- Removed dependencies on Bootstrap, Chosen and jQuery.
- Removed support for menu themes.
- Simplified keyboard nav.
  - Home/End listeners removed.
  - Left/Right behave the same as Tab key.
- Added support for clickable dropdowns to show/hide submenus.
- Removed fontawesome library and added optional dependency on the fontawesome module.
- Submenus take up the full width on the nav container by default.

## FEATURES

- Synchronizes with Drupal core menus
- Allows Drupal blocks to be added to the menu
- Drag and drop administrative interface
- Accessibility features for keyboard navigation and screen readers
- Custom styling available for menu items
- Multiple built-in CSS3 animation effects
- Responsive ready

## REQUIREMENTS

- This module has no dependencies.

## INSTALLATION

- Install as you would normally install a contributed Drupal module.
  See: https://www.drupal.org/docs/extending-drupal/installing-modules
  for further information.

- Visit: https://www.drupal.org/project/tb_megamenu/git-instructions
  for cloning the git repository.

## CONFIGURATION

- Navigate to Extend and enable the TB Mega Menu module.
- Navigate to Structure -> TB Mega Menu to create a new menu.

## MAINTAINERS

- Wade Stewart ([themodularlab](https://www.drupal.org/u/themodularlab))
- Andy Olson ([andrewozone](https://www.drupal.org/u/andrewozone))
- Michael Girgis ([knaffles](https://www.drupal.org/u/knaffles))
- This module is currently owned and maintained by
  [Bounteous](https://www.bounteous.com)
- This module was originally developed by ThemeBrain.

## CHANGELOG

- Follows Keep a Changelog as a guide for managing and
  maintaining changelog. \* see https://keepachangelog.com/en/1.0.0/
- Changelog managed as markdown (.md) file.
- Convert to HTML for Drupal Release Notes.
  - See https://markdowntohtml.com/
- Changle log Structure:
  - Unreleased (dev branch).
  - Releases (version & date, categorized by type)
- Date format: YEAR-Month-Day or YYYY-MM-DD
- Everything else is Release notes, organized by type of change
  - **Added** for new features.
  - **Changed** for changes in existing functionality.
  - **Deprecated** for soon-to-be removed features.
  - **Removed** for now removed features.
  - **Fixed** for any bug fixes.
  - **Security** in case of vulnerabilities.
- Other Guides:
  _ Changelogs are for humans, not machines.
  _ There should be an entry for every single version.
  _ The same types of changes should be grouped.
  _ Versions and sections should be linkable.
  _ The latest version comes first.
  _ The release date of each version is displayed.
  Mention whether you follow Semantic Versioning.

## FRONT END

To build the front-end assets:

1. Install NVM (see https://github.com/nvm-sh/nvm#installing-and-updating).
2. Run `nvm install 10.19.0`. You only need to do this the first time.
3. Run `nvm use`.
4. Run `yarn install`.
5. Run `yarn develop`.
