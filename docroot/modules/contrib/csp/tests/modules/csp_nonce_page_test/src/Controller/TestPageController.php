<?php

namespace Drupal\csp_nonce_page_test\Controller;

/**
 * Controller routines for test_page_test routes.
 */
class TestPageController {

  /**
   * Returns a test page and sets the title.
   */
  public function testNoNonce() {
    return [
      '#title' => t('Test page'),
      '#markup' => t('Test page text.'),
    ];
  }

  /**
   * Returns a test page and sets the title.
   */
  public function testNonce() {
    return [
      '#title' => t('Test page'),
      '#markup' => t('Test page text.'),
      '#attached' => [
        'library' => ['csp/nonce'],
      ],
    ];
  }

}
