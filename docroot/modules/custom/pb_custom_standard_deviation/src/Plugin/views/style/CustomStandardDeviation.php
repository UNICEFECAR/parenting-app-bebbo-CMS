<?php

namespace Drupal\pb_custom_standard_deviation\Plugin\views\style;

use Drupal\rest\Plugin\views\style\Serializer;

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
   * {@inheritdoc}
   */
  public function render() {

    $request_uri = \Drupal::service('path.current')->getPath(); /* Gives request path e.x (api/articles/en/1) */
    $request = explode('/', $request_uri);
    /* Validating request params to response error code */
    $validate_params_res = $this->checkRequestParams($request_uri);
    if (empty($validate_params_res)) {
      $sd_weight_for_height_fields = [
        "goodText", "warrningSmallHeightText",
        "emergencySmallHeightText", "warrningBigHeightText", "emergencyBigHeightText",
      ];
      $sd_height_for_age_fields = [
        "goodText", "warrningSmallLengthText",
        "emergencySmallLengthText", "warrningBigLengthText",
      ];
      $rows = [];
      $weight_for_height = [];
      $height_for_age = [];
      if (isset($this->view->result) && !empty($this->view->result)) {
        foreach ($this->view->result as $row_index => $row) {
          $this->view->row_index = $row_index;
          $view_render = $this->view->rowPlugin->render($row);
          $view_render = json_encode($view_render);
          $rendered_data = json_decode($view_render, TRUE);
          foreach ($rendered_data as $key => $values) {
            if ($key === 'growth_type' && $values === "6461") {
              $weight_for_height[] = $rendered_data;
            }

            if ($key === 'growth_type' && $values === "32786") {
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
          /* \Drupal::logger('pb_custom_standard_deviation')->notice('<pre><code>' . $weight_for_height[$i]['child_age'] . '</code></pre>'); */
          if ($weight_for_height[$i]['child_age'] === "46,45,44,43") {
            $child_1[] = $weight_for_height[$i];
          }

          if ($weight_for_height[$i]['child_age'] === "47") {
            $child_2[] = $weight_for_height[$i];
          }

          if ($weight_for_height[$i]['child_age'] === "48") {
            $child_3[] = $weight_for_height[$i];
          }

          if ($weight_for_height[$i]['child_age'] === "50,49") {
            $child_4[] = $weight_for_height[$i];
          }

          if ($weight_for_height[$i]['child_age'] === "58,57,52,51") {
            $child_5[] = $weight_for_height[$i];
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
            $sd_field_data['text'] = $$temp[$j]['body'];
            $sd_data[$sd_weight_for_height_fields[$j]] = $sd_field_data;
          }
          $sd_arr[] = $sd_data;
        }
        $sd_final_data['weight_for_height'] = $sd_arr;

        $child_1 = [];
        $child_2 = [];
        $child_3 = [];
        $child_4 = [];
        $child_5 = [];
        for ($i = 0; $i <= count($height_for_age); $i++) {
          /* \Drupal::logger('pb_custom_standard_deviation')->notice('<pre><code>' . $height_for_age[$i]['child_age'] . '</code></pre>'); */
          if ($height_for_age[$i]['child_age'] === "46,45,44,43") {
            $child_1[] = $height_for_age[$i];
          }

          if ($height_for_age[$i]['child_age'] === "47") {
            $child_2[] = $height_for_age[$i];
          }

          if ($height_for_age[$i]['child_age'] === "48") {
            $child_3[] = $height_for_age[$i];
          }

          if ($height_for_age[$i]['child_age'] === "50,49") {
            $child_4[] = $height_for_age[$i];
          }

          if ($height_for_age[$i]['child_age'] === "58,57,52,51") {
            $child_5[] = $height_for_age[$i];
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
            $sd_field_data['text'] = $$temp[$j]['body'];
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
      $languages = \Drupal::languageManager()->getLanguages();
      $languages = json_encode($languages);
      $languages = json_decode($languages, TRUE);
      $languages_arr = [];
      foreach ($languages as $lang_code => $lang_name) {
        $languages_arr[] = $lang_code;
      }
      if (isset($languages_arr) && !empty($languages_arr)) {
        if (!in_array($request[3], $languages_arr)) {
          $respons_arr['status'] = 400;
          $respons_arr['message'] = "Request language is wrong";

          return $respons_arr;
        }
      }
    }
  }

}
