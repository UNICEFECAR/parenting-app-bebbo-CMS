<?php

namespace Drupal\language_visibility_control\EventSubscriber;

use Drupal\group\Entity\Group;
use Drupal\language_visibility_control\LanguageVisibilityService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber to modify API responses for language visibility.
 */
class ApiResponseSubscriber implements EventSubscriberInterface {

  /**
   * The language visibility service.
   *
   * @var \Drupal\language_visibility_control\LanguageVisibilityService
   */
  protected $languageVisibilityService;

  /**
   * Constructs an ApiResponseSubscriber object.
   *
   * @param \Drupal\language_visibility_control\LanguageVisibilityService $language_visibility_service
   *   The language visibility service.
   */
  public function __construct(LanguageVisibilityService $language_visibility_service) {
    $this->languageVisibilityService = $language_visibility_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::RESPONSE => ['onResponse', -10],
    ];
  }

  /**
   * Modifies API responses to filter languages based on visibility settings.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function onResponse(ResponseEvent $event) {
    $request = $event->getRequest();
    $response = $event->getResponse();

    // Only process API requests for country-groups.
    if (strpos($request->getPathInfo(), '/api/country-groups') === FALSE) {
      return;
    }

    $content = $response->getContent();
    if (empty($content)) {
      return;
    }

    $data = json_decode($content, TRUE);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['data'])) {
      return;
    }

    // Filter languages for each country group.
    foreach ($data['data'] as &$country_data) {
      if (isset($country_data['CountryID']) && isset($country_data['languages'])) {
        // Skip language visibility filtering for "Rest of the World".
        if ($country_data['CountryID'] == '126') {
          continue;
        }

        $group = Group::load($country_data['CountryID']);
        if ($group) {
          $country_data['languages'] = $this->languageVisibilityService->filterLanguageDataForApi(
            $country_data['languages'],
            $group
          );
        }
      }
    }

    $response->setContent(json_encode($data));
  }

}
