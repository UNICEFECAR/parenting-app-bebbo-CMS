<?php

/**
 * @file
 * Contains \Drupal\block\Controller\CategoryAutocompleteController.
 */

namespace Drupal\tmgmt_google_test\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns autocomplete responses for block categories.
 */
class GoogleTranslatorTestController {

  /**
   * Mock service to get available languages.
   *
   * @param Request $request
   *
   * @return JsonResponse
   */
  public function availableLanguages(Request $request): JsonResponse {
    if ($response = $this->validateKey($request)) {
      return $response;
    }

    $response = array(
      'data' => array(
        'languages' => array(
          array('language' => 'en'),
          array('language' => 'de'),
          array('language' => 'fr'),
        ),
      ),
    );

    return new JsonResponse($response);
  }

  /**
   * Key validator helper.
   *
   * @param Request $request
   *
   * @return JsonResponse|void
   */
  protected function validateKey(Request $request) {
    if ($request->get('key') != 'correct key') {
      return $this->trigger_response_error('usageLimits', 'keyInvalid', 'Bad Request');
    }
  }

  /**
   * Helper to trigger mok response error.
   *
   * @param string $domain
   * @param string $reason
   * @param string $message
   * @param string $locationType
   * @param string $location
   *
   * @return JsonResponse
   */
  public function trigger_response_error(string $domain, string $reason, string $message, string $locationType = '', string $location = ''): JsonResponse {

    $response = array(
      'error' => array(
        'errors' => array(
          'domain' => $domain,
          'reason' => $reason,
          'message' => $message,
        ),
        'code' => 400,
        'message' => $message,
      ),
    );

    if (!empty($locationType)) {
      $response['error']['errors']['locationType'] = $locationType;
    }
    if (!empty($location)) {
      $response['error']['errors']['location'] = $location;
    }

    return new JsonResponse($response);
  }

  /**
   * Mok service to translate request.
   *
   * @param Request $request
   *
   * @return JsonResponse
   */
  public function translate(Request $request): JsonResponse {
    if ($response = $this->validateKey($request)) {
      return $response;
    }

    if (!$request->isMethod('POST')) {
      return $this->trigger_response_error('global', 'method', 'Request must be POST method');
    }
    $content = $request->getContent();
    if (empty($content)) {
      return $this->trigger_response_error('global', 'content', 'Request content must not be empty.');
    }
    $decoded = json_decode($content, TRUE);
    if (empty($decoded['q'][0]) || !str_starts_with($decoded['q'][0], 'Text for job item with type')) {
      return $this->trigger_response_error('global', 'content', 'Request content must contain translation string.');
    }
    if (!$request->query->has('source')) {
      return $this->trigger_response_error('global', 'required', 'Required parameter: source', 'parameter', 'source');
    }
    $target = $request->query->get('target');
    if (!$target) {
      return $this->trigger_response_error('global', 'required', 'Required parameter: target', 'parameter', 'target');
    }

    $translations = array(
      'de' => 'Hallo Welt &amp; willkommen',
      'fr' => 'Bonjour tout le monde',
    );

    $response = array(
      'data' => array(
        'translations' => array(
          array('translatedText' => $translations[$target]),
        ),
      ),
    );

    return new JsonResponse($response);
  }

}
