<?php

namespace Drupal\mobile_app_links;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class MobileAppLinksPathProcessor.
 *
 * @package Drupal\mobile_app_links
 */
class MobileAppLinksPathProcessor implements InboundPathProcessorInterface, OutboundPathProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    if (strpos($path, '.well-known') > -1) {
      $request->attributes->set('_disable_route_normalizer', 1);
    }

    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [],
                                  Request $request = NULL,
                                  BubbleableMetadata $bubbleable_metadata = NULL) {

    if (strpos($path, '.well-known') > -1) {
      $options['language'] = NULL;
    }

    return $path;
  }

}
