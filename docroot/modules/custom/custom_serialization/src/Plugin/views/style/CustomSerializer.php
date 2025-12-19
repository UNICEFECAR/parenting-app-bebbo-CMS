<?php

namespace Drupal\custom_serialization\Plugin\views\style;

ini_set('serialize_precision', 6);

use Drupal\taxonomy\TermInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\rest\Plugin\views\style\Serializer;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\language_visibility_control\LanguageVisibilityService;
use Drupal\custom_serialization\Service\CustomSerializerHelper;

/**
 * The style plugin for serialized output formats.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "custom_serialization",
 *   title = @Translation("Custom serialization"),
 *   help = @Translation("Serializes views row data using the Serializer
 *   component."), display_types = {"data"}
 * )
 */
class CustomSerializer extends Serializer {
  /**
   * The helper service for caching and batch loading.
   *
   * Provides:
   * - Batch entity loading to reduce database queries
   * - Request-level static caching for entities
   * - Persistent caching for external API calls (Vimeo)
   * - Cached lookups for frequently accessed data (taxonomy terms)
   *
   * @var \Drupal\custom_serialization\Service\CustomSerializerHelper
   */
  protected $helper;

  /**
   * The language visibility control service.
   *
   * @var \Drupal\language_visibility_control\LanguageVisibilityService
   */
  protected $languageVisibilityService;

  /**
   * The current path service.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    SerializerInterface $serializer,
    array $serializer_formats,
    array $serializer_format_providers,
    CurrentPathStack $current_path,
    RequestStack $request_stack,
    Connection $database,
    LanguageManagerInterface $language_manager,
    EntityTypeManagerInterface $entity_type_manager,
    LanguageVisibilityService $language_visibility_service,
    CustomSerializerHelper $helper,
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $serializer,
      $serializer_formats,
      $serializer_format_providers
    );
    $this->currentPath = $current_path;
    $this->requestStack = $request_stack;
    $this->database = $database;
    $this->languageManager = $language_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->languageVisibilityService = $language_visibility_service;
    // Inject the helper service for caching and batch loading.
    $this->helper = $helper;
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
      $container->get('request_stack'),
      $container->get('database'),
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('language_visibility_control.service'),
      // Inject the helper service for improved performance.
      $container->get('custom_serialization.helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    /* Gives request path e.x (api/articles/en/1) */
    $request_uri = $this->currentPath->getPath();
    $request = explode('/', $request_uri);
    $request_path = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost();

    // Cache URI type checks to avoid repeated strpos() calls.
    // This improves performance by checking each pattern once.
    $is_country_groups = (strpos($request_uri, "api/country-groups") !== FALSE);
    $is_articles = (strpos($request_uri, "api/articles") !== FALSE);
    $is_taxonomies = (strpos($request_uri, "api/taxonomies") !== FALSE);
    $is_pinned_contents = (strpos($request_uri, "pinned-contents") !== FALSE);
    $is_basic_pages = (strpos($request_uri, "basic-pages") !== FALSE);
    $is_sponsors = (strpos($request_uri, "sponsors") !== FALSE);
    $is_vocabularies = (strpos($request_uri, "vocabularies") !== FALSE);

    /* Validating request params to response error code. */
    if ($is_country_groups) {
      $validate_params_res = '';
    }
    else {
      $validate_params_res = $this->checkRequestParams($request_uri);
    }

