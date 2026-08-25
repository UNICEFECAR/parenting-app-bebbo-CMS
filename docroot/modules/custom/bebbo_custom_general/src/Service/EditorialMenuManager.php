<?php

namespace Drupal\bebbo_custom_general\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\menu_link_content\MenuLinkContentInterface;

/**
 * Exports and syncs the editorial menu between the database and config.
 *
 * The editorial menu is built from menu_link_content entities, which are
 * content, not config. Config import therefore never applies menu changes to a
 * site. This service keeps a canonical copy of the menu in
 * bebbo_custom_general.editorial_menu and applies it to every site on deploy,
 * so all sites converge on one menu with one set of per-role visibility rules.
 *
 * It replaces the menu_export module's exporter, which collapses every
 * multi-value field to its first value and would reduce a link visible to four
 * roles down to one.
 */
class EditorialMenuManager {

  /**
   * Name of the config object holding the canonical menu.
   */
  public const CONFIG_NAME = 'bebbo_custom_general.editorial_menu';

  /**
   * Machine name of the menu this service manages.
   */
  public const MENU_NAME = 'editorial-menu';

  /**
   * Entity fields written to, and read back from, the canon.
   */
  protected const FIELDS = [
    'title',
    'description',
    'weight',
    'expanded',
    'enabled',
    'parent',
    'langcode',
  ];

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs an EditorialMenuManager object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Writes the site's editorial menu into the canonical config object.
   *
   * @return int
   *   Number of links exported.
   */
  public function export(): int {
    $links = [];
    foreach ($this->loadMenuLinks() as $uuid => $link) {
      $links[$uuid] = $this->toCanon($link);
    }
    ksort($links);

    $this->configFactory->getEditable(self::CONFIG_NAME)
      ->set('links', $links)
      ->save();

    return count($links);
  }

  /**
   * Applies the canonical config to this site's menu links.
   *
   * @param bool $dry_run
   *   When TRUE, report what would change without writing anything.
   *
   * @return array
   *   Report with 'created', 'updated', 'deleted' and 'unchanged' keys, each
   *   holding a list of human-readable descriptions.
   */
  public function sync(bool $dry_run = FALSE): array {
    $canon = $this->getCanon();
    $report = [
      'created' => [],
      'updated' => [],
      'deleted' => [],
      'unchanged' => [],
    ];

    if (!$canon) {
      return $report;
    }

    $storage = $this->entityTypeManager->getStorage('menu_link_content');
    $existing = $this->loadMenuLinks();

    // Remove links the canon does not know about. Scoped to this menu only:
    // other menus are managed elsewhere and must not be touched.
    foreach ($existing as $uuid => $link) {
      if (!isset($canon[$uuid])) {
        $report['deleted'][] = $link->getTitle() . ' (' . $uuid . ')';
        if (!$dry_run) {
          $link->delete();
        }
      }
    }

    // Parents must exist before their children can reference them.
    foreach ($this->sortByDepth($canon) as $uuid => $values) {
      $link = $existing[$uuid] ?? NULL;

      if (!$link) {
        $report['created'][] = $values['title'] . ' (' . $uuid . ')';
        if ($dry_run) {
          continue;
        }
        $link = $storage->create([
          'uuid' => $uuid,
          'menu_name' => self::MENU_NAME,
        ]);
        $this->applyCanon($link, $values);
        $link->save();
        continue;
      }

      $changed = $this->diffLink($link, $values);
      if (!$changed) {
        $report['unchanged'][] = $values['title'];
        continue;
      }

      $report['updated'][] = $values['title'] . ': ' . implode(', ', $changed);
      if (!$dry_run) {
        $this->applyCanon($link, $values);
        $link->save();
      }
    }

    return $report;
  }

  /**
   * Returns the canonical menu definition.
   *
   * @return array
   *   Links keyed by UUID.
   */
  public function getCanon(): array {
    return $this->configFactory->get(self::CONFIG_NAME)->get('links') ?? [];
  }

  /**
   * Loads this site's editorial menu links keyed by UUID.
   *
   * @return \Drupal\menu_link_content\MenuLinkContentInterface[]
   *   Menu links keyed by UUID.
   */
  protected function loadMenuLinks(): array {
    $storage = $this->entityTypeManager->getStorage('menu_link_content');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('menu_name', self::MENU_NAME)
      ->execute();

    $links = [];
    foreach ($storage->loadMultiple($ids) as $link) {
      $links[$link->uuid()] = $link;
    }

    return $links;
  }

