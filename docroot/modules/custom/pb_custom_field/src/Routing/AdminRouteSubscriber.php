<?php

declare(strict_types=1);

namespace Drupal\pb_custom_field\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Marks editorial Views pages as admin routes to use the admin theme.
 *
 * Many Views pages live outside /admin/* but are editorial interfaces.
 * Before the Claro-to-Gin migration both themes were Claro, hiding this.
 * Now that admin=Gin and default=Claro, these pages need _admin_route.
 */
class AdminRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    $admin_routes = [
      // Dashboard.
      'view.dashboard_recent_content.page_1',
      // Content listings.
      'view.content_listing.page_1',
      'view.country_content_listing.page_5',
      'view.global_content_listing.page_4',
      'view.global_content_listing_country_users.page_4',
      'view.articlescontentlist.page_1',
      'view.my_language_content.page_1',
      // Reports.
      'view.country_reports.page_5',
      'view.global_reports.page_4',
      'view.child_growth_reports.page_5',
      'view.users_reports.page_2',
      // CSV exports.
      'view.content_export_csv.page_1',
      'view.content_export_csv.page_2',
      'view.content_export_csv.page_3',
      'view.content_export_csv.page_4',
      'view.taxonomy_export.page_1',
      'view.taxonomy_export_standard_deviation.page_1',
      // Groups.
      'view.group_members.page_1',
      'view.duplicate_of_moderated_group_relationship.moderated_content',
      // Users.
      'view.user_admin_people.page_2',
      'view.users_list.page_2',
      // Keywords.
      'view.keyword_term_count.page_1',
      'view.keyword_term_count.page_2',
      'view.keyword_term_count.page_3',
      'view.keyword_term_count.page_4',
      'view.keyword_term_count.page_5',
      // Admin tools.
      'view.force_update_check.page_1',
      // TMGMT translation management.
      'view.tmgmt_local_manage_translate_task.unassigned',
      'view.tmgmt_local_manage_translate_task.assigned',
      'view.tmgmt_local_manage_translate_task.closed',
      'view.tmgmt_local_manage_translate_task.completed',
      'view.tmgmt_local_manage_translate_task.pending',
      'view.tmgmt_local_manage_translate_task.rejected',
      'view.tmgmt_local_task_overview.unassigned',
      'view.tmgmt_local_task_overview.pending',
      'view.tmgmt_local_task_overview.completed',
      'view.tmgmt_local_task_overview.closed',
      'view.tmgmt_local_task_overview.rejected',
      'view.tmgmt_local_task_overview.eligible',
      'view.tmgmt_local_task_overview.my_tasks',
    ];

    foreach ($admin_routes as $route_name) {
      if ($route = $collection->get($route_name)) {
        $route->setOption('_admin_route', TRUE);
      }
    }
  }

}
