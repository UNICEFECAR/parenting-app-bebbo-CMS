<?php

/**
 * @file
 * TB Mega Menu template preprocess functions.
 */

use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\Component\Utility\Xss;

/**
 * Prepare variables for tb_megamenu_row.
 *
 * @param array $vars
 *   Variables are declared in theme.
 */
function template_preprocess_tb_megamenu_row(array &$vars): void {
  $row = $vars['row'];
  $vars['columns'] = [];
  foreach ($row as $col) {
    $vars['columns'][] = [
      '#theme' => 'tb_megamenu_column',
      '#menu_name' => $vars['menu_name'],
      '#col' => $col,
      '#parent' => $vars['parent'],
      '#level' => $vars['level'],
      '#menu_config' => $vars['menu_config'],
      '#block_config' => $vars['block_config'],
      '#trail' => $vars['trail'],
      '#section' => $vars['section'],
    ];
  }
  $vars['attributes'] = new Attribute();
  $vars['attributes']['class'] = [];
  $vars['attributes']['class'][] = 'tbm-row';
}

/**
 * Prepare variables for tb_megamenu_column.
 *
 * @param array $vars
 *   Variables are declared in theme.
 */
function template_preprocess_tb_megamenu_column(array &$vars): void {
  $col = $vars['col']['col_content'];
  $col_config = $vars['col']['col_config'] ?? [];

  \Drupal::service('tb_megamenu.menu_builder')->editColumnConfig($col_config);
  $vars['col_config'] = $col_config;
  $vars['close_button'] = FALSE;
  $vars['tb_items'] = [];
  $vars['attributes'] = new Attribute();
  foreach ($col as $tb_item) {
    if ($tb_item['type'] == 'menu_item') {
      $vars['tb_items'][] = [
        '#theme' => 'tb_megamenu_subnav',
        '#menu_name' => $vars['menu_name'],
        '#col' => $col,
        '#items' => $vars['parent']->subtree,
        '#level' => $vars['level'],
        '#menu_config' => $vars['menu_config'],
        '#block_config' => $vars['block_config'],
        '#trail' => $vars['trail'],
        '#section' => $vars['section'],
      ];
      break;
    }
    else {
      $vars['tb_items'][] = [
        '#theme' => 'tb_megamenu_block',
        '#block_id' => $tb_item['block_id'],
        '#section' => $vars['section'],
        '#showblocktitle' => $col_config['showblocktitle'],
      ];
      if ($vars['section'] !== 'frontend') {
        $msg = t("Remove this block");
        $vars['close_button'] = [
          '#markup' => '<span class="close fa fa-times-circle" title="' . $msg . '">&nbsp;</span>',
        ];
        $vars['attributes']['data-showblocktitle'] = $col_config['showblocktitle'];
      }
    }
  }

  $vars['attributes']['class'] = [];
  $vars['attributes']['class'][] = 'tbm-column';
  $vars['attributes']['class'][] = 'span' . ($col_config['width'] ? $col_config['width'] : 12);
  if (!empty($col_config['class'])) {
    $vars['attributes']['class'][] = $col_config['class'];
  }
  if (!empty($col_config['hidewcol'])) {
    $vars['attributes']['class'][] = $col_config['class'];
    $vars['attributes']['class'][] = 'hidden-collapse';
  }

  if ($vars['section'] == 'backend') {
    $vars['attributes']['data-class'] = $col_config['class'];
    $vars['attributes']['data-width'] = $col_config['width'];
    $vars['attributes']['data-hidewcol'] = $col_config['hidewcol'];
    $num_cols = $vars['block_config']['number-columns'] ?? 1;
    $vars['attributes']['id'] = \Drupal::service('tb_megamenu.menu_builder')->getIdColumn($num_cols);
  }
}

/**
 * Prepare variables for tb_megamenu_subnav.
 *
 * @param array $vars
 *   Variables are declared in theme.
 */
