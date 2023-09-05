<?php

namespace Drupal\shield;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Middleware for the shield module.
 */
class ShieldMiddleware implements HttpKernelInterface {

  /**
   * Constants representing if configured paths should be included or excluded.
   */
  const EXCLUDE_METHOD = 0;
  const INCLUDE_METHOD = 1;

  /**
   * Provide BC compatibility for Drupal 9 / Symfony 4.x.
   */
  const MAIN_REQUEST = 1;

  /**
   * The decorated kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The path matcher.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected $pathMatcher;

  /**
   * The path alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $pathAliasManager;

  /**
   * Language Manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Symfony\Component\HttpFoundation\RequestStack definition.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a ShieldMiddleware object.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The decorated kernel.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The EntityTypeManager service.
   * @param \Drupal\Core\Path\PathMatcherInterface $path_matcher
   *   The path matcher service.
   * @param \Drupal\path_alias\AliasManagerInterface $path_alias_manager
   *   The path alias manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   Language Manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack.
   */
  public function __construct(HttpKernelInterface $http_kernel,
                              ConfigFactoryInterface $config_factory,
                              EntityTypeManagerInterface $entity_type_manager,
                              PathMatcherInterface $path_matcher,
                              AliasManagerInterface $path_alias_manager,
                              LanguageManagerInterface $language_manager,
                              ModuleHandlerInterface $module_handler,
                              RequestStack $requestStack) {
    $this->httpKernel = $http_kernel;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->pathMatcher = $path_matcher;
    $this->pathAliasManager = $path_alias_manager;
    $this->languageManager = $language_manager;
    $this->moduleHandler = $module_handler;
    $this->requestStack = $requestStack;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MAIN_REQUEST, $catch = TRUE): Response {
    $config = $this->configFactory->get('shield.settings');

    // Bypass as shield is disabled.
    if (!$config->get('shield_enable')) {
      $request->headers->set('X-Shield-Status', 'disabled');
      return $this->bypass($request, $type, $catch);
    }

    // Bypass as it is a subrequest.
    if ($type != self::MAIN_REQUEST) {
      $request->headers->set('X-Shield-Status', 'skipped (subrequest)');
      return $this->bypass($request, $type, $catch);
    }

    // Bypass as shield is configured to allow CLI.
    if (PHP_SAPI === 'cli' && $config->get('allow_cli')) {
      $request->headers->set('X-Shield-Status', 'skipped (cli)');
      return $this->bypass($request, $type, $catch);
    }

    // Bypass as the path is allowed.
    if ($this->checkPathAllowed($request, $config)) {
      $request->headers->set('X-Shield-Status', 'skipped (path)');
      return $this->bypass($request, $type, $catch);
    }

    // Bypass as the HTTP method is allowed.
    if (!empty($config->get('http_method_allowlist')[strtolower($request->getMethod())])) {
      $request->headers->set('X-Shield-Status', 'skipped (http method)');
      return $this->bypass($request, $type, $catch);
    }

    // Bypass as the IP is allowed.
    if ($allowlist = $config->get('allowlist')) {
      $allowlist = array_filter(array_map('trim', explode("\n", $allowlist)));
      if (IpUtils::checkIp($request->getClientIp(), $allowlist)) {
        $request->headers->set('X-Shield-Status', 'skipped (ip)');
        return $this->bypass($request, $type, $catch);
      }
    }

    // Bypass as the domain is allowed.
    if ($domains = $config->get('domains')) {
      $domains = str_replace(' ', '', $domains);
      if (!empty($domains) && $this->pathMatcher->matchPath($request->getHost(), $domains)) {
        $request->headers->set('X-Shield-Status', 'skipped (domain)');
        return $this->bypass($request, $type, $catch);
      }
    }

    // Check if user has provided credentials.
    $user = NULL;
    switch ($config->get('credential_provider')) {
      case 'shield':
        $user = $config->get('credentials.shield.user');
        $pass = $config->get('credentials.shield.pass');
        break;

      case 'key':
        $user = $config->get('credentials.key.user');

        /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
        $storage = $this->entityTypeManager->getStorage('key');
        /** @var \Drupal\key\KeyInterface $pass_key */
        $pass_key = $storage->load($config->get('credentials.key.pass_key'));
        if ($pass_key) {
          $pass = $pass_key->getKeyValue();
        }
        break;

      case 'multikey':
        /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
        $storage = $this->entityTypeManager->getStorage('key');
        /** @var \Drupal\key\KeyInterface $user_pass_key */
        $user_pass_key = $storage->load($config->get('credentials.multikey.user_pass_key'));
        if ($user_pass_key) {
          $values = $user_pass_key->getKeyValues();
          $user = $values['username'];
          $pass = $values['password'];
        }
        break;
    }
    if ($request->server->has('PHP_AUTH_USER') && $request->server->has('PHP_AUTH_PW')) {
      $input_user = $request->server->get('PHP_AUTH_USER');
      $input_pass = $request->server->get('PHP_AUTH_PW');
    }
    elseif (!empty($request->server->get('HTTP_AUTHORIZATION'))) {
      [$input_user, $input_pass] = explode(':', base64_decode(substr($request->server->get('HTTP_AUTHORIZATION'), 6)), 2);
    }
    elseif (!empty($request->server->get('REDIRECT_HTTP_AUTHORIZATION'))) {
      [$input_user, $input_pass] = explode(':', base64_decode(substr($request->server->get('REDIRECT_HTTP_AUTHORIZATION'), 6)), 2);
    }
    if (isset($input_user) && $input_user === $user && hash_equals($pass, $input_pass)) {
      $request->headers->set('X-Shield-Status', 'authenticated');
      return $this->bypass($request, $type, $catch);
    }

    $response = new Response();
    $response->headers->add([
      'WWW-Authenticate' => 'Basic realm="' . strtr($config->get('print'), [
        '[user]' => $user,
        '[pass]' => $pass,
      ]) . '"',
    ]);
    if ($config->get('debug_header')) {
      $response->headers->add(['X-Shield-Status' => 'pending']);
    }

    $response->setStatusCode(401);
    return $response;
  }

