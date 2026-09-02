<?php

namespace Drupal\bebbo_custom_general\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupMembership;

/**
 * Sends the acting user to their own country's member list.
 *
 * The editorial menu used to hardcode a group ID in this link, which meant the
 * same menu could not be shared across sites: group IDs are per-site
 * auto-increment values, so one number points at a different country on every
 * site, or at nothing at all. Resolving the group at request time lets a
 * single link work everywhere.
 */
class CountryUsersController extends ControllerBase {

  /**
   * Redirects to the member list of the user's country group.
   *
   * A user belonging to exactly one group lands on that group's member list.
   * Anyone else — no membership, or several — goes to the group overview,
   * which already lists every group they may reach.
   *
   * @return \Drupal\Core\Routing\LocalRedirectResponse
   *   Redirect to the member list, or to the group overview.
   */
  public function redirectToCountry(): LocalRedirectResponse {
    $memberships = GroupMembership::loadByUser($this->currentUser());

    $url = count($memberships) === 1
      ? Url::fromRoute('view.group_members.page_1', ['group' => reset($memberships)->getGroupId()])
      : Url::fromRoute('entity.group.collection');

    $response = new LocalRedirectResponse($url->toString());
    // The destination differs per user, and joining or leaving a group has to
    // change it straight away.
    $response->getCacheableMetadata()
      ->addCacheContexts(['user'])
      ->setCacheMaxAge(0);

    return $response;
  }

}
