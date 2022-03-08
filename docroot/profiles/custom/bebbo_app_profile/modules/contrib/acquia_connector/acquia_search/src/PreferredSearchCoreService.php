<?php

namespace Drupal\acquia_search;

/**
 * Class PreferredSearchCoreService.
 *
 * @package Drupal\acquia_search\
 */
class PreferredSearchCoreService {

  /**
   * Acquia subscription identifier.
   *
   * @var string
   */
  protected $acquiaIdentifier;

  /**
   * Acquia environment.
   *
   * @var string
   */
  protected $ahEnv;

  /**
   * Sites folder name.
   *
   * @var string
   */
  protected $sitesFolderName;

  /**
   * Site DB name.
   *
   * @var string
   */
  protected $ahDbName;

  /**
   * Available search cores.
   *
   * @var array
   */
  protected $availableCores;

  /**
   * ExpectedCoreService constructor.
   *
   * @param string $acquia_identifier
   *   E.g. 'WXYZ-12345'.
   * @param string $ah_env
   *   E.g. 'dev', 'stage' or 'prod'.
   * @param string $sites_folder_name
   *   E.g. 'default'.
   * @param string $ah_db_name
   *   E.g. 'my_site_db'.
   * @param array $available_cores
   *   E.g.
   *     [
   *       [
   *         'balancer' => 'useast11-c4.acquia-search.com',
   *         'core_id' => 'WXYZ-12345.dev.mysitedev',
   *       ],
   *     ].
   */
  public function __construct($acquia_identifier, $ah_env, $sites_folder_name, $ah_db_name, array $available_cores) {

    $this->acquiaIdentifier = $acquia_identifier;
    $this->ahEnv = $ah_env;
    $this->sitesFolderName = $sites_folder_name;
    $this->ahDbName = $ah_db_name;
    $this->availableCores = $available_cores;

  }

  /**
   * Returns expected core ID based on the current site configs.
   *
   * @return string
   *   Core ID.
   */
  public function getPreferredCoreId() {

    $core = $this->getPreferredCore();

    return $core['core_id'];

  }

  /**
   * Returns expected core host based on the current site configs.
   *
   * @return string
   *   Hostname.
   */
  public function getPreferredCoreHostname() {

    $core = $this->getPreferredCore();

    return $core['balancer'];
  }

  /**
   * Determines whether the expected core ID matches any available core IDs.
   *
   * The list of available core IDs is set by Acquia and comes within the
   * Acquia Subscription information.
   *
   * @return bool
   *   True if the expected core ID available to use with Acquia.
   */
  public function isPreferredCoreAvailable() {

    return (bool) $this->getPreferredCore();

  }

  /**
   * Returns the preferred core from the list of available cores.
   *
   * @return array|null
   *   NULL or
   *     [
   *       'balancer' => 'useast11-c4.acquia-search.com',
   *       'core_id' => 'WXYZ-12345.dev.mysitedev',
   *     ].
   */
  public function getPreferredCore() {
    static $preferred_core;

    if (!empty($preferred_core)) {
      return $preferred_core;
    }

    $expected_cores = $this->getListOfPossibleCores();
    $available_cores_sorted = $this->sortCores($this->availableCores);

    foreach ($expected_cores as $expected_core) {

      foreach ($available_cores_sorted as $available_core) {

        if ($expected_core == $available_core['core_id']) {
          $preferred_core = $available_core;
          return $preferred_core;
        }

      }

    }
  }

  /**
   * Sorts and returns search cores.
   *
   * It puts v3 cores first.
   *
   * @param array $cores
   *   Unsorted array of search cores.
   *
   * @return array
   *   Array of search cores. V3 cores in the begging of the result array.
   */
  protected function sortCores(array $cores) {

    $v3_cores = array_filter($cores, function ($core) {
      return $this->isCoreV3($core);
    });

    $regular_cores = array_filter($cores, function ($core) {
      return !$this->isCoreV3($core);
    });

    return array_merge($v3_cores, $regular_cores);
  }

  /**
   * Determines whether given search core is version 3.
   *
   * @param array $core
   *   Search core.
   *
   * @return bool
   *   TRUE if the given search core is V3, FALSE otherwise.
   */
  protected function isCoreV3(array $core) {
    return !empty($core['version']) && $core['version'] === 'v3';
  }

  /**
   * Returns URL for the preferred search core.
   *
   * @return string
   *   URL string, e.g.
   *   http://useast1-c1.acquia-search.com/solr/WXYZ-12345.dev.mysitedev
   */
  public function getPreferredCoreUrl() {

    $core = $this->getPreferredCore();

    return 'http://' . $core['balancer'] . '/solr/' . $core['core_id'];

  }

  /**
   * Returns a list of all possible search core IDs.
   *
   * The core IDs are generated based on the current site configuration.
   *
   * @return array
   *   E.g.
   *     [
   *       'WXYZ-12345',
   *       'WXYZ-12345.dev.mysitedev_folder1',
   *       'WXYZ-12345.dev.mysitedev_db',
   *     ]
   */
  public function getListOfPossibleCores() {

    $possible_core_ids = [];

    // The Acquia Search Solr module tries to use this core before any auto
    // detected core in case if it's set in the site configuration.
    if ($default_search_core = \Drupal::config('acquia_search.settings')->get('default_search_core')) {
      $possible_core_ids[] = $default_search_core;
    }

    // In index naming, we only accept alphanumeric chars.
    $sites_foldername = preg_replace('/[^a-zA-Z0-9]+/', '', $this->sitesFolderName);
    $ah_env = preg_replace('/[^a-zA-Z0-9]+/', '', $this->ahEnv);

    $context = [
      'ah_env' => $ah_env,
      'ah_db_role' => $this->ahDbName,
      'identifier' => $this->acquiaIdentifier,
      'sites_foldername' => $sites_foldername,
    ];

    if ($ah_env) {

      // When there is an Acquia DB name defined, priority is to pick
      // WXYZ-12345.[env].[db_name], then WXYZ-12345.[env].[site_foldername].
      // If we're sure this is prod, then 3rd option is WXYZ-12345.
      // @TODO: Support for [id]_[env][sitename] cores?
      if ($this->ahDbName) {
        $possible_core_ids[] = $this->acquiaIdentifier . '.' . $ah_env . '.' . $this->ahDbName;
      }

      $possible_core_ids[] = $this->acquiaIdentifier . '.' . $ah_env . '.' . $sites_foldername;

    }

    // For production-only, we allow auto-connecting to the suffix-less core
    // as the fallback.
    if (!empty($_SERVER['AH_PRODUCTION']) || !empty($_ENV['AH_PRODUCTION'])) {
      $possible_core_ids[] = $this->acquiaIdentifier;
    }

    // Let other modules alter the list possible cores.
    \Drupal::moduleHandler()->alter('acquia_search_get_list_of_possible_cores', $possible_core_ids, $context);

    return $possible_core_ids;

  }

}