function template_preprocess_tb_megamenu_subnav(array &$vars): void {
  $col = $vars['col'];
  $level = $vars['level'];
  $vars['lis'] = [];
  foreach ($col as $tb_item) {
    $plugin_id = !empty($tb_item['plugin_id']) ? $tb_item['plugin_id'] : '';
    $item = \Drupal::service('tb_megamenu.menu_builder')->getMenuItem($vars['menu_name'], $plugin_id);
    if (empty($tb_item)) {
      // @todo figure out how to handle NULLs in columns.
      continue;
    }
    if ($item->link == NULL) {
      continue;
    }

    $weight = $item->link->getWeight();
    $title = $item->link->getTitle();
    $vars['lis'][$weight][$title] = [
      '#theme' => 'tb_megamenu_item',
      '#menu_name' => $vars['menu_name'],
      '#level' => $level + 1,
      '#item' => $item,
      '#menu_config' => $vars['menu_config'],
      '#block_config' => $vars['block_config'],
      '#trail' => $vars['trail'],
      '#section' => $vars['section'],
    ];
  }
  // Sort items by link weights as array keys.
  ksort($vars['lis']);
  // Restructure to account for duplicate weights.
  $new_list = [];
  foreach ($vars['lis'] as $weight_group) {
    ksort($weight_group);
    foreach ($weight_group as $item) {
      $new_list[] = $item;
    }
  }
  $vars['lis'] = $new_list;

  $vars['attributes'] = new Attribute();
  $vars['attributes']['class'] = [];
  $vars['attributes']['class'][] = 'tbm-subnav';
  $vars['attributes']['class'][] = "level-" . $level;
  $vars['attributes']['class'][] = "items-" . count($col);

  // Add a11y attributes.
  $vars['attributes']['role'] = 'list';
}

/**
 * Prepare variables for tb_megamenu_submenu.
 *
 * @param array $vars
 *   Variables are declared in theme.
 */
function template_preprocess_tb_megamenu_submenu(array &$vars): void {
  $parent = $vars['parent'];
  $plugin_id = $parent->link->getPluginId();
  $menu_config = $vars['menu_config'];
  $item_config = $menu_config[$plugin_id] ?? [];
  $submenu_config = $item_config['submenu_config'] ?? [];
  $vars['submenu_config'] = $submenu_config;
  \Drupal::service('tb_megamenu.menu_builder')->editSubMenuConfig($submenu_config, $vars['level']);
  $vars['rows'] = [];
  foreach ($item_config['rows_content'] as $row) {
    $vars['rows'][] = [
      '#theme' => 'tb_megamenu_row',
      '#menu_name' => $vars['menu_name'],
      '#row' => $row,
      '#parent' => $vars['parent'],
      '#level' => $vars['level'],
      '#menu_config' => $vars['menu_config'],
      '#block_config' => $vars['block_config'],
      '#trail' => $vars['trail'],
      '#section' => $vars['section'],
    ];
  }
  $vars['attributes'] = new Attribute();
  $vars['attributes']['class'] = [];

  if ($submenu_config['class'] != "") {
    $vars['attributes']['class'][] = $submenu_config['class'];
  }

  if ($submenu_config['group']) {
    $vars['attributes']['class'][] = 'tbm-group-container';
  }
  else {
    $vars['attributes']['class'][] = 'tbm-submenu';
  }

  $vars['attributes']['class'][] = 'tbm-item-child';

  if ($vars['section'] == 'backend') {
    $vars['attributes']['data-class'] = $submenu_config['class'];
    $vars['attributes']['data-width'] = $submenu_config['width'];
  }

  if (!empty($submenu_config['width'])) {
    $vars['attributes']['class'][] = 'tbm-has-width';
    $vars['attributes']['style'] = "width: " . $submenu_config['width'] . "px;";
  }

  // Add a11y attributes.
  $vars['attributes']['role'] = 'list';
}

/**
 * Prepare variables for tb_megamenu.
 *
 * @param array $vars
 *   Variables are declared in theme.
 */
