<?php

namespace Drupal\custom_serialization\Plugin\views\style;

ini_set('serialize_precision', 6);

use Drupal\file\Entity\File;
use Drupal\group\Entity\Group;
use Drupal\media\Entity\Media;
use Drupal\taxonomy\TermInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\Core\Database\Connection;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\rest\Plugin\views\style\Serializer;
use Symfony\Component\HttpFoundation\Response;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\language_visibility_control\LanguageVisibilityService;

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
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The group membership loader.
   *
   * @var \Drupal\group\GroupMembershipLoaderInterface
   */
  protected $groupMembershipLoader;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

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
      $container->get('language_visibility_control.service')
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

    /* Validating request params to response error code. */
    if (strpos($request_uri, "api/country-groups") !== FALSE) {
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
      date_default_timezone_set('Asia/Kolkata');
      $timestamp = date("Y-m-d H:i");
      if (isset($this->view->result) && !empty($this->view->result)) {
        if (isset($request[3])) {
          $language_code = $request[3];
        }
        else {
          $language_code = '';
        }
        foreach ($this->view->result as $row_index => $row) {
          $this->view->row_index = $row_index;

          $view_render = $this->view->rowPlugin->render($row);
          $view_render = json_encode($view_render);
          $rendered_data = json_decode($view_render, TRUE);
          // Custom country listing.
          if (strpos($request_uri, "api/country-groups") !== FALSE && isset($rendered_data['CountryID']) && $rendered_data['CountryID'] == 131) {
            continue;
          }
          /* error_log("type =>".$rendered_data['type']); */
          /* Custom pinned api formatter. */
          if (strpos($request_uri, "pinned-contents") !== FALSE && isset($request[4]) && in_array($request[4], $pinned_content)) {
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
          /* Add unique field to Basic page API. */
          if (strpos($request_uri, "basic-pages") !== FALSE && $rendered_data['type'] === "Basic page") {
            $query = $this->database->select('node_field_data');
            $query->condition('nid', $rendered_data['id']);
            $query->condition('langcode', "en");
            $query->fields('node_field_data');
            $result = $query->execute()->fetchAll();
            if (!empty($result)) {
              $basic_title = $result[0]->title;
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
              /* Remove empty <p> </p> tag */
              $body_summary = str_replace("<p> </p>", '', $body_summary);
              /* Remove strong <strong> </strong> tag */
              $body_summary = str_replace("<strong> </strong>", '', $body_summary);
              /* remove inline style attribute */
              $body_summary = preg_replace('/(<[^>]*) style=("[^"]+"|\'[^\']+\')([^>]*>)/i', '$1$3', $body_summary);
              /* Remove empty <p> </p> tag */
              $body_summary = str_replace("<p> </p>", '', $body_summary);
              /* Remove empty <strong> </strong> tag */
              $body_summary = str_replace("<strong> </strong>", '', $body_summary);
              /* Remove width and height of remote video */
              $body_summary = str_replace('width="640"', '', $body_summary);
              $body_summary = str_replace('height="480"', '', $body_summary);

              /* Remove div Image label tag */
              $body_summary = str_replace("<div class=\"field__label visually-hidden\">Image</div>", '', $body_summary);

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
              $media_formatted_data = $this->customMediaFormatter($key, $values, $language_code);
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
                $rendered_data[$key] = (int) $values;
              }
              else {
                $rendered_data[$key] = 0;
              }
            }

            /* Custom Taxonomy Field Formatter. */
            if (strpos($request_uri, "vocabularies") !== FALSE || strpos($request_uri, "taxonomies") !== FALSE) {
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

            if (strpos($request_uri, "api/country-groups") !== FALSE && isset($rendered_data['CountryID']) && $rendered_data['CountryID'] == 126) {
              $display_ru = $display_en = $custom_locale_en = $custom_luxon_en = $custom_plural_en = $custom_locale_ru = $custom_luxon_ru = $custom_plural_ru = '';
              // $Countryname = $rendered_data['Countryname'] ?? 'Unknown';
              // Langcode en.
              $existing_data_en = $this->database->select('custom_language_data', 'cld')
                ->fields('cld', ['custom_locale', 'custom_luxon', 'custom_plural', 'custom_language_name_local'])
                ->condition('langcode', 'en')
                ->execute()
                ->fetchAssoc();

              $langcode_en = 'en';
              $language_en = $this->languageManager->getLanguage($langcode_en);
              $this->languageManager->setConfigOverrideLanguage($language_en);
              $languages_en = ConfigurableLanguage::load($langcode_en);

              if ($languages_en) {
                // Retrieve the display name (label) of the language.
                if ($languages_en->label()) {
                  $display_en = $languages_en->label();
                }
              }

              if (!empty($existing_data_en)) {
                $custom_locale_en = $existing_data_en['custom_locale'];
                $custom_luxon_en = $existing_data_en['custom_luxon'];
                $custom_plural_en = $existing_data_en['custom_plural'];
              }

              // Langcode ru.
              $existing_data_ru = $this->database->select('custom_language_data', 'cld')
                ->fields('cld', ['custom_locale', 'custom_luxon', 'custom_plural', 'custom_language_name_local'])
                ->condition('langcode', 'ru')
                ->execute()
                ->fetchAssoc();

              $langcode_ru = 'ru';
              $language_ru = $this->languageManager->getLanguage($langcode_ru);
              $this->languageManager->setConfigOverrideLanguage($language_ru);
              $language_ru = ConfigurableLanguage::load($langcode_ru);

              if ($language_ru) {
                // Retrieve the display name (label) of the language.
                if ($language_ru->label()) {
                  $display_ru = $language_ru->label();
                }
              }

              if (!empty($existing_data_ru)) {
                $custom_locale_ru = $existing_data_ru['custom_locale'];
                $custom_luxon_ru = $existing_data_ru['custom_luxon'];
                $custom_plural_ru = $existing_data_ru['custom_plural'];
              }
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
            if (strpos($request_uri, "api/country-groups") !== FALSE && isset($rendered_data['CountryID']) && $rendered_data['CountryID'] != 126) {
              $groups = Group::load($rendered_data['CountryID']);
              $country_languages = $groups->get('field_language')->getValue();
              $rendered_data['languages'] = [];

              foreach ($country_languages as $val) {
                $langcode = $val['value'];

                if ($langcode) {
                  // Check if the language still exists (not disabled)
                  try {
                    $language = $this->languageManager->getLanguage($langcode);
                  }
                  catch (\Exception $e) {
                    // Language doesn't exist, skip it.
                    continue;
                  }

                  $this->languageManager->setConfigOverrideLanguage($language);
                  $languages = ConfigurableLanguage::load($langcode);

                  // Skip if ConfigurableLanguage entity doesn't exist.
                  if (!$languages) {
                    continue;
                  }

                  $view_weight = $languages->get('weight') ?? 0;

                  // Fetch the existing data from the database.
                  $existing_data_all = $this->database->select('custom_language_data', 'cld')
                    ->fields('cld', ['custom_locale', 'custom_luxon', 'custom_plural', 'custom_language_name_local'])
                    ->condition('langcode', $langcode)
                    ->execute()
                    ->fetchAssoc();

                  // Initialize variables.
                  $custom_locale_all = $custom_luxon_all = $custom_plural_all = '';
                  $custom_language_name_local = '';

                  if (!empty($existing_data_all)) {
                    $custom_locale_all = $existing_data_all['custom_locale'];
                    $custom_luxon_all = $existing_data_all['custom_luxon'];
                    $custom_plural_all = $existing_data_all['custom_plural'];
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

          if (strpos($request_uri, "vocabularies") !== FALSE || strpos($request_uri, "taxonomies") !== FALSE) {
            $data = $field_formatter;
            $rows['status'] = 200;
          }
          else {
            /* E error_log("data =>".print_r($rendered_data, true)); */
            $rows['status'] = 200;
            if (strpos($request_uri, "pinned-contents") !== FALSE || strpos($request_uri, "related-article-contents") !== FALSE) {
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
          if (strpos($request_uri, "archive") !== FALSE) {
            $type = $rendered_data['type'];
            $total_ids[] = $rendered_data['id'];
            $types[$type][] = +$rendered_data['id'];
            $data = $types;
            $rows['total'] = count($total_ids);

          }
        }

        if (strpos($request_uri, "/api/taxonomies") !== FALSE || strpos($request_uri, "/api/articles") !== FALSE) {
          if (strpos($request_uri, "/api/articles") !== FALSE) {
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
          foreach ($term_name_arr as $val) {
            $term_values = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(['name' => $val]);
            foreach ($term_values as $term_value) {
              if ($term_value) {
                // Use ->id() to get the term ID.
                $tid = $term_value->id();
                // Get the vocabulary ID.
                $vid = $term_value->bundle();
                // Adjust 'taxonomy_terms' to your actual key.
                $data = $this->removeItemsByKeyValue($request_uri, $data, $vid, $tid);
              }
            }
          }
        }

        if (strpos($request_uri, "api/country-groups") !== FALSE) {
          $index = array_search('126', array_column($data, 'CountryID'));

          // Check if the entry exists.
          if ($index !== FALSE) {
            // Remove the entry from the array.
            $entry = array_splice($data, $index, 1);

            // Append the entry to the end of the array.
            $data[] = $entry[0];
          }
        }

        /* To validate request params. */
        if (isset($request[3]) && !empty($request[3])) {
          $rows['langcode'] = $request[3];
        }

        if (strpos($request_uri, "api/country-groups") !== FALSE) {
          $rows['langcode'] = 'en';
        }

        if (strpos($request_uri, "sponsors") !== FALSE) {
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

        if (strpos($request_uri, "api/country-groups") !== FALSE) {
          $serialized_data = $this->serializer->serialize($rows, $content_type, ['views_style_plugin' => $this]);

          // Create a response object to set headers.
          $response = new Response($serialized_data);
          $response->headers->set('Content-Type', $content_type);
          $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
          $response->headers->set('Pragma', 'no-cache');
          $response->headers->set('Expires', '0');

          // Send headers and return serialized data.
          $response->sendHeaders();

          return $serialized_data;
        }
        else {
          return $this->serializer->serialize($rows, $content_type, ['views_style_plugin' => $this]);
        }

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
   * To check request params is correct.
   */
  public function checkRequestParams($request_uri) {
    $request = explode('/', $request_uri);
    if (isset($request[3]) && !empty($request[3])) {
      if (strpos($request_uri, "sponsors") !== FALSE) {
        if ($request[3] == "all") {
          return "";
        }
        else {
          $groups = Group::loadMultiple();
          $gids = [];
          foreach ($groups as $group) {
            $id = $group->get('id')->getString();
            $gids[] = $id;
          }
          if (!in_array($request[3], $gids)) {
            $respons_arr['status'] = 400;
            $respons_arr['message'] = "Request country code is wrong";

            return $respons_arr;
          }
        }
      }
      else {
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

        // Check if language is visible in mobile app for any country group.
        if ($this->languageVisibilityService) {
          $requested_language = $request[3];
          $is_language_visible = FALSE;

          // Check all country groups to see if this language is visible in any.
          $language_visibility_service = $this->languageVisibilityService;
          $groups = Group::loadMultiple();
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
   * To get media files details from db.
   */
  public function customMediaFormatter($key, $values, $language_code) {

    if (!empty($values)) {
      $url = $mname = $malt = '';
      $media_data = [];
      $media_entity = Media::load($values);
      $media_type = $media_entity->bundle();
      $base_url = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost();
      if ($media_type === 'image') {
        $mid = $media_entity->get('field_media_image')->target_id;
        if (!empty($mid)) {
          $mname = $media_entity->get('name')->value;
          $query = $this->database->select('media__field_media_image');
          $query->condition('entity_id', $values);
          $query->condition('langcode', $language_code);
          $query->fields('media__field_media_image');
          $result = $query->execute()->fetchAll();
          if (!empty($result)) {
            $malt = $result[0]->field_media_image_alt;
          }
          else {
            $malt_field = $media_entity->get('field_media_image')->getValue();
            $malt = $malt_field[0]['alt'] ?? '';
          }
          /**
           * Get the File Details.
           *
           * @var object
           */
          $query = $this->database->select('file_managed');
          $query->condition('fid', $mid);
          $query->fields('file_managed');
          $result22 = $query->execute()->fetchAll();
          $uri = '';
          if (!empty($result22)) {
            $uri = $result22[0]->uri;
          }
          $url = ImageStyle::load('content_1200xh_')->buildUrl($uri);
          $url = $this->getWebpUrl($url);

        }
        $media_data = [
          'url'  => $url,
          'name' => $mname,
          'alt'  => $malt,
        ];
      }
      elseif ($media_type === "remote_video") {
        $url = $media_entity->get('field_media_oembed_video')->value;
        $mname = $media_entity->get('name')->value;
        $site = (stripos($media_entity->get('field_media_oembed_video')->value, 'vimeo') !== FALSE) ? 'vimeo' : 'youtube';
        $media_data = [
          'url'  => $url,
          'name' => $mname,
          'site'  => $site,
        ];

        if ($key == "cover_image") {
          $tid = $media_entity->get('thumbnail')->target_id;
          $urls = '';
          if (!empty($tid)) {
            if (strpos($media_entity->get('field_media_oembed_video')->value, 'vimeo') !== FALSE) {
              // Get the value of the oEmbed video field.
              $oembed_value = $media_entity->get('field_media_oembed_video')->value;

              // Parse the oEmbed URL to extract the Vimeo video ID.
              $parsed_url = parse_url($oembed_value);
              if (isset($parsed_url['path'])) {
                // Extract the Vimeo video ID from the path.
                $path_segments = explode('/', $parsed_url['path']);
                $vimeo_video_id = end($path_segments);
                $vimeo_api_url = "https://vimeo.com/api/oembed.json?url=https://vimeo.com/{$vimeo_video_id}";

                // Initialize cURL session.
                $ch = curl_init();

                // Set cURL options.
                curl_setopt($ch, CURLOPT_URL, $vimeo_api_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

                // Execute the cURL request.
                $response = curl_exec($ch);

                if ($response === FALSE) {
                  // cURL error occurred.
                  curl_error($ch);
                  // Handle the error, log it, etc.
                  $urls = 'cURL error';
                }
                else {
                  // Close cURL session.
                  curl_close($ch);

                  // Decode the JSON response into an associative array.
                  $data = json_decode($response, TRUE);

                  if ($data === NULL) {
                    // JSON decoding error occurred.
                    json_last_error_msg();
                    // Handle the error, log it, etc.
                    $urls = 'Vimeo error';
                  }
                  else {
                    // Extract the thumbnail URL from the response data.
                    $urls = $data['thumbnail_url'] ?? NULL;
                  }
                }
              }
              else {
                // Vimeo video ID not found in the oEmbed URL
                // Handle the error, log it, etc.
                $urls = 'Vimeo ID not found';
              }

            }
            else {
              $thumbnail = File::load($tid);
              $thumbnail_url = $thumbnail->createFileUrl();
              if (strpos($thumbnail_url, $base_url) !== FALSE) {
                // Base URL is present in the thumbnail URL.
                $urls = $thumbnail_url;
              }
              else {
                // Base URL is Not present in the thumbnail URL.
                $urls = $base_url . $thumbnail_url;
              }
            }
          }
          $urls = $this->getWebpUrl($urls);
          $media_data = [
            'url'  => $urls,
            'name' => $mname,
            'alt'  => '',
          ];
        }
      }
      elseif ($media_type === "video") {
        $mname = $media_entity->get('name')->value;
        $site = (stripos($media_entity->get('field_media_video_file')->value, 'vimeo') !== FALSE) ? 'vimeo' : 'youtube';
        $mid = $media_entity->get('field_media_video_file')->target_id;
        if (!empty($mid)) {
          /**
           * Get the File Details.
           *
           * @var object
           */
          $file = File::load($mid);
          $url = $file->createFileUrl();
        }

        $media_data = [
          'url'  => $url,
          'name' => $mname,
          'site'  => $site,
        ];

        if ($key == "cover_image") {
          $tid = $media_entity->get('thumbnail')->target_id;
          $thumbnail_url = '';
          if (!empty($tid)) {
            $thumbnail = File::load($tid);
            $thumbnail_url = $thumbnail->createFileUrl();
          }
          $thumbnail_url = $this->getWebpUrl($thumbnail_url);
          $media_data = [
            'url'  => $thumbnail_url,
            'name' => $mname,
            'alt'  => '',
          ];
        }
      }
      return $media_data;
    }
    else {
      $media_data = [];
      if ($key == "cover_image" || $key == "country_flag" || $key == "country_sponsor_logo" || $key == "country_national_partner") {
        $media_data = [
          'url'  => '',
          'name' => '',
          'alt'  => '',
        ];
      }
      elseif ($key == "cover_video") {
        $media_data = [
          'url'  => '',
          'name' => '',
          'site'  => '',
        ];
      }
      return $media_data;
    }
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
      for ($tax = 0; $tax < count($tax_result); $tax++) {
        if ($vocabulary_machine_name === "growth_period") {
          $term_obj = $this->entityTypeManager->getStorage('taxonomy_term')->load($tax_result[$tax]->tid);
          /** @var \Drupal\taxonomy\TermInterface $term_obj */
          $term_data[] = [
            'id' => (int) $tax_result[$tax]->tid,
            'name' => $tax_result[$tax]->name,
            'vaccination_opens' => (int) $term_obj->get('field_vaccination_opens')->value,
          ];
        }
        elseif ($vocabulary_machine_name === "child_age") {
          $term_obj = $this->entityTypeManager->getStorage('taxonomy_term')->load($tax_result[$tax]->tid);
          /** @var \Drupal\taxonomy\TermInterface $term_obj */
          $age_bracket = $term_obj->get('field_age_bracket')->getValue();
          $ageBracket = [];
          foreach ($age_bracket as $agevalue) {
            $ageBracket[] = $agevalue['target_id'];
          }
          if (!empty($ageBracket)) {
            $age_bracket_arr = array_map(function ($elem) {
              return intval($elem);
            }, $ageBracket);
          }
          else {
            $age_bracket_arr = [];
          }
          $term_data[] = [
            'id' => (int) $tax_result[$tax]->tid,
            'name' => $tax_result[$tax]->name,
            'days_from' => (int) $term_obj->get('field_days_from')->value,
            'days_to' => (int) $term_obj->get('field_days_to')->value,
            'buffers_days' => (int) $term_obj->get('field_buffers_days')->value,
            'age_bracket' => $age_bracket_arr,
          ];
        }
        elseif ($vocabulary_machine_name === "growth_introductory") {
          $term_obj = $this->entityTypeManager->getStorage('taxonomy_term')->load($tax_result[$tax]->tid);
          /** @var \Drupal\taxonomy\TermInterface $term_obj */
          $term_data[] = [
            'id' => (int) $tax_result[$tax]->tid,
            'name' => $tax_result[$tax]->name,
            'body' => $tax_result[$tax]->description__value,
            'days_from' => (int) $term_obj->get('field_days_from')->value,
            'days_to' => (int) $term_obj->get('field_days_to')->value,
          ];
        }
        elseif ($vocabulary_machine_name === "standard_deviation") {
          $term_obj = $this->entityTypeManager->getStorage('taxonomy_term')->load($tax_result[$tax]->tid);
          /** @var \Drupal\taxonomy\TermInterface $term_obj */
          $sd0 = (float) $term_obj->get('field_sd0')->value;
          $sd1 = (float) $term_obj->get('field_sd1')->value;
          $sd2 = (float) $term_obj->get('field_sd2')->value;
          $sd3 = (float) $term_obj->get('field_sd3')->value;
          $sd4 = (float) $term_obj->get('field_sd4')->value;
          $sd1neg = (float) $term_obj->get('field_sd1neg')->value;
          $sd2neg = (float) $term_obj->get('field_sd2neg')->value;
          $sd3neg = (float) $term_obj->get('field_sd3neg')->value;
          $sd4neg = (float) $term_obj->get('field_sd4neg')->value;
          $term_name = (float) $tax_result[$tax]->name;

          $term_data[] = [
            'id' => (int) $tax_result[$tax]->tid,
            'name' => round($term_name, 3),
            'child_gender' => (int) $term_obj->get('field_child_gender')->target_id,
            'growth_type' => (int) $term_obj->get('field_growth_type')->target_id,
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
          $term_obj = $this->entityTypeManager->getStorage('taxonomy_term')->load($tax_result[$tax]->tid);
          /** @var \Drupal\taxonomy\TermInterface $term_obj */
          $term_data[] = [
            'id' => (int) $tax_result[$tax]->tid,
            'name' => $tax_result[$tax]->name,
            'parent_category_id' => (int) $term_obj->get('field_chatbot_category')->target_id,
            'unique_name' => $term_obj->get('field_unique_name')->value,
          ];
        }
        elseif ($vocabulary_machine_name === "category") {
          $term_obj = $this->entityTypeManager->getStorage('taxonomy_term')->load($tax_result[$tax]->tid);
          /** @var \Drupal\taxonomy\TermInterface $term_obj */
          $field_type_of_article_entity = $term_obj->get('field_type_of_article')->entity ?? NULL;
          $field_type_of_article = $field_type_of_article_entity instanceof TermInterface
          ? ($field_type_of_article_entity->get('name')->value ?? '')
          : '';
          $term_data[] = [
            'id' => (int) $tax_result[$tax]->tid,
            'name' => $tax_result[$tax]->name,
            'unique_name' => $term_obj->get('field_unique_name')->value,
            'field_type_of_article' => $field_type_of_article,
          ];
        }
        elseif ($vocabulary_machine_name === "growth_type" || $vocabulary_machine_name === "activity_category" || $vocabulary_machine_name === "child_gender" || $vocabulary_machine_name === "parent_gender" || $vocabulary_machine_name === "relationship_to_parent" || $vocabulary_machine_name === "chatbot_category") {
          $term_obj = $this->entityTypeManager->getStorage('taxonomy_term')->load($tax_result[$tax]->tid);
          /** @var \Drupal\taxonomy\TermInterface $term_obj */
          $term_data[] = [
            'id' => (int) $tax_result[$tax]->tid,
            'name' => $tax_result[$tax]->name,
            'unique_name' => $term_obj->get('field_unique_name')->value,
          ];
        }
        else {
          $term_data[] = [
            'id' => (int) $tax_result[$tax]->tid,
            'name' => $tax_result[$tax]->name,
          ];
        }
      }
      return $term_data;
    }
  }

  /**
   * Convert image URL to WebP format.
   */
  public function getWebpUrl($url) {
    if (empty($url)) {
      return $url;
    }

    $webp_url = preg_replace('/\.(jpg|jpeg|png)(\?.*)?$/i', '.webp$2', $url);
    return $webp_url;

  }

  /**
   * Convert embedded image URLs to WebP format and make them relative.
   */
  public function processEmbeddedImages($embedded_images) {
    $processed_images = [];
    $base_url = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost();

    foreach ($embedded_images as $image_url) {
      // Check if this is a direct file URL.
      if (preg_match('#/sites/default/files/(.+)#', $image_url, $matches)) {
        // Extract the file path and convert to proper URI format.
        $file_path = $matches[1];
        $uri = 'public://' . $file_path;

        // Generate the proper image style URL.
        $styled_url = ImageStyle::load('content_1200xh_')->buildUrl($uri);
        $webp_url = $this->getWebpUrl($styled_url);
      }
      else {
        $webp_url = $this->getWebpUrl($image_url);
      }

      if (strpos($webp_url, $base_url) === 0) {
        $webp_url = substr($webp_url, strlen($base_url));
      }
      $processed_images[] = $webp_url;
    }
    return $processed_images;

  }

  /**
   * Removes item.
   */
  public function removeItemsByKeyValue($request_uri, $data, $key, $tid) {

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

    if (strpos($request_uri, "/api/articles") !== FALSE) {
      $pregnancy_tid = $this->entityTypeManager->getStorage('taxonomy_term')
        ->getQuery()
        ->condition('vid', 'child_age')
        ->condition('name', 'pregnancy')
        ->accessCheck(FALSE)
        ->range(0, 1)
        ->execute();

      $pregnancy_tid = $pregnancy_tid ? reset($pregnancy_tid) : NULL;
      foreach ($data as $k => $val) {
        if (in_array($tid, $val[$key])) {
          // Ignore removal if tid is 166191 (Pregnancy).
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
