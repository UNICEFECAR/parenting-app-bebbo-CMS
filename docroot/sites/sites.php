<?php

// phpcs:ignoreFile

/**
 * @file
 * Configuration file for multi-site support and directory aliasing feature.
 *
 * This file is required for multi-site support and also allows you to define a
 * set of aliases that map hostnames, ports, and pathnames to configuration
 * directories in the sites directory. These aliases are loaded prior to
 * scanning for directories, and they are exempt from the normal discovery
 * rules. See default.settings.php to view how Drupal discovers the
 * configuration directory when no alias is found.
 *
 * Aliases are useful on development servers, where the domain name may not be
 * the same as the domain of the live server. Since Drupal stores file paths in
 * the database (files, system table, etc.) this will ensure the paths are
 * correct when the site is deployed to a live server.
 *
 * To activate this feature, copy and rename it such that its path plus
 * filename is 'sites/sites.php'.
 *
 * Aliases are defined in an associative array named $sites. The array is
 * written in the format: '<port>.<domain>.<path>' => 'directory'. As an
 * example, to map https://www.drupal.org:8080/mysite/test to the configuration
 * directory sites/example.com, the array should be defined as:
 * @code
 * $sites = [
 *   '8080.www.drupal.org.mysite.test' => 'example.com',
 * ];
 * @endcode
 * The URL, https://www.drupal.org:8080/mysite/test/, could be a symbolic link
 * or an Apache Alias directive that points to the Drupal root containing
 * index.php. An alias could also be created for a subdomain. See the
 * @link https://www.drupal.org/documentation/install online Drupal installation guide @endlink
 * for more information on setting up domains, subdomains, and subdirectories.
 *
 * The following examples look for a site configuration in sites/example.com:
 * @code
 * URL: http://dev.drupal.org
 * $sites['dev.drupal.org'] = 'example.com';
 *
 * URL: http://localhost/example
 * $sites['localhost.example'] = 'example.com';
 *
 * URL: http://localhost:8080/example
 * $sites['8080.localhost.example'] = 'example.com';
 *
 * URL: https://www.drupal.org:8080/mysite/test/
 * $sites['8080.www.drupal.org.mysite.test'] = 'example.com';
 * @endcode
 *
 * @see default.settings.php
 * @see \Drupal\Core\DrupalKernel::getSitePath()
 * @see https://www.drupal.org/documentation/install/multi-site
 */
$sites['babuni.app'] = 'bangladesh';
$sites['bangla.bebbo.app'] = 'bangladesh';
$sites['tr.bebbo.app'] = 'turkey';
$sites['ec.bebbo.app'] = 'ecuador';
$sites['merhababebek.app'] = 'turkey';
$sites['pc.bebbo.app'] = 'pacific_islands';
$sites['ws.bebbo.app'] = 'somoa';
$sites['rerai.umntwana.app'] = 'somoa';
$sites['wawamor.ec'] = 'ecuador';
$sites['zw.bebbo.app'] = 'zimbabwe';
$sites['bebbopacific.app'] = 'zimbabwe';

// Staging setup
$sites['bangla-stage.bebbo.app'] = 'bangladesh';
$sites['tr-stage.bebbo.app'] = 'turkey';
$sites['ec-stage.bebbo.app'] = 'ecuador';
$sites['pc-stage.bebbo.app'] = 'pacific_islands';
$sites['ws-stage.bebbo.app'] = 'somoa';
$sites['zw-stage.bebbo.app'] = 'zimbabwe';

// Dev setup
$sites['bangla-dev.bebbo.app'] = 'bangladesh';
$sites['tr-dev.bebbo.app'] = 'turkey';
$sites['ec-dev.bebbo.app'] = 'ecuador';
$sites['pc-dev.bebbo.app'] = 'pacific_islands';
$sites['ws-dev.bebbo.app'] = 'somoa';
$sites['zw-dev.bebbo.app'] = 'zimbabwe';

// ddev local setup
$sites['bangla.bebbo.app.ddev.site'] = 'bangladesh';
$sites['tr.bebbo.app.ddev.site'] = 'turkey';
$sites['ec.bebbo.app.ddev.site'] = 'ecuador';
$sites['pc.bebbo.app.ddev.site'] = 'pacific_islands';
$sites['ws.bebbo.app.ddev.site'] = 'somoa';
$sites['zw.bebbo.app.ddev.site'] = 'zimbabwe';
