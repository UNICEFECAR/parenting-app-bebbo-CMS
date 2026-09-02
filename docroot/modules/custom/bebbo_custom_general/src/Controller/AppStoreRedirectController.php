<?php

namespace Drupal\bebbo_custom_general\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableRedirectResponse;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the app store redirect page at /downloadapp.html.
 */
class AppStoreRedirectController extends ControllerBase {

  /**
   * Renders the redirect HTML page.
   */
  public function render(): Response {
    $config = $this->config('bebbo_custom_general.app_store_redirect');
    $app_store_url = $config->get('app_store_url') ?: '';
    $google_play_url = $config->get('google_play_url') ?: '';

    $homepage_url = Url::fromRoute('<front>')->setAbsolute()->toString();

    $cache_metadata = new CacheableMetadata();
    $cache_metadata->addCacheTags([
      'config:bebbo_custom_general.app_store_redirect',
      'config:system.site',
    ]);
    $cache_metadata->setCacheMaxAge(3600);

    if (empty($app_store_url) && empty($google_play_url)) {
      $response = new CacheableRedirectResponse($homepage_url, 302);
      $response->addCacheableDependency($cache_metadata);
      return $response;
    }

    $site_name = Html::escape($this->config('system.site')->get('name') ?: 'Bebbo');
    $safe_app_store = Html::escape($app_store_url ?: $homepage_url);
    $safe_google_play = Html::escape($google_play_url ?: $homepage_url);
    $safe_homepage = Html::escape($homepage_url);

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Download {$site_name} app</title>
  <script>
    const ua = navigator.userAgent
    if (/android/i.test(ua)) {
      location.replace('{$safe_google_play}')
    } else if (/iphone|ipad|ipod/i.test(ua)) {
      location.replace('{$safe_app_store}')
    } else {
      location.replace('{$safe_homepage}')
    }
  </script>
</head>
<body>
  <noscript>
    <p>
      JavaScript is required for automatic redirection.<br>
      Please visit our <a href="{$safe_homepage}">landing page</a>.
    </p>
  </noscript>
  <p style="display:none;" id="fallback-link">
    If you are not redirected, please visit our <a href="{$safe_homepage}">landing page</a>.
  </p>
  <script>
    setTimeout(function() {
      document.getElementById('fallback-link').style.display = 'block';
    }, 5000);
  </script>
</body>
</html>
HTML;

    $response = new CacheableResponse($html, 200, [
      'Content-Type' => 'text/html; charset=UTF-8',
    ]);
    $response->addCacheableDependency($cache_metadata);

    return $response;
  }

}