function template_preprocess_tb_megamenu(array &$vars): void {
  $menu_name = $vars['menu_name'];
  $menu_id = "tbm-" . str_replace('_', '-', $menu_name);
  $theme = $vars['block_theme'];
  $vars['#attached']['drupalSettings']['TBMegaMenu']['theme'] = $theme;

  $menu_tree = \Drupal::menuTree();
  $menu_active_trail = \Drupal::service('menu.active_trail')->getActiveTrailIds($menu_name);
  $parameters = (new MenuTreeParameters)->setActiveTrail($menu_active_trail)->onlyEnabledLinks();
  $menu_items = $menu_tree->load($menu_name, $parameters);
  $manipulators = [
    ['callable' => 'menu.default_tree_manipulators:checkNodeAccess'],
    ['callable' => 'menu.default_tree_manipulators:checkAccess'],
  ];
  $menu_items = $menu_tree->transform($menu_items, $manipulators);
  tb_megamenu_check_access($menu_items);
  /*
   * This function is old, we need to solve this later.
   * if (function_exists('i18n_menu_localize_tree')) {
   *  $items = i18n_menu_localize_tree($items);
   * }
   */
  // @todo what does this function to do ?
  // $items_page = menu_tree_page_data($menu_name);
  // $trail = tb_megamenu_build_page_trail($items_page);
  $menu_builder = \Drupal::service('tb_megamenu.menu_builder');
  $trail = $menu_builder->buildPageTrail($menu_items);
  $menu_config = $menu_builder->getMenuConfig($menu_name, $theme);
  $block_config = $menu_builder->getBlockConfig($menu_name, $theme);
  $menu_builder->editBlockConfig($block_config);
  $menu_builder->syncConfigAll($menu_items, $menu_config, $vars['section']);
  $vars['block_config'] = $block_config;
  $vars['attributes'] = new Attribute();
  $vars['attributes']['class'] = [];
  $vars['attributes']['class'][] = 'tbm';
  $vars['attributes']['class'][] = 'tbm' . '-' . str_replace('_', '-', $menu_name);
  $vars['attributes']['id'] = $menu_id;
  $vars['attributes']['data-breakpoint'] = $block_config['breakpoint'];
  $vars['#attached']['drupalSettings']['TBMegaMenu'][$menu_id]['arrows'] = $block_config['auto-arrow'];
  if ($block_config['auto-arrow'] === '1') {
    $vars['attributes']['class'][] = 'tbm-arrows';
  }
  else {
    $vars['attributes']['class'][] = 'tbm-no-arrows';
  }

  if ($block_config['animation'] != 'none') {
    $vars['attributes']['class'][] = "animate";
    $vars['attributes']['class'][] = $block_config['animation'];
    $vars['attributes']['data-duration'] = $block_config['duration'];
    $vars['attributes']['data-delay'] = $block_config['delay'];
  }
  $vars['content'] = [
    '#theme' => 'tb_megamenu_nav',
    '#menu_name' => $vars['menu_name'],
    '#level' => 0,
    '#menu_config' => $menu_config,
    '#block_config' => $block_config,
    '#items' => $menu_items,
    '#trail' => $trail,
    '#section' => $vars['section'],
  ];
  if ($vars['section'] == 'frontend' && $block_config['animation'] != 'none') {
    $css = '';
    $css .= '  transition-delay: ' . $block_config['delay'] . 'ms;';
    $css .= '  -webkit-transition-delay: ' . $block_config['delay'] . 'ms;';
    $css .= '  -ms-transition-delay: ' . $block_config['delay'] . 'ms;';
    $css .= '  -o-transition-delay: ' . $block_config['delay'] . 'ms;';
    $css .= '  transition-duration: ' . $block_config['duration'] . 'ms;';
    $css .= '  -webkit-transition-duration: ' . $block_config['duration'] . 'ms;';
    $css .= '  -ms-transition-duration: ' . $block_config['duration'] . 'ms;';
    $css .= '  -o-transition-duration: ' . $block_config['duration'] . 'ms;';
    $vars['css_style'] = $css;
  }

  // Add a11y attributes.
  $vars['attributes']['aria-label'] = t('@menu_name navigation', ['@menu_name' => $menu_name]);

  // Reset the getMenuItem method in case there is more than one TB menu on the
  // same page.
  drupal_static_reset('getMenuItem');
}

/**
 * Prepare variables for tb_megamenu_backend.
 *
 * @param array $vars
 *   Variables are declared in theme.
 */
function template_preprocess_tb_megamenu_backend(array &$vars): void {
  $menu_name = $vars['menu_name'];
  $theme = $vars['block_theme'];
  $menu_builder = \Drupal::service('tb_megamenu.menu_builder');
  $block_config = $menu_builder->getBlockConfig($menu_name, $theme);
  $menu_builder->editBlockConfig($block_config);
  $vars['menu_content'] = $menu_builder->renderBlock($menu_name, $theme);

  // Determine if fontawesome is installed.
  $moduleHandler = \Drupal::service('module_handler');
  $fontawesome = $moduleHandler->moduleExists('fontawesome');
  $vars['item_toolbox'] = [
    '#theme' => 'tb_megamenu_item_toolbox',
    '#fontawesome' => $fontawesome,
  ];

  $vars['submenu_toolbox'] = ['#theme' => 'tb_megamenu_submenu_toolbox'];
  $vars['column_toolbox'] = [
    '#theme' => 'tb_megamenu_column_toolbox',
    '#blocks_options' => $menu_builder->getAllBlocks($theme),
  ];
  $vars['block_config'] = $block_config;
  $vars['animation_options'] = $menu_builder->createAnimationOptions($block_config);
  $vars['edit_links'] = Url::fromRoute('entity.menu.edit_form', ['menu' => $menu_name])->toString();

  // Attach this to init TB Mega Menu (Importance).
  $vars['#attached']['drupalSettings']['TBMegaMenu']['menu_name'] = $menu_name;
}

