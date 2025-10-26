<?php

namespace Drupal\pb_custom_standard_deviation\Plugin\views\style;

use Drupal\rest\Plugin\views\style\Serializer;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\node\NodeInterface;

/**
 * The style plugin for serialized output formats.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "pb_custom_standard_deviation",
 *   title = @Translation("Custom standard deviation"),
 *   help = @Translation("Serializes views row data using custom SD mapping."),
 *   display_types = {"data"}
 * )
 */
class CustomStandardDeviation extends Serializer {

  /**
   * The current path service.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * {@inheritDoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    $serializer,
    array $serializer_formats,
    array $serializer_format_providers,
    CurrentPathStack $current_path,
    LanguageManagerInterface $language_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer, $serializer_formats, $serializer_format_providers);
    $this->currentPath = $current_path;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('serializer'),
      $container->getParameter('serializer.formats'),
      $container->getParameter('serializer.format_providers'),
      $container->get('path.current'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function render() {
    $request_uri = $this->currentPath->getPath();

    // 1) Enrich rows with growth type + deviation label.
    $rows = $this->view->result ?? [];
    foreach ($rows as &$row) {
      /** @var \Drupal\node\NodeInterface|null $entity */
      $entity = $row->_entity ?? NULL;

      if ($entity instanceof NodeInterface && $entity->hasField('field_growth_type')) {
        $tid = $entity->get('field_growth_type')->target_id ?? NULL;
        // @phpstan-ignore-next-line
        $row->custom_growth_type = $tid ? $this->getTermNameById($tid) : NULL;
      }