  /**
   * Converts a menu link into its canonical array form.
   *
   * @param \Drupal\menu_link_content\MenuLinkContentInterface $link
   *   The menu link.
   *
   * @return array
   *   Canonical values for the link.
   */
  protected function toCanon(MenuLinkContentInterface $link): array {
    $link_value = $link->get('link')->first()?->getValue() ?? [];

    $values = [
      'title' => (string) $link->getTitle(),
      'description' => (string) $link->getDescription(),
      'uri' => (string) ($link_value['uri'] ?? ''),
      'options' => $link_value['options'] ?? [],
      'weight' => (int) $link->getWeight(),
      'expanded' => (bool) $link->isExpanded(),
      'enabled' => (bool) $link->isEnabled(),
      'parent' => (string) $link->getParentId(),
      'langcode' => $link->language()->getId(),
      'show_role' => $this->roleValues($link, 'menu_per_role__show_role'),
      'hide_role' => $this->roleValues($link, 'menu_per_role__hide_role'),
    ];
    ksort($values);

    return $values;
  }

  /**
   * Reads a menu_per_role field as a plain list of role IDs.
   *
   * @param \Drupal\menu_link_content\MenuLinkContentInterface $link
   *   The menu link.
   * @param string $field_name
   *   The field to read.
   *
   * @return string[]
   *   Role IDs, sorted so the canon stays stable across exports.
   */
  protected function roleValues(MenuLinkContentInterface $link, string $field_name): array {
    if (!$link->hasField($field_name)) {
      return [];
    }

    $roles = [];
    foreach ($link->get($field_name)->getValue() as $item) {
      if (!empty($item['target_id'])) {
        $roles[] = $item['target_id'];
      }
    }
    sort($roles);

    return $roles;
  }

  /**
   * Writes canonical values onto a menu link.
   *
   * @param \Drupal\menu_link_content\MenuLinkContentInterface $link
   *   The menu link.
   * @param array $values
   *   Canonical values for the link.
   */
  protected function applyCanon(MenuLinkContentInterface $link, array $values): void {
    foreach (self::FIELDS as $field) {
      $link->set($field, $values[$field] ?? NULL);
    }

    $link->set('menu_name', self::MENU_NAME);
    $link->set('link', [
      'uri' => $values['uri'],
      'options' => $values['options'] ?? [],
    ]);

    foreach (['show_role', 'hide_role'] as $key) {
      $field_name = 'menu_per_role__' . $key;
      if ($link->hasField($field_name)) {
        $link->set($field_name, $values[$key] ?? []);
      }
    }
  }

  /**
   * Lists the canonical values that differ from the link's current state.
   *
   * @param \Drupal\menu_link_content\MenuLinkContentInterface $link
   *   The menu link.
   * @param array $values
   *   Canonical values for the link.
   *
   * @return string[]
   *   Names of the differing values, empty when the link already matches.
   */
  protected function diffLink(MenuLinkContentInterface $link, array $values): array {
    $current = $this->toCanon($link);
    $changed = [];

    foreach ($values as $key => $value) {
      if (($current[$key] ?? NULL) !== $value) {
        $changed[] = $key;
      }
    }

    return $changed;
  }

  /**
   * Orders canonical links so parents come before their children.
   *
   * @param array $canon
   *   Links keyed by UUID.
   *
   * @return array
   *   The same links, ordered by depth.
   */
  protected function sortByDepth(array $canon): array {
    $depths = [];
    foreach (array_keys($canon) as $uuid) {
      $depth = 0;
      $parent = $canon[$uuid]['parent'] ?? '';
      // Walk up the tree, guarding against a parent chain that loops.
      while ($parent && $depth <= count($canon)) {
        $parent_uuid = str_replace('menu_link_content:', '', $parent);
        if (!isset($canon[$parent_uuid])) {
          break;
        }
        $depth++;
        $parent = $canon[$parent_uuid]['parent'] ?? '';
      }
      $depths[$uuid] = $depth;
    }
    asort($depths);

    $sorted = [];
    foreach (array_keys($depths) as $uuid) {
      $sorted[$uuid] = $canon[$uuid];
    }

    return $sorted;
  }

}