/**
 * Prepare variables for tb_megamenu_block.
 *
 * @param array $vars
 *   Variables are declared in theme.
 */
function template_preprocess_tb_megamenu_block(array &$vars): void {
  /** @var \Drupal\block\BlockInterface $entity_block */
  $entity_block = \Drupal::service('tb_megamenu.menu_builder')->loadEntityBlock($vars['block_id']);
  if ($entity_block) {
    $plugin = $entity_block->getPlugin();
    $plugin_configuration = $plugin->getConfiguration();
    $content = $plugin->build();
    if ($content) {
      if (empty($vars['showblocktitle'])) {
        $plugin_configuration['label_display'] = '';
      }
      $vars['content'] = [
        '#theme' => 'block',
        '#configuration' => $plugin_configuration,
        '#plugin_id' => $plugin->getPluginId(),
        '#base_plugin_id' => $plugin->getBaseId(),
        '#derivative_plugin_id' => $plugin->getDerivativeId(),
        'content' => $content,
        '#id' => $plugin_configuration['id'],
      ];
    }
    elseif ($vars['section'] == 'backend') {
      $vars['content'] = $plugin_configuration['label'];
    }
    $vars['attributes'] = new Attribute();
    $vars['attributes']['class'] = [];
    $vars['attributes']['class'][] = 'tbm-block';

    if ($vars['section'] == 'backend') {
      $vars['attributes']['data-type'] = "block";
      $vars['attributes']['data-block'] = $vars['block_id'];
    }
  }
  else {
    $vars['content'] = NULL;
  }
}

/**
 * Prepare variables for tb_megamenu_nav.
 *
 * @param array $vars
 *   Variables are declared in theme.
 */
function template_preprocess_tb_megamenu_nav(array &$vars): void {
  $items = $vars['items'];
  $level = $vars['level'];
  $vars['lis'] = [];
  foreach ($items as $item) {
    if ($item->link->isEnabled()) {
      $weight = $item->link->getWeight();
      $title = $item->link->getTitle();
      $vars['lis'][$weight][$title] = [
        '#theme' => 'tb_megamenu_item',
        '#menu_name' => $vars['menu_name'],
        '#level' => $level + 1,
        '#item' => $item,
        '#menu_config' => $vars['menu_config'],
        '#block_config' => $vars['block_config'],
        '#trail' => $vars['trail'],
        '#section' => $vars['section'],
      ];
    }
  }
  // Sort items by link weights as array keys.
  ksort($vars['lis']);
  // Restructure to account for duplicate weights.
  $new_list = [];
  foreach ($vars['lis'] as $weight_group) {
    ksort($weight_group);
    foreach ($weight_group as $item) {
      $new_list[] = $item;
    }
  }

  $vars['lis'] = $new_list;

  $vars['attributes'] = new Attribute();
  $vars['attributes']['class'] = [];
  $vars['attributes']['class'][] = "tbm-nav";
  $vars['attributes']['class'][] = "level-" . $level;
  $vars['attributes']['class'][] = "items-" . count($items);

  // Add a11y attributes to unordered list tag.
  $vars['attributes']['role'] = 'list';
}

/**
 * Prepare variables for tb_megamenu_item.
 *
 * @param array $vars
 *   Variables are declared in theme.
 */
