<?php

namespace Drupal\pb_custom_standard_deviation\Plugin\views\style;

use Drupal\rest\Plugin\views\style\Serializer;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * The style plugin for serialized output formats.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "pb_custom_standard_deviation",
 *   title = @Translation("Custom standard deviation"),
 *   help = @Translation("Serializes views row data using the Serializer
 *   component."), display_types = {"data"}
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
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, $serializer, array $serializer_formats, array $serializer_format_providers, CurrentPathStack $current_path, LanguageManagerInterface $language_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer, $serializer_formats, $serializer_format_providers);
    $this->currentPath = $current_path;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
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
   * {@inheritdoc}
   */
  public function render() {
    $request_uri = $this->currentPath->getPath();
    $request = explode('/', $request_uri);

    // Get the view results.
    $rows = $this->view->result;
    // Add field_unique_name to each row by child_growth_id.
    foreach ($rows as &$row) {
      if (!empty($row->_entity) && $row->_entity->hasField('field_growth_type')) {
        $growth_type_tid = $row->_entity->get('field_growth_type')->target_id ?? NULL;
        if ($growth_type_tid) {
          $term = Term::load($growth_type_tid);
          $row->custom_growth_type = ($term && $term->hasField('field_unique_name'))
          ? trim($term->get('field_unique_name')->value)
          : NULL;
        }
      }
    }

    // Pass the modified rows to the parent serializer.
    $this->view->result = $rows;
    /* Validating request params to response error code */
    $validate_params_res = $this->checkRequestParams($request_uri);
    if (empty($validate_params_res)) {
      $sd_weight_for_height_fields = [
        "goodText",
        "warrningSmallHeightText",
        "emergencySmallHeightText",
        "warrningBigHeightText",
        "emergencyBigHeightText",
      ];
      $sd_height_for_age_fields = [
        "goodText",
        "warrningSmallLengthText",
        "emergencySmallLengthText",
        "warrningBigLengthText",
      ];
      $rows = [];
      $weight_for_height = [];
      $height_for_age = [];
      if (isset($this->view->result) && !empty($this->view->result)) {
        foreach ($this->view->result as $row_index => $row) {
          $this->view->row_index = $row_index;
          $view_render = $this->view->rowPlugin->render($row);

          // Add the custom field manually if not included in the rendering.
          if (!isset($view_render['custom_growth_type'])) {
            $view_render['custom_growth_type'] = $row->custom_growth_type ?? NULL;
          }

          $view_render = json_encode($view_render);
          $rendered_data = json_decode($view_render, TRUE);
          foreach ($rendered_data as $key => $values) {
            // If (($key === 'growth_type' && $values === "6461") ||
            // ($key === 'growth_type' && $values === "606") ||
            // ($key === 'growth_type' && $values === "6891")) {.
            if (($key === 'custom_growth_type' && $values === "height_for_weight")) {
              $weight_for_height[] = $rendered_data;
            }

            // If (($key === 'growth_type' && $values === "32786") ||
            // ($key === 'growth_type' && $values === "601") ||
            // ($key === 'growth_type' && $values === "25466")) {.
            if (($key === 'custom_growth_type' && $values === "height_for_age")) {
              $height_for_age[] = $rendered_data;
            }
          }
        }
        $child_1 = [];
        $child_2 = [];
        $child_3 = [];
        $child_4 = [];
        $child_5 = [];

        for ($i = 0; $i < count($weight_for_height); $i++) {
          if (isset($weight_for_height[$i]['child_age'])) {
            $sorted_weight_for_height = $this->sortChildAgeId($weight_for_height[$i]['child_age']);
            // \Drupal::logger('pb_custom_standard_deviation')->
            // notice('weight_for_height=> <pre><code>' .
            // $sorted_weight_for_height . '</code></pre>');.
            // if ($sorted_weight_for_height === "43,44,45,46" ||
            // $sorted_weight_for_height === "466,471,476,481" ||
            // $sorted_weight_for_height === "596,601,606,611") {
            if ($sorted_weight_for_height === "1st_month,2nd_month,3_4_months,5_6_months") {
              $child_1[] = $weight_for_height[$i];
            }

            // If ($sorted_weight_for_height === "47" ||
            // $sorted_weight_for_height === "486" ||
            // $sorted_weight_for_height === "616") {.
            if ($sorted_weight_for_height === "7_9_months") {
              $child_2[] = $weight_for_height[$i];
            }

            // If ($sorted_weight_for_height === "48" ||
            // $sorted_weight_for_height === "491" ||
            // $sorted_weight_for_height === "621") {.
            if ($sorted_weight_for_height === "10_12_months") {
              $child_3[] = $weight_for_height[$i];
            }

            // If ($sorted_weight_for_height === "49,50" ||
            // $sorted_weight_for_height === "496,501" ||
            // $sorted_weight_for_height === "626,631") {.
            if ($sorted_weight_for_height === "13_18_months,19_24_months") {
              $child_4[] = $weight_for_height[$i];
            }

            // If ($sorted_weight_for_height === "51,52,57,58" ||
            // $sorted_weight_for_height === "506,511,516,521" ||
            // $sorted_weight_for_height === "636,641,646,651") {.
            if ($sorted_weight_for_height === "25_36_months,37_48_months,49_60_months,61_72_months") {
              $child_5[] = $weight_for_height[$i];
            }
          }
        }

        $sd_data = [];
        $sd_field_data = [];
        $sd_arr = [];
        for ($i = 1; $i <= 5; $i++) {
          $temp = "child_" . $i;
          $formatted_data = $this->customArrayFormatter($$temp[0]['child_age']);
          $sd_data['child_age'] = array_map(
            function ($elem) {
              return intval($elem);
            }, $formatted_data);
          for ($j = 0; $j < count($$temp); $j++) {
            $pinned_data = $this->customArrayFormatter($$temp[$j]['pinned_article']);
            $sd_field_data['articleID'] = array_map(
              function ($elem) {
                return intval($elem);
              }, $pinned_data);
            $title = str_replace("&#039;", "'", $$temp[$j]['title']);
            $title = str_replace("&quot;", '"', $title);
            $sd_field_data['name'] = $title;
            /* remove new line. */
            $body = str_replace("\n", '', $$temp[$j]['body']);
            $sd_field_data['text'] = $body;
            $sd_data[$sd_weight_for_height_fields[$j]] = $sd_field_data;
          }
          $sd_arr[] = $sd_data;
        }
        $sd_final_data['weight_for_height'] = $sd_arr;

        // Render data for height for age.
        $child_1 = [];
        $child_2 = [];
        $child_3 = [];
        $child_4 = [];
        $child_5 = [];
        for ($i = 0; $i <= count($height_for_age); $i++) {
          if (isset($height_for_age[$i]['child_age'])) {
            $sorted_height_for_age = $this->sortChildAgeId($height_for_age[$i]['child_age']);
            // \Drupal::logger('pb_custom_standard_deviation')
            // ->notice('height_for_age => <pre><code>' .
            // $sorted_height_for_age . '</code></pre>');
            // if ($sorted_height_for_age === "43,44,45,46" ||
            // $sorted_height_for_age === "466,471,476,481" ||
            // $sorted_height_for_age === "596,601,606,611") {
            if ($sorted_height_for_age === "1st_month,2nd_month,3_4_months,5_6_months") {
              $child_1[] = $height_for_age[$i];
            }

            // If ($sorted_height_for_age === "47" ||
            // $sorted_height_for_age === "486" ||
            // $sorted_height_for_age === "616") {.
            if ($sorted_height_for_age === "7_9_months") {
              $child_2[] = $height_for_age[$i];
            }

            // If ($sorted_height_for_age === "48" ||
            // $sorted_height_for_age === "491" ||
            // $sorted_height_for_age === "621") {.
            if ($sorted_height_for_age === "10_12_months") {
              $child_3[] = $height_for_age[$i];
            }

            // If ($sorted_height_for_age === "49,50" ||
            // $sorted_height_for_age === "496,501" ||
            // $sorted_height_for_age === "626,631") {.
            if ($sorted_height_for_age === "13_18_months,19_24_months") {
              $child_4[] = $height_for_age[$i];
            }

            // If ($sorted_height_for_age === "51,52,57,58" ||
            // $sorted_height_for_age === "506,511,516,521" ||
            // $sorted_height_for_age === "636,641,646,651") {.
            if ($sorted_height_for_age === "25_36_months,37_48_months,49_60_months,61_72_months") {
              $child_5[] = $height_for_age[$i];
            }
          }
        }

        $sd_data = [];
        $sd_field_data = [];
        $sd_arr = [];
        for ($i = 1; $i <= 5; $i++) {
          $temp = "child_" . $i;
          $formatted_data = $this->customArrayFormatter($$temp[0]['child_age']);
          $sd_data['child_age'] = array_map(
            function ($elem) {
              return intval($elem);
            }, $formatted_data);
          for ($j = 0; $j < count($$temp); $j++) {
            $pinned_data = $this->customArrayFormatter($$temp[$j]['pinned_article']);
            $sd_field_data['articleID'] = array_map(
              function ($elem) {
                return intval($elem);
              }, $pinned_data);
            $title = str_replace("&#039;", "'", $$temp[$j]['title']);
            $title = str_replace("&quot;", '"', $title);
            $sd_field_data['name'] = $title;
            /* remove new line. */
            $body = str_replace("\n", '', $$temp[$j]['body']);
            $sd_field_data['text'] = $body;
            $sd_data[$sd_height_for_age_fields[$j]] = $sd_field_data;
          }
          $sd_arr[] = $sd_data;
        }

        $sd_final_data['height_for_age'] = $sd_arr;
        $rows['status'] = 200;
        /* To validate request params. */
        if (isset($request[3]) && !empty($request[3])) {
          $rows['langcode'] = $request[3];
        }
        $rows['data'] = $sd_final_data;
        return $this->serializer->serialize($rows, 'json', ['views_style_plugin' => $this]);
      }
      else {
        $rows = [];
        $rows['status'] = 204;
        $rows['message'] = "No Records Found";

        return $this->serializer->serialize($rows, 'json', ['views_style_plugin' => $this]);
      }
    }
    else {
      return $this->serializer->serialize($validate_params_res, 'json', ['views_style_plugin' => $this]);
    }
  }

  /**
   * To convert comma seperated string into array.
   */
  public function customArrayFormatter($values) {

    /* If the field have comma, */
    if (!empty($values) && strpos($values, ',') !== FALSE) {
      $formatted_data = explode(',', $values);
    }
    elseif (!empty($values)) {
      $formatted_data = [$values];
    }
    else {
      $formatted_data = [];
    }

    return $formatted_data;
  }

  /**
   * To check request params is correct.
   */
  public function checkRequestParams($request_uri) {
    $request = explode('/', $request_uri);
    if (isset($request[3]) && !empty($request[3])) {
      /* Get all enabled languages. */
      $languages = $this->languageManager->getLanguages();
      $languages = json_encode($languages);
      $languages = json_decode($languages, TRUE);
      $languages_arr = [];
      foreach ($languages as $lang_code => $lang_name) {
        $languages_arr[] = $lang_code;
      }
      if (!empty($languages_arr)) {
        if (!in_array($request[3], $languages_arr)) {
          $respons_arr['status'] = 400;
          $respons_arr['message'] = "Request language is wrong";
          return $respons_arr;
        }
      }
    }
  }

  /**
   * To sort child age id.
   */
  public function sortChildAgeId($child_age_id) {
    $child_age_arr = explode(',', $child_age_id);
    sort($child_age_arr);
    foreach ($child_age_arr as $key => $value) {
      $child_age_arr[$key] = $this->getTermNameById($value);
    }
    // $child_arr_length = count($child_age_arr);
    // for ($x = 0; $x < $child_arr_length; $x++) {
    // array_push($child_sorted_arr, $child_age_arr[$x]);
    // }
    // $child_sorted_arr = $child_age_arr;
    $child_age = implode(',', $child_age_arr);
    return $child_age;
  }

  /**
   * Get term name by ID.
   */
  public function getTermNameById($term_id) {
    // Load the term by ID.
    $term = Term::load($term_id);
    if ($term) {
      // Check if the field exists and has a value.
      if ($term->hasField('field_unique_name') && !$term->get('field_unique_name')->isEmpty()) {
        // Get the value of the field.
        return trim($term->get('field_unique_name')->value);
      }
    }
    // Return NULL or a default value if the term doesn't exist.
    return NULL;
  }

}