      if ($entity instanceof NodeInterface && $entity->hasField('field_standard_deviation')) {
        $tid = $entity->get('field_standard_deviation')->target_id ?? NULL;
        // @phpstan-ignore-next-line
        $row->standard_deviation = $tid ? ($this->loadTermName($tid) ?? NULL) : NULL;
      }
    }
    $this->view->result = $rows;

    // 2) Validate request lang param.
    $validate = $this->checkRequestParams($request_uri);
    if (!empty($validate)) {
      return $this->serializer->serialize($validate, 'json', ['views_style_plugin' => $this]);
    }

    if (empty($this->view->result)) {
      $out = ['status' => 204, 'message' => 'No Records Found'];
      return $this->serializer->serialize($out, 'json', ['views_style_plugin' => $this]);
    }

    // 3) Mapping tables (SD label → output key) per growth type.
    $maps = [
      'height_for_age' => [
        'between -2SD to +3SD' => 'goodText',
        'below -2SD' => 'warrningSmallLengthText',
        'below -3SD' => 'emergencySmallLengthText',
        'above +3SD' => 'warrningBigLengthText',
      ],
      'height_for_weight' => [
        'between -2SD to +2SD' => 'goodText',
        'between -2 and -3SD' => 'warrningSmallHeightText',
        'below -3SD' => 'emergencySmallHeightText',
        'between +2 and +3SD' => 'warrningBigHeightText',
        'above +3SD' => 'emergencyBigHeightText',
      ],
    ];

    // 4) Collect normalized items from rendered rows.
    $items_by_growth = [
      'height_for_age' => [],
      'height_for_weight' => [],
    ];

    foreach ($this->view->result as $row_index => $row_result) {
      $this->view->row_index = $row_index;
      $render = $this->view->rowPlugin->render($row_result);
      // Ensure custom_growth_type present.
      if (!isset($render['custom_growth_type'])) {
        $render['custom_growth_type'] = $row_result->custom_growth_type ?? NULL;
      }
      // Ensure standard_deviation present.
      if (!isset($render['standard_deviation'])) {
        $render['standard_deviation'] = $row_result->standard_deviation ?? NULL;
      }

      // Normalize to plain array.
      $render = json_decode(json_encode($render), TRUE);
      $growth = $render['custom_growth_type'] ?? NULL;
      if (!$growth || !isset($maps[$growth])) {
        continue;
      }

      $items_by_growth[$growth][] = [
        'child_age' => $render['child_age'] ?? '',
        'pinned_article' => $render['pinned_article'] ?? '',
        'title' => $this->cleanTitle($render['title'] ?? ''),
        'body' => $this->cleanBody($render['body'] ?? ''),
        'sd_label' => trim($render['standard_deviation'] ?? ''),
      ];
    }

    // 5) Build output for each growth type.
    $final = [];

    // Bucket definitions (same strings as your sorter returns).
    $buckets = [
      "1st_month,2nd_month,3_4_months,5_6_months",
      "7_9_months",
      "10_12_months",
      "13_18_months,19_24_months",
      "25_36_months,37_48_months,49_60_months,61_72_months",
    ];

    foreach (['height_for_weight', 'height_for_age'] as $growth) {
      $grouped = $this->groupByChildBuckets($items_by_growth[$growth] ?? [], $buckets);
      $sd_arr = [];

      foreach ($grouped as $groupItems) {
        if (empty($groupItems)) {
          continue;
        }

        // child_age->array of ints from term machine names.
        // (already sorted in sorter).
        $child_age_ids = $this->customArrayFormatter($groupItems[0]['child_age'] ?? '');
        $sd_data = [
          'child_age' => array_values(array_filter(array_map('intval', $child_age_ids))),
        ];

        // ---- Ordered mapping fix ----
        $tmp_fields = [];
        foreach ($groupItems as $gi) {
          $field_key = $maps[$growth][$gi['sd_label']] ?? NULL;
          if (!$field_key) {
            // Unknown label → skip gracefully.
            continue;
          }

          $pinned = $this->customArrayFormatter($gi['pinned_article']);
          $tmp_fields[$field_key] = [
            'articleID' => array_values(array_filter(array_map('intval', $pinned))),
            'name' => $gi['title'],
            'text' => $gi['body'],
          ];
        }

        // Define explicit order.
        $order = $growth === 'height_for_age'
          ? [
            'goodText',
            'warrningSmallLengthText',
            'emergencySmallLengthText',
            'warrningBigLengthText',
          ]
          : [
            'goodText',
            'warrningSmallHeightText',
            'emergencySmallHeightText',
            'warrningBigHeightText',
            'emergencyBigHeightText',
          ];

        foreach ($order as $key) {
          if (isset($tmp_fields[$key])) {
            $sd_data[$key] = $tmp_fields[$key];
          }
        }
        $sd_arr[] = $sd_data;
      }

      if (!empty($sd_arr)) {
        // Use expected top-level keys.
        $key = $growth === 'height_for_weight' ? 'weight_for_height' : 'height_for_age';
        $final[$key] = $sd_arr;
      }
    }

    // 6) Build response.
    $out = [
      'status' => 200,
      'data'   => $final,
    ];
    $parts = explode('/', $request_uri);
    if (!empty($parts[3])) {
      $out['langcode'] = $parts[3];
    }

    return $this->serializer->serialize($out, 'json', ['views_style_plugin' => $this]);
  }

  /**
   * {@inheritDoc}
   */
  protected function cleanTitle(string $s): string {
    return html_entity_decode($s, ENT_QUOTES | ENT_HTML5);
  }

  /**
   * {@inheritDoc}
   */
  protected function cleanBody(string $s): string {
    $s = str_replace(["\r", "\n"], '', $s);
    return $s;
  }

  /**
   * Function to load term name by termid.
   */
  protected function loadTermName($tid): ?string {
    $t = Term::load($tid);
    return $t ? $t->getName() : NULL;
  }

  /**
   * Convert comma separated string into array.
   */
  public function customArrayFormatter($values): array {
    if (!is_string($values) || $values === '') {
      return [];
    }
    return strpos($values, ',') !== FALSE ? array_map('trim', explode(',', $values)) : [trim($values)];
  }

  /**
   * Validate request lang param.
   */
  public function checkRequestParams($request_uri) {
    $parts = explode('/', $request_uri);
    if (!empty($parts[3])) {
      $languages = array_keys(json_decode(json_encode($this->languageManager->getLanguages()), TRUE));
      if (!in_array($parts[3], $languages, TRUE)) {
        return ['status' => 400, 'message' => 'Request language is wrong'];
      }
    }
    return NULL;
  }

  /**
   * Group items into defined child-age buckets.
   *
   * Keeps your existing sort+labeling approach for bucket keys.
   *
   * @param array $items
   *   Each item: ['child_age','pinned_article','title','body','sd_label'].
   * @param array $bucketLabels
   *   Ordered list of bucket label strings.
   *
   * @return array
   *   bucketLabel => item[]
   */
  protected function groupByChildBuckets(array $items, array $bucketLabels): array {
    $out = array_fill_keys($bucketLabels, []);
    foreach ($items as $it) {
      if (empty($it['child_age'])) {
        continue;
      }
      $label = $this->sortChildAgeId($it['child_age']);
      if (isset($out[$label])) {
        $out[$label][] = $it;
      }
    }
    return $out;
  }

  /**
   * Sort child age IDs and return the comma-joined unique names (bucket key).
   */
  public function sortChildAgeId($child_age_id): string {
    $ids = array_values(array_filter(array_map('trim', explode(',', (string) $child_age_id))));
    sort($ids, SORT_NUMERIC);
    $names = [];
    foreach ($ids as $id) {
      $name = $this->getTermNameById($id);
      if ($name !== NULL) {
        $names[] = $name;
      }
    }
    return implode(',', $names);
  }

  /**
   * Get term's field_unique_name value by TID.
   */
  public function getTermNameById($term_id) {
    $term = Term::load($term_id);
    if ($term && $term->hasField('field_unique_name') && !$term->get('field_unique_name')->isEmpty()) {
      return trim($term->get('field_unique_name')->value);
    }
    return NULL;
  }

}
