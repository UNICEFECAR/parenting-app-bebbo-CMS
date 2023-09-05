<?php

namespace Drupal\json_field;

use Drupal\Core\Database\Connection;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Implements Json Field Library service.
 */
class JsonFieldRequirements implements JsonFieldRequirementsInterface {
  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * The drupal root path.
   *
   * @var string
   */
  private $root;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * {@inheritdoc}
   */
  public function __construct($root, Connection $connection) {
    $this->root = $root;
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public function libraryIsAvailable(bool $warning = FALSE) {
    $library_paths = $this->getPaths();
    $exists = file_exists($this->root . $library_paths[0]) && file_exists($this->root . $library_paths[1]) ? TRUE : FALSE;
    if (!$exists) {
      if ($warning) {
        $this->messenger()->addWarning($this->getWarningMessage());
      }

      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraryWarningMessage() {
    return $this->t('The <a href=":url">jQuery JSONView</a> library is used when displaying JSON fields. It is not currently available. To use the formatter properly the library should be downloaded and copied to the <strong>/libraries</strong> directory so that these two file paths exist: <strong>/libraries/jquery-jsonview/dist/jquery.jsonview.css</strong> and <strong>/libraries/jquery-jsonview/dist/jquery.jsonview.js</strong>.', [
      ':url'  => 'https://github.com/yesmeck/jquery-jsonview',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function databaseIsCompatible() {
    $driver = $this->connection->driver();
    $minimum_version_required = $this->getDriverMinimumRequirementVersion($driver);
    $version_query = 'SELECT VERSION()';
    if ($driver == 'pgsql') {
      $version_query = 'SHOW server_version';
    }
    elseif ($driver === 'sqlite') {
        $version_query = 'select sqlite_version()';
    }
    $driver_version = $this->connection->query($version_query)->fetchCol();
    $is_compatible = version_compare($driver_version[0], $minimum_version_required);
    return !empty($is_compatible) && $is_compatible != -1 ? TRUE : FALSE;
  }

  /**
   * Get driver minimum requirement version.
   *
   * @param string $driver
   *   The curent aplication database driver.
   *
   * @return string
   *   The database driver version or an empty string.
   */
  public function getDriverMinimumRequirementVersion($driver) {
    $driver_compatibility = [
      'mysql' => '5.7.8',
      'mariadb' => '10.2.7',
      'pgsql' => '9.2',
      'sqlite' => '3.26',
    ];
    if (isset($driver_compatibility[$driver])) {
      return $driver_compatibility[$driver];
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getDatabaseWarningMessage() {
    return $this->t('Verify if your database supports the JSON data type so the json_field module can work properly.');
  }

  /**
   * Returns the expected js and css library path.
   *
   * @return array
   *   An array the contains the library path.
   */
  private function getPaths() {
    return [
      '/libraries/jquery-jsonview/dist/jquery.jsonview.css',
      '/libraries/jquery-jsonview/dist/jquery.jsonview.js',
    ];
  }

}
