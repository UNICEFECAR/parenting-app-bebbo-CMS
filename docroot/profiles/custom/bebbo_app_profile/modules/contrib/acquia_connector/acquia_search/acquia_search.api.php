<?php

/**
 * @file
 * Hooks specific to the acquia_search module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the possible cores list.
 *
 * @param array $possible_core_ids
 *   The predefined list of possible cores.
 * @param array $context
 *   Context.
 *
 * @code
 *   $possible_core_ids = [
 *     'WXYZ-12345.prod.default',
 *     'WXYZ-12345.dev.mysitedev_folder1',
 *     'WXYZ-12345.dev.mysitedev_db',
 *   ];
 *   $context = [
 *     'ah_env' => 'dev',                // string|null
 *     'ah_db_role' => 'SomeDb1,         // string
 *     'identifier' => 'WXYZ-12345',     // string, may be empty
 *     'sites_foldername' => 'default',  // string
 *   ];
 * @endcode
 */
function hook_acquia_search_get_list_of_possible_cores_alter(array &$possible_core_ids, array $context) {
  if (empty($context['ah_env'])) {
    $possible_core_ids[] = 'WXYZ-12345.dev.mysitedev_db';
  }
}

/**
 * @} End of "addtogroup hooks".
 */
