<?php

namespace Drupal\purge_drush\Commands;

@trigger_error('The ' . __NAMESPACE__ . '\DebugCommands is deprecated. Instead, use \Drupal\purge\Commands\DebugCommands', E_USER_DEPRECATED);

use Drupal\purge\Commands\DebugCommands as DebugCommandsBase;

/**
 * Commands to help debugging caching and Purge.
 *
 * Note: This code has moved to Purge Core, see the parent class.
 *
 * @deprecated in Purge 8.x-1.x and will be removed before 2.0
 */
class DebugCommands extends DebugCommandsBase {}