    if (empty($validate_params_res)) {
      $array_of_multiple_values = [
        "child_age", "keywords", "related_articles", "related_video_articles", "related_activities",
        "language", "related_milestone", "embedded_images",
      ];
      $media_fields = [
        "cover_image", "country_flag", "country_sponsor_logo", "unicef_logo", "country_national_partner",
        "cover_video",
      ];
      $pinned_content = [
        "vaccinations", "child_growth", "health_check_ups", "child_development",
      ];
      $string_to_int = [
        "id", "field_type_of_article", "category", "subcategory", "child_gender", "parent_gender", "licensed", "premature",
        "mandatory", "growth_type", "standard_deviation", "boy_video_article", "girl_video_article",
        "growth_period", "activity_category", "equipment", "type_of_support",
        "make_available_for_mobile", "pinned_article", "pinned_video_article", "chatbot_subcategory",
        "related_article", "old_calendar",
      ];
      $string_to_array_of_int = [
        "related_articles", "keywords", "child_age", "related_activities", "related_video_articles",
        "related_milestone",
      ];

      $rows = [];
      $data = [];
      $field_formatter = [];
      $uniques = [];
      // Use helper service for timestamp generation.
      // This avoids global timezone change that could affect other modules.
      $timestamp = $this->helper->getCurrentTimestamp('Asia/Kolkata', 'Y-m-d H:i');
      if (isset($this->view->result) && !empty($this->view->result)) {
        if (isset($request[3])) {
          $language_code = $request[3];
        }
        else {
          $language_code = '';
        }
        foreach ($this->view->result as $row_index => $row) {
          $this->view->row_index = $row_index;

          // JSON encode/decode is required to properly convert Drupal Markup
          // objects and render arrays to primitive values (strings, integers).
          // Direct array casting keeps Markup objects which don't work as IDs.
          $view_render = $this->view->rowPlugin->render($row);
          $view_render = json_encode($view_render);
          $rendered_data = json_decode($view_render, TRUE);
          // Custom country listing - use cached boolean.
          if ($is_country_groups
            && isset($rendered_data['CountryID'])
            && $rendered_data['CountryID'] == 131
          ) {
            continue;
          }
          /* error_log("type =>".$rendered_data['type']); */
          /* Custom pinned api formatter - use cached boolean. */
          if ($is_pinned_contents && isset($request[4]) && in_array($request[4], $pinned_content)) {
            if ($rendered_data['type'] === "Article") {
              unset($rendered_data['cover_video']);
              unset($rendered_data['cover_video_image']);
            }
            elseif ($rendered_data['type'] === "Video Article") {
              unset($rendered_data['cover_image']);
              $rendered_data['cover_image'] = $rendered_data['cover_video_image'];
              unset($rendered_data['cover_video_image']);
            }
          }
          /* Add unique field to Basic page API - use cached boolean and helper. */
          if ($is_basic_pages && $rendered_data['type'] === "Basic page") {
            // Use helper service for batch node title lookup (cached).
            $node_titles = $this->helper->getNodeTitlesBatch([$rendered_data['id']], 'en');
            if (!empty($node_titles[$rendered_data['id']])) {
              $basic_title = $node_titles[$rendered_data['id']];
              $basic_page = strtolower($basic_title);
              $basic_page = str_replace(' ', '_', $basic_page);
              $rendered_data['unique_name'] = $basic_page;
            }
            else {
              $rendered_data['unique_name'] = "";
            }
          }
          $embedded_images = [];
          // $languages_all = [];
          foreach ($rendered_data as $key => $values) {
            /* Replace special charater into normal. */
            if ($key === "title") {
              $title = str_replace("&#039;", "'", $values);
              $title = str_replace("&quot;", '"', $title);
              $rendered_data[$key] = htmlspecialchars_decode($title);
            }
            // Added for FAQ.
            if ($key === "question") {
              $question = str_replace("&#039;", "'", $values);
              $question = str_replace("&quot;", '"', $question);
              $rendered_data[$key] = htmlspecialchars_decode($question);
            }

            /* Change video or image actual path to absolute path. */
            if ($key === "body" || $key === "summary" || $key === "answer_part_1" || $key === "answer_part_2") {
              $body_summary = str_replace('src="/sites/default/files/', 'src="' . $request_path . '/sites/default/files/', $values);
              $body_summary = str_replace('src="/media/oembed', 'src="' . $request_path . '/media/oembed', $body_summary);
              /* remove new line. */
              $body_summary = str_replace("\n", '', $body_summary);
              /* Remove span tag from body and summary field */
              $body_summary = preg_replace('/<span[^>]+\>|<\/span>/i', '', $body_summary);
              /* Remove empty <p> </p> tag */
              $body_summary = str_replace("<p> </p>", '', $body_summary);
              /* Remove strong <strong> </strong> tag */
              $body_summary = str_replace("<strong> </strong>", '', $body_summary);
              /* remove inline style attribute */
              $body_summary = preg_replace('/(<[^>]*) style=("[^"]+"|\'[^\']+\')([^>]*>)/i', '$1$3', $body_summary);
              /* Remove empty <p> </p> tag */
              $body_summary = str_replace("<p> </p>", '', $body_summary);
              /* Remove empty <strong> </strong> tag */
              $body_summary = str_replace("<strong> </strong>", '', $body_summary);
              /* Remove width and height of remote video */
              $body_summary = str_replace('width="640"', '', $body_summary);
              $body_summary = str_replace('height="480"', '', $body_summary);

              /* Remove div Image label tag */
              $body_summary = str_replace("<div class=\"field__label visually-hidden\">Image</div>", '', $body_summary);
              $body_summary = html_entity_decode($body_summary, ENT_QUOTES | ENT_HTML5, 'UTF-8');

              /* Embedded images. */
              if ($rendered_data['type'] == "Article" || $rendered_data['type'] == "Games" || $rendered_data['type'] == "Basic page" || $rendered_data['type'] == "Video Article") {
                $rendered_data[$key] = $body_summary;
                if (!empty($body_summary)) {
                  $doc = new \DOMDocument();
                  libxml_use_internal_errors(TRUE);
                  $doc->loadHTML($body_summary);
                  // Get the images.
                  $images = $doc->getElementsByTagName('img');

                  foreach ($images as $image) {
                    $embedded_images[] = $image->getAttribute('src');
                  }
                }
                $rendered_data['embedded_images'] = $this->processEmbeddedImages($embedded_images);
              }
              else {
                $rendered_data[$key] = $body_summary;
              }
            }
            /* Custom image & video formattter.To check media image field exist  */
            if (in_array($key, $media_fields)) {
              $media_formatted_data = $this->customMediaFormatter($key, $values, $language_code, $request_uri);
              $rendered_data[$key] = $media_formatted_data;
            }
            /* Custom array formatter.To check mulitple field.  */
            if (in_array($key, $array_of_multiple_values)) {
              $array_formatted_data = $this->customArrayFormatter($values);
              /* Convert array to array of int. */
              if (in_array($key, $string_to_array_of_int)) {
                $rendered_data[$key] = array_map(function ($elem) {
                  return intval($elem);
                }, $array_formatted_data);
              }
              else {
                $rendered_data[$key] = $array_formatted_data;
              }
            }

            /* Convert string to int. */
            if (in_array($key, $string_to_int)) {
              if (!empty($values)) {
                // Convert Markup objects to string before casting to int.
                $string_value = is_object($values) ? (string) $values : $values;
                $rendered_data[$key] = (int) $string_value;
              }
              else {
                $rendered_data[$key] = 0;
              }
            }

            /* Custom Taxonomy Field Formatter - use cached booleans. */
            if ($is_vocabularies || $is_taxonomies) {
              /* If the field have comma. */
              if (!empty($values) && strpos($values, ',') !== FALSE) {
                /* remove keywords from taxonomy res */
                if ($values != "keywords,Keywords") {
                  $formatted_data = explode(',', $values);
                  $vocabulary_name = $formatted_data[1];
                  $vocabulary_machine_name = $formatted_data[0];
                  $taxonomy_data = $this->customTaxonomyFieldFormatter($request_uri, $key, $vocabulary_name, $vocabulary_machine_name, $language_code);
                  /* $this->logger->notice('<pre><code>' . print_r($taxonomy_data, TRUE) . '</code></pre>'); */
                  $field_formatter[$formatted_data[0]] = $taxonomy_data;
                }
              }
            }

            // Handle CountryID 126 (special case) - use cached boolean.
            if ($is_country_groups && isset($rendered_data['CountryID']) && $rendered_data['CountryID'] == 126) {
              $display_ru = $display_en = $custom_locale_en = $custom_luxon_en = $custom_plural_en = $custom_locale_ru = $custom_luxon_ru = $custom_plural_ru = '';

              // Batch load language data for en and ru using helper.
              $lang_data_batch = $this->helper->getLanguageDataBatch(['en', 'ru']);

              // Use helper for cached ConfigurableLanguage loading.
              $languages_en = $this->helper->getConfigurableLanguage('en');
              if ($languages_en && $languages_en->label()) {
                $display_en = $languages_en->label();
              }

              if (!empty($lang_data_batch['en'])) {
                $custom_locale_en = $lang_data_batch['en']['custom_locale'];
                $custom_luxon_en = $lang_data_batch['en']['custom_luxon'];
                $custom_plural_en = $lang_data_batch['en']['custom_plural'];
              }

              // Use helper for cached ConfigurableLanguage loading.
              $languages_ru = $this->helper->getConfigurableLanguage('ru');
              if ($languages_ru && $languages_ru->label()) {
                $display_ru = $languages_ru->label();
              }

              if (!empty($lang_data_batch['ru'])) {
                $custom_locale_ru = $lang_data_batch['ru']['custom_locale'];
                $custom_luxon_ru = $lang_data_batch['ru']['custom_luxon'];
                $custom_plural_ru = $lang_data_batch['ru']['custom_plural'];
              }

              // Set up "Rest of the World" entry.
              $rendered_data['name'] = 'Rest of the world';
              $rendered_data['displayName'] = 'Rest of the world';
              $rendered_data['languages'] = [
                 [
                   'name' => 'English',
                   'displayName' => $display_en,
                   'languageCode' => 'en',
                   'locale' => $custom_locale_en,
                   'luxonLocale' => $custom_luxon_en,
                   'pluralShow' => $custom_plural_en,
                 ],
                 [
                   'name' => 'Russian',
                   'displayName' => $display_ru,
                   'languageCode' => 'ru',
                   'locale' => $custom_locale_ru,
                   'luxonLocale' => $custom_luxon_ru,
                   'pluralShow' => $custom_plural_ru,
                 ],
              ];
              unset($rendered_data['langcode']);
              unset($rendered_data['field_make_available_for_mobile']);
              unset($rendered_data['logo']);
              unset($rendered_data['field_country_national_partner']);
              unset($rendered_data['published']);
            }
            // Handle all other countries - use cached boolean and helper.
            if ($is_country_groups && isset($rendered_data['CountryID']) && $rendered_data['CountryID'] != 126) {
              // Use helper for cached Group entity loading.
              $groups = $this->helper->getGroupEntity($rendered_data['CountryID']);
              if (!$groups) {
                continue;
              }
              $country_languages = $groups->get('field_language')->getValue();
              $rendered_data['languages'] = [];

              // Collect all langcodes first for batch loading.
              $langcodes_to_load = [];
              foreach ($country_languages as $val) {
                if (!empty($val['value'])) {
                  $langcodes_to_load[] = $val['value'];
                }
              }

              // Batch load all language data and ConfigurableLanguage entities.
              $lang_data_batch = $this->helper->getLanguageDataBatch($langcodes_to_load);
              $config_languages_batch = $this->helper->loadConfigurableLanguagesBatch($langcodes_to_load);

              foreach ($country_languages as $val) {
                $langcode = $val['value'];

                if ($langcode) {
                  // Check if the language still exists (not disabled).
                  $language = $this->languageManager->getLanguage($langcode);
                  if (!$language) {
                    continue;
                  }

                  // Use batch-loaded ConfigurableLanguage entity.
                  $config_language = $config_languages_batch[$langcode] ?? NULL;
                  if (!$config_language) {
                    continue;
                  }

                  $view_weight = $config_language->get('weight') ?? 0;

                  // Use batch-loaded language data.
                  $existing_data_all = $lang_data_batch[$langcode] ?? [];

                  // Initialize variables.
                  $custom_locale_all = $custom_luxon_all = $custom_plural_all = '';
                  $custom_language_name_local = '';

                  if (!empty($existing_data_all)) {
                    $custom_locale_all = $existing_data_all['custom_locale'] ?? '';
                    $custom_luxon_all = $existing_data_all['custom_luxon'] ?? '';
                    $custom_plural_all = $existing_data_all['custom_plural'] ?? '';
                    $custom_language_name_local = !empty($existing_data_all['custom_language_name_local']) ?
                      $existing_data_all['custom_language_name_local'] : '';
                  }

                  // Add the language data to the array.
                  $rendered_data['languages'][] = [
                  // Adjust as necessary.
                    'name' => $rendered_data['name'],
                    'displayName' => $custom_language_name_local,
                    'languageCode' => $val['value'],
                    'locale' => $custom_locale_all,
                    'luxonLocale' => $custom_luxon_all,
                    'pluralShow' => $custom_plural_all,
                    'view_weight' => $view_weight,
                  ];
                }
              }

              // Apply language visibility filtering if the service exists.
              if ($this->languageVisibilityService) {
                $rendered_data['languages'] = $this->languageVisibilityService->filterLanguageDataForApi($rendered_data['languages'], $groups);
              }

              // Reorder the array to place the preferred language code first.
              usort($rendered_data['languages'], function ($a, $b) {
                return $a['view_weight'] <=> $b['view_weight'];
              });

              // Remove view_weight from each language entry in the array.
              foreach ($rendered_data['languages'] as &$values_lng) {
                unset($values_lng['view_weight']);
              }
              unset($rendered_data['langcode']);
              unset($rendered_data['field_make_available_for_mobile']);
              unset($rendered_data['logo']);
              unset($rendered_data['field_country_national_partner']);
              unset($rendered_data['published']);
              unset($rendered_data['field_language']);
            }
          }

          // Use cached booleans for strpos checks.
          if ($is_vocabularies || $is_taxonomies) {
            $data = $field_formatter;
            $rows['status'] = 200;
          }
          else {
            /* E error_log("data =>".print_r($rendered_data, true)); */
            $rows['status'] = 200;
            // Use cached boolean and add check for related-article-contents.
            $is_related_articles = (strpos($request_uri, "related-article-contents") !== FALSE);
            if ($is_pinned_contents || $is_related_articles) {
              if (!in_array($rendered_data['id'], $uniques)) {
                $uniques[] = $rendered_data['id'];
                $data[] = $rendered_data;
              }
              /* To get total no of records. */
              $rows['total'] = count($data);
            }
            else {
              $data[] = $rendered_data;
              /* To get total no of records. */
              $rows['total'] = count($data);
            }
          }
          // Use cached boolean check for archive.
          $is_archive = (strpos($request_uri, "archive") !== FALSE);
          if ($is_archive) {
            $type = $rendered_data['type'];
            $total_ids[] = $rendered_data['id'];
            $types[$type][] = +$rendered_data['id'];
            $data = $types;
            $rows['total'] = count($total_ids);

          }
        }

        // Use cached booleans for taxonomy/articles filtering.
        if ($is_taxonomies || $is_articles) {
          if ($is_articles) {
            $term_name_arr = ['Pregnancy'];
          }
          else {
            $query_params = $this->requestStack->getCurrentRequest()->query->all();
            if (isset($query_params['pregnancy']) && $query_params['pregnancy'] == 'true') {
              // pregnancy,Week by Week (if above condition
              // gets true we are removing it
              // from term array to display pregnancy in child age taxo)
              $term_name_arr = [];
            }
            else {
              // To hide pregnancy term in child age taxo.
              $term_name_arr = ['pregnancy'];
            }
          }
          // Use helper service for batch term loading instead of loading
          // one-by-one.
          if (!empty($term_name_arr)) {
            $term_map = $this->helper->getTermIdsByNames($term_name_arr);
            foreach ($term_name_arr as $val) {
              if (isset($term_map[$val])) {
                foreach ($term_map[$val] as $term_info) {
                  $data = $this->removeItemsByKeyValue(
                    $request_uri,
                    $data,
                    $term_info['vid'],
                    $term_info['tid']
                  );
                }
              }
            }
          }
        }

        // Use cached boolean for country-groups.
        if ($is_country_groups) {
          $index = array_search('126', array_column($data, 'CountryID'));

          // Check if the entry exists.
          if ($index !== FALSE) {
            // Remove the entry from the array.
            $entry = array_splice($data, $index, 1);

            // Append the entry to the end of the array.
            $data[] = $entry[0];
          }
        }

        /* To validate request params. - Use cached booleans */
        if (isset($request[3]) && !empty($request[3])) {
          $rows['langcode'] = $request[3];
        }

        if ($is_country_groups) {
          $rows['langcode'] = 'en';
        }

        if ($is_sponsors) {
          unset($rows['langcode']);
        }
        $rows['datetime'] = $timestamp;
        $rows['data'] = $data;
        unset($this->view->row_index);

        /* Json output. */
        if ((empty($this->view->live_preview)) && method_exists($this->displayHandler, 'getContentType')) {
          $content_type = $this->displayHandler->getContentType();
        }
        else {
          $content_type = !empty($this->options['formats']) ? reset($this->options['formats']) : 'json';
        }

        // Return serialized response - let Drupal Views caching handle
        // HTTP cache headers.
        return $this->serializer->serialize(
          $rows,
          $content_type,
          ['views_style_plugin' => $this]
        );

      }
      else {
        $rows = [];
        $rows['status'] = 204;
        $rows['message'] = "No Records Found";
        $rows['datetime'] = $timestamp;

        return $this->serializer->serialize($rows, 'json', ['views_style_plugin' => $this]);
      }
    }
    else {
      return $this->serializer->serialize($validate_params_res, 'json', ['views_style_plugin' => $this]);
    }
  }

  /**
   * Check if request params are valid.
   *
   * Validates language codes and country group IDs.
   * Uses helper service for cached group loading to avoid
   * loading all groups on every request.
   *
   * @param string $request_uri
   *   The request URI.
   *
   * @return array|string
   *   Error array with status and message, or empty string if valid.
   */
  public function checkRequestParams($request_uri) {
    $request = explode('/', $request_uri);
    if (isset($request[3]) && !empty($request[3])) {
      if (strpos($request_uri, "sponsors") !== FALSE) {
        if ($request[3] == "all") {
          return "";
        }
        else {
          // Use helper service for cached group IDs instead of loading
          // all groups.
          $gids = $this->helper->getCountryGroupIds();
          if (!in_array($request[3], $gids)) {
            $respons_arr['status'] = 400;
            $respons_arr['message'] = "Request country code is wrong";

            return $respons_arr;
          }
        }
      }
      else {
        /* Get all enabled languages - no JSON encode/decode needed. */
        $languages = $this->languageManager->getLanguages();
        $languages_arr = array_keys($languages);
        if (!empty($languages_arr)) {
          if (!in_array($request[3], $languages_arr)) {
            $respons_arr['status'] = 400;
            $respons_arr['message'] = "Request language is wrong";

            return $respons_arr;
          }
        }

        // Check if language is visible in mobile app for any country group.
        if ($this->languageVisibilityService) {
          $requested_language = $request[3];
          $is_language_visible = FALSE;

          // Use helper service for cached groups instead of
          // Group::loadMultiple().
          $language_visibility_service = $this->languageVisibilityService;
          $groups = $this->helper->getCountryGroups();
          foreach ($groups as $group) {
            if ($group->bundle() === 'country') {
              $visible_languages = $language_visibility_service->getVisibleLanguages($group);
              if (in_array($requested_language, $visible_languages)) {
                $is_language_visible = TRUE;
                break;
              }
            }
          }

          if (!$is_language_visible) {
            $respons_arr['status'] = 403;
            $respons_arr['message'] = "Language not available";

            return $respons_arr;
          }
        }
      }
    }
    return "";
  }

  /**
   * To convert comma seperated string into array.
   */
  public function customArrayFormatter($values) {
    if (!empty($values) && strpos($values, ',') !== FALSE) {
      /* If the field have comma. */
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
   * Get media file details with caching and performance optimizations.
   *
   * This method uses the helper service for:
   * - Cached media entity loading (request-level cache)
   * - Cached ImageStyle loading (loaded once per request)
   * - Cached Vimeo API calls (persistent cache, 24 hours)
   * - Cached file entity loading (request-level cache)
   *
   * @param string $key
   *   The field key (e.g., 'cover_image', 'cover_video').
   * @param mixed $values
   *   The media entity ID.
   * @param string $language_code
   *   The language code.
   * @param string $request_uri
   *   The current request URI.
   *
   * @return array
   *   The formatted media data array.
   */
  public function customMediaFormatter($key, $values, $language_code, $request_uri = '') {
    if (empty($values)) {
      // Return empty structure for empty values.
      return $this->getEmptyMediaData($key);
    }

    // Use helper service for cached media entity loading.
    $media_entity = $this->helper->getMediaEntity($values);
    if (!$media_entity) {
      return $this->getEmptyMediaData($key);
    }

    $media_type = $media_entity->bundle();
    $base_url = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost();
    // Flag for video-articles cover_image special handling.
    $is_video_article_cover = (strpos($request_uri, "video-articles") !== FALSE && $key === "cover_image");

    // Process based on media type.
    switch ($media_type) {
      case 'image':
        return $this->processImageMedia($media_entity, $key, $values, $language_code, $request_uri, $is_video_article_cover);

      case 'remote_video':
        return $this->processRemoteVideoMedia($media_entity, $key, $base_url, $is_video_article_cover);

      case 'video':
        return $this->processVideoMedia($media_entity, $key, $is_video_article_cover);

      default:
        return $this->getEmptyMediaData($key);
    }
  }

  /**
   * Process image media type.
   *
   * @param \Drupal\media\Entity\Media $media_entity
   *   The media entity.
   * @param string $key
   *   The field key.
   * @param mixed $values
   *   The media entity ID.
   * @param string $language_code
   *   The language code.
   * @param string $request_uri
   *   The request URI.
   * @param bool $is_video_article_cover
   *   Whether this is a video article cover image.
   *
   * @return array
   *   The formatted media data.
   */
  protected function processImageMedia($media_entity, $key, $values, $language_code, $request_uri, $is_video_article_cover) {
    $url = $mname = $malt = '';

    $mid = $media_entity->get('field_media_image')->target_id;
    if (!empty($mid)) {
      $mname = $media_entity->get('name')->value;

      // Get alt text - try language-specific first, then fallback.
      // Use helper for batch alt text lookup (cached within request).
      $alt_texts = $this->helper->getMediaAltTextBatch([$values], $language_code);
      if (!empty($alt_texts[$values])) {
        $malt = $alt_texts[$values];
      }
      else {
        $malt_field = $media_entity->get('field_media_image')->getValue();
        $malt = $malt_field[0]['alt'] ?? '';
      }

      // Get file URI using helper for batch lookup (cached within request).
      $file_uris = $this->helper->getFileUrisBatch([$mid]);
      $uri = $file_uris[$mid] ?? '';

      // Use helper for cached ImageStyle loading.
      $image_style = $this->helper->getImageStyle('content_1200xh_');
      if ($image_style && !empty($uri)) {
        $url = $image_style->buildUrl($uri);
      }

      // Convert to WebP unless video-articles cover_image.
      if (!$is_video_article_cover) {
        $url = $this->helper->convertToWebp($url);
      }
    }

    return [
      'url' => $url,
      'name' => $mname,
      'alt' => $malt,
    ];
  }

  /**
   * Process remote video media type (YouTube/Vimeo).
   *
   * Uses helper service for cached Vimeo API calls.
   *
   * @param \Drupal\media\Entity\Media $media_entity
   *   The media entity.
   * @param string $key
   *   The field key.
   * @param string $base_url
   *   The base URL.
   * @param bool $is_video_article_cover
   *   Whether this is a video article cover image.
   *
   * @return array
   *   The formatted media data.
   */
  protected function processRemoteVideoMedia($media_entity, $key, $base_url, $is_video_article_cover) {
    $oembed_value = $media_entity->get('field_media_oembed_video')->value;
    $mname = $media_entity->get('name')->value;
    $is_vimeo = (stripos($oembed_value, 'vimeo') !== FALSE);
    $site = $is_vimeo ? 'vimeo' : 'youtube';

    $media_data = [
      'url' => $oembed_value,
      'name' => $mname,
      'site' => $site,
    ];

    // Handle cover_image special case.
    if ($key === "cover_image") {
      $tid = $media_entity->get('thumbnail')->target_id;
      $urls = '';

      if (!empty($tid)) {
        if ($is_vimeo) {
          // Use helper service for cached Vimeo API call.
          // This avoids external API calls on every request.
          $vimeo_video_id = $this->helper->extractVimeoId($oembed_value);
          if ($vimeo_video_id) {
            $urls = $this->helper->getVimeoThumbnail($vimeo_video_id);
          }
          // Fallback if Vimeo API fails.
          if (empty($urls) || $urls === NULL) {
            $urls = '';
          }
        }
        else {
          // YouTube or other - use file thumbnail.
          $thumbnail = $this->helper->getFileEntity($tid);
          if ($thumbnail) {
            $thumbnail_url = $thumbnail->createFileUrl();
            if (strpos($thumbnail_url, $base_url) !== FALSE) {
              $urls = $thumbnail_url;
            }
            else {
              $urls = $base_url . $thumbnail_url;
            }
          }
        }
      }

      // Convert to WebP unless video-articles cover_image.
      if (!$is_video_article_cover && !empty($urls)) {
        $urls = $this->helper->convertToWebp($urls);
      }

      $media_data = [
        'url' => $urls,
        'name' => $mname,
        'alt' => '',
      ];
    }

    return $media_data;
  }

  /**
   * Process video (file) media type.
   *
   * @param \Drupal\media\Entity\Media $media_entity
   *   The media entity.
   * @param string $key
   *   The field key.
   * @param bool $is_video_article_cover
   *   Whether this is a video article cover image.
   *
   * @return array
   *   The formatted media data.
   */
  protected function processVideoMedia($media_entity, $key, $is_video_article_cover) {
    $mname = $media_entity->get('name')->value;
    $video_field_value = $media_entity->get('field_media_video_file')->value ?? '';
    $site = (stripos($video_field_value, 'vimeo') !== FALSE) ? 'vimeo' : 'youtube';
    $mid = $media_entity->get('field_media_video_file')->target_id;
    $url = '';

    if (!empty($mid)) {
      // Use helper for cached file loading.
      $file = $this->helper->getFileEntity($mid);
      if ($file) {
        $url = $file->createFileUrl();
      }
    }

    $media_data = [
      'url' => $url,
      'name' => $mname,
      'site' => $site,
    ];

    // Handle cover_image special case.
    if ($key === "cover_image") {
      $tid = $media_entity->get('thumbnail')->target_id;
      $thumbnail_url = '';

      if (!empty($tid)) {
        // Use helper for cached file loading.
        $thumbnail = $this->helper->getFileEntity($tid);
        if ($thumbnail) {
          $thumbnail_url = $thumbnail->createFileUrl();
        }
      }

      // Convert to WebP unless video-articles cover_image.
      if (!$is_video_article_cover && !empty($thumbnail_url)) {
        $thumbnail_url = $this->helper->convertToWebp($thumbnail_url);
      }

      $media_data = [
        'url' => $thumbnail_url,
        'name' => $mname,
        'alt' => '',
      ];
    }

    return $media_data;
  }

  /**
   * Get empty media data structure based on field key.
   *
   * @param string $key
   *   The field key.
   *
   * @return array
   *   Empty media data structure.
   */
  protected function getEmptyMediaData($key) {
    if ($key === "cover_video") {
      return [
        'url' => '',
        'name' => '',
        'site' => '',
      ];
    }

    return [
      'url' => '',
      'name' => '',
      'alt' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function customTaxonomyFieldFormatter($request_uri, $key, $vocabulary_name, $vocabulary_machine_name, $language_code) {
    /* Vocabularies Field formatter. */
    if (strpos($request_uri, "vocabularies") !== FALSE) {
      $termName = str_replace("&#039;", "'", $vocabulary_name);
      $vocabulary_data = [
        $key => $termName,
      ];
      return $vocabulary_data;
    }

    /* Taxonomies Field formatter. */
    if (strpos($request_uri, "taxonomies") !== FALSE) {
      $term_data = [];
      $tax_query = $this->database->select('taxonomy_term_field_data');
      $tax_query->condition('vid', $vocabulary_machine_name);
      $tax_query->condition('langcode', $language_code);
      $tax_query->condition('status', 1);
      $tax_query->fields('taxonomy_term_field_data');
      $tax_query->addExpression("CASE WHEN vid = 'child_age' THEN weight ELSE 999999 END", 'sorted_weight');
      $tax_query->orderBy('vid', 'ASC');
      $tax_query->orderBy('sorted_weight', 'ASC');
      $tax_result = $tax_query->execute()->fetchAll();

      // Batch load all taxonomy terms at once to avoid N+1 queries.
      // This significantly improves performance for large vocabularies.
      $term_ids = array_column($tax_result, 'tid');
      $term_entities = $this->helper->loadTaxonomyTermsBatch($term_ids);

      $tax_count = count($tax_result);
      for ($tax = 0; $tax < $tax_count; $tax++) {
        $tid = $tax_result[$tax]->tid;
        $term_obj = $term_entities[$tid] ?? NULL;

        if ($vocabulary_machine_name === "growth_period") {
          /** @var \Drupal\taxonomy\TermInterface $term_obj */
          $term_data[] = [
            'id' => (int) $tid,
            'name' => $tax_result[$tax]->name,
            'vaccination_opens' => $term_obj ? (int) $term_obj->get('field_vaccination_opens')->value : 0,
          ];
        }
        elseif ($vocabulary_machine_name === "child_age") {
          /** @var \Drupal\taxonomy\TermInterface $term_obj */
          $age_bracket_arr = [];
          if ($term_obj) {
            $age_bracket = $term_obj->get('field_age_bracket')->getValue();
            foreach ($age_bracket as $agevalue) {
              $age_bracket_arr[] = (int) $agevalue['target_id'];
            }
          }
          $term_data[] = [
            'id' => (int) $tid,
            'name' => $tax_result[$tax]->name,
            'days_from' => $term_obj ? (int) $term_obj->get('field_days_from')->value : 0,
            'days_to' => $term_obj ? (int) $term_obj->get('field_days_to')->value : 0,
            'buffers_days' => $term_obj ? (int) $term_obj->get('field_buffers_days')->value : 0,
            'age_bracket' => $age_bracket_arr,
          ];
        }
        elseif ($vocabulary_machine_name === "growth_introductory") {
          /** @var \Drupal\taxonomy\TermInterface $term_obj */
          $term_data[] = [
            'id' => (int) $tid,
            'name' => $tax_result[$tax]->name,
            'body' => $tax_result[$tax]->description__value,
            'days_from' => $term_obj ? (int) $term_obj->get('field_days_from')->value : 0,
            'days_to' => $term_obj ? (int) $term_obj->get('field_days_to')->value : 0,
          ];
        }
        elseif ($vocabulary_machine_name === "standard_deviation") {
          /** @var \Drupal\taxonomy\TermInterface $term_obj */
          $sd0 = $term_obj ? (float) $term_obj->get('field_sd0')->value : 0.0;
          $sd1 = $term_obj ? (float) $term_obj->get('field_sd1')->value : 0.0;
          $sd2 = $term_obj ? (float) $term_obj->get('field_sd2')->value : 0.0;
          $sd3 = $term_obj ? (float) $term_obj->get('field_sd3')->value : 0.0;
          $sd4 = $term_obj ? (float) $term_obj->get('field_sd4')->value : 0.0;
          $sd1neg = $term_obj ? (float) $term_obj->get('field_sd1neg')->value : 0.0;
          $sd2neg = $term_obj ? (float) $term_obj->get('field_sd2neg')->value : 0.0;
          $sd3neg = $term_obj ? (float) $term_obj->get('field_sd3neg')->value : 0.0;
          $sd4neg = $term_obj ? (float) $term_obj->get('field_sd4neg')->value : 0.0;
          $term_name = (float) $tax_result[$tax]->name;

          $term_data[] = [
            'id' => (int) $tid,
            'name' => round($term_name, 3),
            'child_gender' => $term_obj ? (int) $term_obj->get('field_child_gender')->target_id : 0,
            'growth_type' => $term_obj ? (int) $term_obj->get('field_growth_type')->target_id : 0,
            'sd0' => round($sd0, 3),
            'sd1' => round($sd1, 3),
            'sd2' => round($sd2, 3),
            'sd3' => round($sd3, 3),
            'sd4' => round($sd4, 3),
            'sd1neg' => round($sd1neg, 3),
            'sd2neg' => round($sd2neg, 3),
            'sd3neg' => round($sd3neg, 3),
            'sd4neg' => round($sd4neg, 3),
          ];
        }
        elseif ($vocabulary_machine_name === "chatbot_subcategory") {
          /** @var \Drupal\taxonomy\TermInterface $term_obj */
          $term_data[] = [
            'id' => (int) $tid,
            'name' => $tax_result[$tax]->name,
            'parent_category_id' => $term_obj ? (int) $term_obj->get('field_chatbot_category')->target_id : 0,
            'unique_name' => $term_obj ? $term_obj->get('field_unique_name')->value : '',
          ];
        }
        elseif ($vocabulary_machine_name === "category") {
          /** @var \Drupal\taxonomy\TermInterface $term_obj */
          $field_type_of_article = '';
          if ($term_obj) {
            $field_type_of_article_entity = $term_obj->get('field_type_of_article')->entity ?? NULL;
            $field_type_of_article = $field_type_of_article_entity instanceof TermInterface
              ? ($field_type_of_article_entity->get('name')->value ?? '')
              : '';
          }
          $term_data[] = [
            'id' => (int) $tid,
            'name' => $tax_result[$tax]->name,
            'unique_name' => $term_obj ? $term_obj->get('field_unique_name')->value : '',
            'field_type_of_article' => $field_type_of_article,
          ];
        }
        elseif ($vocabulary_machine_name === "growth_type" || $vocabulary_machine_name === "activity_category" || $vocabulary_machine_name === "child_gender" || $vocabulary_machine_name === "parent_gender" || $vocabulary_machine_name === "relationship_to_parent" || $vocabulary_machine_name === "chatbot_category") {
          /** @var \Drupal\taxonomy\TermInterface $term_obj */
          $term_data[] = [
            'id' => (int) $tid,
            'name' => $tax_result[$tax]->name,
            'unique_name' => $term_obj ? $term_obj->get('field_unique_name')->value : '',
          ];
        }
        else {
          $term_data[] = [
            'id' => (int) $tid,
            'name' => $tax_result[$tax]->name,
          ];
        }
      }
      return $term_data;
    }
  }

  /**
   * Convert embedded image URLs to WebP format and make them relative.
   */
  public function processEmbeddedImages($embedded_images) {
    $processed_images = [];
    foreach ($embedded_images as $image_url) {
      // Check if this is a direct file URL.
      if (preg_match('#/sites/default/files/(.+)#', $image_url, $matches)) {
        // Extract the file path and convert to proper URI format.
        $file_path = $matches[1];
        $processed_images[] = '/sites/default/files/' . $file_path;
      }
      else {
        $processed_images[] = $image_url;
      }
    }
    return $processed_images;

  }

  /**
   * Removes items by key value from data array.
   *
   * This method filters data based on taxonomy term IDs.
   * Uses helper service for cached pregnancy term ID lookup
   * to avoid database queries on every call.
   *
   * @param string $request_uri
   *   The current request URI.
   * @param array $data
   *   The data array to filter.
   * @param string $key
   *   The key to check in the data.
   * @param int $tid
   *   The taxonomy term ID to remove.
   *
   * @return array
   *   The filtered data array.
   */
  public function removeItemsByKeyValue($request_uri, $data, $key, $tid) {
    // Handle taxonomies API.
    if (strpos($request_uri, "/api/taxonomies") !== FALSE) {
      if (isset($data[$key]) && is_array($data[$key])) {
        foreach ($data[$key] as $itemKey => $item) {
          if (isset($item['id']) && $item['id'] == $tid) {
            unset($data[$key][$itemKey]);
          }
        }
        // Reindex array keys to be consecutive integers.
        $data[$key] = array_values($data[$key]);
      }
    }

    // Handle articles API.
    if (strpos($request_uri, "/api/articles") !== FALSE) {
      // Use helper service for cached pregnancy term ID lookup.
      // This avoids a database query on every call.
      $pregnancy_tid = $this->helper->getPregnancyTermId();

      foreach ($data as $k => $val) {
        // Check if key exists and contains the tid.
        if (!isset($val[$key]) || !is_array($val[$key])) {
          continue;
        }

        if (in_array($tid, $val[$key])) {
          // Ignore removal if tid is Pregnancy term.
          if ($tid == $pregnancy_tid) {
            continue;
          }
          // Find the key of the value to remove.
          $keyToRemove = array_search($tid, $val[$key]);

          // If the value exists, remove it.
          if ($keyToRemove !== FALSE) {
            unset($data[$k][$key][$keyToRemove]);
            // Reindex array keys to be consecutive integers.
            $data[$k][$key] = array_values($data[$k][$key]);
          }
        }
      }
    }
    return $data;
  }

}
