<?php
// phpcs:ignoreFile
/**
 * @file
 * A database agnostic dump for testing purposes.
 */

use Drupal\Core\Database\Database;

$connection = Database::getConnection();

$connection->schema()->createTable('system', array(
  'fields' => array(
    'filename' => array(
      'type' => 'varchar',
      'not null' => TRUE,
      'length' => '255',
      'default' => '',
    ),
    'name' => array(
      'type' => 'varchar',
      'not null' => TRUE,
      'length' => '255',
      'default' => '',
    ),
    'type' => array(
      'type' => 'varchar',
      'not null' => TRUE,
      'length' => '12',
      'default' => '',
    ),
    'owner' => array(
      'type' => 'varchar',
      'not null' => TRUE,
      'length' => '255',
      'default' => '',
    ),
    'status' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'default' => '0',
    ),
    'bootstrap' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'default' => '0',
    ),
    'schema_version' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'small',
      'default' => '-1',
    ),
    'weight' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'default' => '0',
    ),
    'info' => array(
      'type' => 'blob',
      'not null' => FALSE,
      'size' => 'normal',
    ),
  ),
  'primary key' => array(
    'filename',
  ),
  'indexes' => array(
    'system_list' => array(
      'status',
      'bootstrap',
      'type',
      'weight',
      'name',
    ),
    'type_name' => array(
      'type',
      'name',
    ),
  ),
  'mysql_character_set' => 'utf8mb3',
));

$connection->insert('system')
->fields(array(
  'filename',
  'name',
  'type',
  'owner',
  'status',
  'bootstrap',
  'schema_version',
  'weight',
  'info',
))
->values(array(
  'filename' => 'modules/system/system.module',
  'name' => 'system',
  'type' => 'module',
  'owner' => '',
  'status' => '1',
  'bootstrap' => '0',
  'schema_version' => '7084',
  'weight' => '0',
  'info' => 'a:14:{s:4:"name";s:6:"System";s:11:"description";s:54:"Handles general site configuration for administrators.";s:7:"package";s:4:"Core";s:7:"version";s:4:"7.82";s:4:"core";s:3:"7.x";s:5:"files";a:6:{i:0;s:19:"system.archiver.inc";i:1;s:15:"system.mail.inc";i:2;s:16:"system.queue.inc";i:3;s:14:"system.tar.inc";i:4;s:18:"system.updater.inc";i:5;s:11:"system.test";}s:8:"required";b:1;s:9:"configure";s:19:"admin/config/system";s:7:"project";s:6:"drupal";s:9:"datestamp";s:10:"1626883669";s:5:"mtime";i:1626883669;s:12:"dependencies";a:0:{}s:3:"php";s:5:"5.2.4";s:9:"bootstrap";i:0;}',
))
->values(array(
  'filename' => 'sites/all/modules/contrib/shield/shield.module',
  'name' => 'shield',
  'type' => 'module',
  'owner' => '',
  'status' => '1',
  'bootstrap' => '1',
  'schema_version' => '7001',
  'weight' => '0',
  'info' => 'a:11:{s:4:"name";s:6:"Shield";s:11:"description";s:57:"Creates a general PHP Authentication shield for the site.";s:4:"core";s:3:"7.x";s:9:"configure";s:26:"admin/config/system/shield";s:5:"mtime";i:1639047351;s:12:"dependencies";a:0:{}s:7:"package";s:5:"Other";s:7:"version";N;s:3:"php";s:5:"5.2.4";s:5:"files";a:0:{}s:9:"bootstrap";i:0;}',
))
->execute();

$connection->schema()->createTable('variable', array(
  'fields' => array(
    'name' => array(
      'type' => 'varchar',
      'not null' => TRUE,
      'length' => '128',
      'default' => '',
    ),
    'value' => array(
      'type' => 'blob',
      'not null' => TRUE,
      'size' => 'big',
    ),
  ),
  'primary key' => array(
    'name',
  ),
  'mysql_character_set' => 'utf8mb3',
));

$connection->insert('variable')
->fields(array(
  'name',
  'value',
))
->values(array(
  'name' => 'shield_allow_cli',
  'value' => 'i:0;',
))
->values(array(
  'name' => 'shield_enabled',
  'value' => 'i:1;',
))
->values(array(
  'name' => 'shield_ignored_addresses',
  'value' => 's:12:"192.168.0.75";',
))
->values(array(
  'name' => 'shield_method',
  'value' => 's:1:"1";',
))
->values(array(
  'name' => 'shield_pass',
  'value' => 's:4:"1234";',
))
->values(array(
  'name' => 'shield_paths',
  'value' => "s:14:\"node/3\r\nblog/*\";",
))
->values(array(
  'name' => 'shield_print',
  'value' => 's:13:"Authenticate!";',
))
->values(array(
  'name' => 'shield_remote_address',
  'value' => 's:11:"REMOTE_ADDR";',
))
->values(array(
  'name' => 'shield_user',
  'value' => 's:3:"abc";',
))
->execute();
