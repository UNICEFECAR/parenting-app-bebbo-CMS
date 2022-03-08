<?php

/**
 * @file
 * Post update functions for Shield.
 */

/**
 * Rebuild caches to ensure schema changes are read in.
 */
function shield_post_update_domain_allowlist() {
  // Empty update to cause a cache rebuild so that the schema changes are read.
}