  /**
   * Forward the request to the normal Kernel for processing.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param int $type
   *   The type of the request.
   * @param bool $catch
   *   Whether to catch exceptions or not.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A Response instance.
   *
   * @throws \Exception
   *   When an Exception occurs during processing.
   */
  public function bypass(Request $request, $type, $catch) {
    $basic_auth_enabled = $this->moduleHandler->moduleExists('basic_auth');
    $config = $this->configFactory->get('shield.settings');
    if ($basic_auth_enabled && $config->get('unset_basic_auth_headers') && !$this->basicAuthRequestAuthenticate($request)) {
      // Unset basic auth headers to prevent basic_auth trigger on
      // subsequent kernel calls.
      $request->headers->remove('PHP_AUTH_USER');
      $request->headers->remove('PHP_AUTH_PW');
    }

    return $this->httpKernel->handle($request, $type, $catch);
  }

  /**
   * Checks if the current path should be allowed to bypass shield.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The global request object.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The current Shield config.
   *
   * @return bool
   *   TRUE if the current path should be bypassed, and FALSE if not.
   */
  public function checkPathAllowed(Request $request, ImmutableConfig $config) {
    $paths_to_check = $config->get('paths');
    $method = $config->get('method');

    // If nothing specified in path config we can return early.
    if (empty($paths_to_check)) {
      if ($method == self::EXCLUDE_METHOD) {
        return FALSE;
      }
      elseif ($method == self::INCLUDE_METHOD) {
        return TRUE;
      }
    }

    $paths_to_check = str_replace(' ', '', $paths_to_check);

    $path = $request->getPathInfo();

    // Remove language code from url.
    foreach ($this->languageManager->getLanguages() as $language) {
      $langcode = $language->getId();
      if (substr($path, 0, strlen($langcode) + 1) === '/' . $langcode) {
        $path = str_replace('/' . $langcode . '/', '/', $path);
        break;
      }
    }

    // Remove trailing slash.
    $path = rtrim($path, '/');

    // Make it simple slash again for home page.
    $path = empty($path) ? '/' : $path;

    // Get alias of path.
    $path = $this->pathAliasManager->getAliasByPath($path);

    // Match the path using path matcher service against paths in config.
    $path_match = $this->pathMatcher->matchPath($path, $paths_to_check);

    return $path_match && $method == self::EXCLUDE_METHOD || !$path_match && $method == self::INCLUDE_METHOD;
  }

  /**
   * Check if current request authenticated with basic auth.
   *
   * Call this if basic_auth is enabled.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object on which headers will be modified.
   */
  private function basicAuthRequestAuthenticate(Request $request) {
    /** @var \Drupal\basic_auth\Authentication\Provider\BasicAuth $basicAuthService */
    $basicAuthService = \Drupal::service('basic_auth.authentication.basic_auth');
    // Check for empty username and password in case of shield disabled.
    if ($basicAuthService->applies($request)) {
      // We need to push the current request to the request stack because
      // basic_auth uses a flood functionality which needs the client IP.
      $this->requestStack->push($request);
      try {
        $authenticate = $basicAuthService->authenticate($request);
      } catch (\Exception $e) {
        // Do nothing.
      }
      if ($authenticate) {
        // Reset request stack, as we don't need it anymore.
        $this->requestStack->pop();
        return TRUE;
      }
      $this->requestStack->pop();
    }
    return FALSE;
  }

}