function template_preprocess_tb_megamenu_item(array &$vars): void {
  $level = $vars['level'];
  $item = $vars['item'];
  $vars['link'] = $item->link->getPluginDefinition();
  $vars['link']['url'] = $item->link->getUrlObject()->toString();
  $plugin_id = $vars['link']['id'];
  $menu_config = $vars['menu_config'];
  $item_config = $menu_config[$plugin_id]['item_config'] ?? [];
  $title = t('@title', ['@title' => $item->link->getTitle()]);
  $vars['link']['title_translate'] = htmlspecialchars_decode(
    Xss::filter($title)
  );
  $trail = $vars['trail'];
  $menu_builder = \Drupal::service('tb_megamenu.menu_builder');
  $menu_builder->editItemConfig($item_config, $level);
  $vars['item_config'] = $item_config;

  // Determine if fontawesome is installed.
  $moduleHandler = \Drupal::service('module_handler');
  $vars['fontawesome'] = $moduleHandler->moduleExists('fontawesome');

  if (!empty($menu_config[$plugin_id]['rows_content']) &&
    ($vars['section'] == 'backend' || !$item_config['hidesub'])) {

    $vars['submenu'] = [
      '#theme' => 'tb_megamenu_submenu',
      '#menu_name' => $vars['menu_name'],
      '#parent' => $item,
      '#level' => $level,
      '#menu_config' => $vars['menu_config'],
      '#block_config' => $vars['block_config'],
      '#trail' => $vars['trail'],
      '#section' => $vars['section'],
    ];
  }
  else {
    $vars['submenu'] = NULL;
  }
  $vars['group'] = FALSE;
  $vars['link']['attributes'] = new Attribute();
  $vars['attributes'] = new Attribute();
  $vars['attributes']['class'] = [];
  $vars['link']['attributes']['class'] = [];
  $vars['attributes']['class'][] = 'tbm-item';
  $vars['attributes']['class'][] = 'level-' . $level;
  $vars['link']['attributes']['class'][] = 'tbm-link';
  $vars['link']['attributes']['class'][] = 'level-' . $level;

  if (empty($vars['link']['url'])) {
    $vars['link']['attributes']['class'][] = 'no-link';
    $vars['link']['attributes']['tabindex'] = '0';
  }

  if (!empty($item_config['class'])) {
    $vars['attributes']['class'][] = $item_config['class'];
  }
  if (!empty($item_config['alignsub'])) {
    $vars['attributes']['class'][] = "tbm-" . $item_config['alignsub'];
  }
  if ($item_config['hidewcol']) {
    $vars['attributes']['class'][] = "sub-hidden-collapse";
  }

  if ($item_config['group'] && $vars['submenu']) {
    $vars['group'] = TRUE;
    $vars['attributes']['class'][] = "tbm-group";
    $vars['link']['attributes']['class'][] = 'tbm-group-title';
  }
  elseif ($vars['submenu'] && !$item_config['hidesub']) {
    $vars['link']['attributes']['class'][] = 'tbm-toggle';
    if ($level == 1) {
      $vars['attributes']['class'][] = 'tbm-item--has-dropdown';
    }
    else {
      $vars['attributes']['class'][] = 'tbm-item--has-flyout';
    }
  }

  if (isset($trail[$plugin_id])) {
    $vars['attributes']['class'][] = 'active';
    $vars['attributes']['class'][] = 'active-trail';
    $vars['link']['attributes']['class'][] = 'active-trail';
  }

  if ($vars['section'] == 'backend') {
    $vars['attributes']['title'] = t("Menu Item");
    $vars['attributes']['data-id'] = $plugin_id;
    $vars['attributes']['data-level'] = $level;
    $vars['attributes']['data-type'] = 'menu_item';
    $vars['attributes']['data-class'] = $item_config['class'];
    $vars['attributes']['data-xicon'] = $item_config['xicon'];
    $vars['attributes']['data-caption'] = $item_config['caption'];
    $vars['attributes']['data-alignsub'] = $item_config['alignsub'];
    $vars['attributes']['data-group'] = $item_config['group'];
    $vars['attributes']['data-hidewcol'] = $item_config['hidewcol'];
    $vars['attributes']['data-hidesub'] = $item_config['hidesub'];
    $vars['attributes']['data-label'] = $item_config['label'];
  }

  // Add a11y attributes to list item tag.
  $vars['attributes']['aria-level'] = $level;

  // Add a11y attributes to anchor tag.
  if (!empty($vars['link']['description'])) {
    $vars['link']['attributes']['title'] = $vars['link']['description'];
  }
  if ($vars['submenu'] != NULL) {
    $vars['link']['attributes']['aria-expanded'] = 'false';
  }

  // Make other menu attributes available to TB Mega Menu Anchor Links.
  if (!empty($vars['link']['options']['attributes']['target'])) {
    $vars['link']['attributes']['target'] = $vars['link']['options']['attributes']['target'];
  }
  if (!empty($vars['link']['options']['attributes']['rel'])) {
    $vars['link']['attributes']['rel'] = $vars['link']['options']['attributes']['rel'];
  }
  if (!empty($vars['link']['options']['attributes']['class'])) {
    $vars['link']['attributes']->addClass($vars['link']['options']['attributes']['class']);
  }
}
