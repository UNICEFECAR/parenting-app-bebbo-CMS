<?php
namespace Drupal\custom_serialization\Plugin\views\style;

ini_set('serialize_precision',6);

use Drupal\rest\Plugin\views\style\Serializer;
use Drupal\media\Entity\Media;
use Drupal\file\Entity\File;
use Drupal\taxonomy\Entity\Term;
use Drupal\group\Entity\Group;

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
   * {@inheritdoc}
   */
  public function render() {

    $request_uri = \Drupal::service('path.current')->getPath(); //gives request path e.x (api/articles/en/1)
    $request = explode('/', $request_uri);
    $request_path = \Drupal::request()->getSchemeAndHttpHost();

    //Validating request params to response error code
    $validate_params_res = $this->check_request_params($request_uri);
    if(empty($validate_params_res))
    {
      $array_of_multiple_values = ["child_age","keywords","related_articles","related_video_articles","related_activities","language","related_milestone"];
      $media_fields = ["cover_image", "country_flag", "country_sponsor_logo", "country_national_partner", "cover_video"];    
      $pinned_content = ["vaccinations", "child_growth", "health_check_ups", "child_development"];
      $string_to_int = ["id", "category", "child_gender", "parent_gender", "licensed", "premature", "mandatory", "growth_type", "standard_deviation", "boy_video_article", "girl_video_article", "growth_period", "activity_category", "equipment", "type_of_support", "make_available_for_mobile", "pinned_article", "pinned_video_article"];
      $string_to_array_of_int = ["related_articles", "keywords", "child_age", "related_activities", "related_video_articles", "related_milestone"];
      
      $rows = array();
      $data = array();
      $url = '';
      $mname = '';
      $malt = '';
      $site = '';
      $thumbnail_url = '';    
      $field_formatter = array();    
      $uniques = [];
      if(isset($this->view->result) && !empty($this->view->result))
      {    
        $language_code = $request[3];
        foreach ($this->view->result as $row_index => $row) {
          $this->view->row_index = $row_index;
    
          $view_render = $this->view->rowPlugin->render($row);
          $view_render = json_encode($view_render);
          $rendered_data = json_decode($view_render, true);   
          // error_log("type =>".$rendered_data['type']);
          //Custom pinned api formatter
          if(strpos($request_uri, "pinned-contents") !== false && isset($request[4]) && in_array($request[4], $pinned_content))
          {
            if($rendered_data['type'] === "Article")
            {
              unset($rendered_data['cover_video']);
              unset($rendered_data['cover_video_image']);
            }
            else if($rendered_data['type'] === "Video Article")
            {
              unset($rendered_data['cover_image']);
              $rendered_data['cover_image'] = $rendered_data['cover_video_image'];
              unset($rendered_data['cover_video_image']);
            }
          }
          //Add unique field to Basic page API
          if(strpos($request_uri, "basic-pages") !== false && $rendered_data['type'] === "Basic page")
          {
            $query = \Drupal::database()->select('node_field_data');
            $query->condition('nid', $rendered_data['id']);
            $query->fields('node_field_data');
            $result = $query->execute()->fetchAll();
            for($i = 0; $i < count($result); $i++)
            {            
              $language = $result[$i]->langcode;
              if($language == "en")
              {
                $basic_title = $result[$i]->title;
                $basic_page = strtolower($basic_title);
                $basic_page = str_replace(' ', '_', $basic_page);
                $rendered_data['unique_name'] = $basic_page;
              }
            }                 
          }

          foreach($rendered_data as $key => $values)
          {                   
            
            //replace special charater into normal
            if($key === "title")
            {
              $title = str_replace("&#039;","'", $values);
              $title = str_replace("&quot;",'"', $title);

              $rendered_data[$key] = $title;
            }

            //change video or image actual path to absolute path
            if($key === "body" || $key === "summary")
            {
              $rendered_data[$key] = str_replace('src="/sites/default/files/', 'src="'.$request_path.'/sites/default/files/', $values);
            }
            //Custom image & video formattter
            if (in_array($key,$media_fields)) //To check media image field exist
            {                
              $media_formatted_data = $this->custom_media_formatter($key, $values,$language_code);   
              $rendered_data[$key] = $media_formatted_data;                                    
            }
            
            //Custom array formatter
            if(in_array($key,$array_of_multiple_values)) //To check mulitple field 
            {         
              $array_formatted_data = $this->custom_array_formatter($values); 
              //Convert array to array of int
              if(in_array($key,$string_to_array_of_int))
              {
                $rendered_data[$key] = array_map(function($elem) { return intval($elem); }, $array_formatted_data);
              }  
              else
              {
                $rendered_data[$key] = $array_formatted_data;
              }
            }   
            
            //Convert string to int
            if(in_array($key,$string_to_int))
            {
              if(!empty($values))
              {
                $rendered_data[$key] = (int)$values;
              }
              else
              {
                $rendered_data[$key] = 0;
              }
            }            
           
            //Custom Taxonomy Field Formatter
            if(strpos($request_uri, "vocabularies") !== false || strpos($request_uri, "taxonomies") !== false){   
              if(!empty($values) && strpos($values, ',') !== false) // if the field have comma
              { 
                $formatted_data = explode(',',$values);
                $vocabulary_name = $formatted_data[1];
                $vocabulary_machine_name = $formatted_data[0];
                $taxonomy_data = $this->custom_taxonomy_field_formatter($request_uri, $key, $vocabulary_name, $vocabulary_machine_name, $language_code);                
                //\Drupal::logger('custom_serialization')->notice('<pre><code>' . print_r($taxonomy_data, TRUE) . '</code></pre>');
                $field_formatter[$formatted_data[0]] = $taxonomy_data;
              }
            }         
          }                
          
          if(strpos($request_uri, "vocabularies") !== false || strpos($request_uri, "taxonomies") !== false){
            $data = $field_formatter;
            $rows['status'] = 200;
          }        
          else
          {       
            //error_log("data =>".print_r($rendered_data, true));
            $rows['status'] = 200;     
            if(strpos($request_uri, "pinned-contents") !== false || strpos($request_uri, "related-article-contents") !== false){          
              if(!in_array($rendered_data['id'], $uniques)){
                $uniques[] = $rendered_data['id'];
                $data[] = $rendered_data; 
              }              
              // To get total no of records
              $rows['total'] = count($data);          
            }
            else
            {
              $data[] = $rendered_data;  
              // To get total no of records
              $rows['total'] = count($data);          
            }                                    
          }
        }    
    
        // To validate request params
        if(isset($request[3]) && !empty($request[3]))
        {
          $rows['langcode'] = $request[3];
        }        

        if(strpos($request_uri, "sponsors") !== false){
          unset($rows['langcode']);   
        }

        $rows['data'] = $data;              
        unset($this->view->row_index);        
        // json output
        if ((empty($this->view->live_preview))) {
          $content_type = $this->displayHandler->getContentType();
        }
        else {
          $content_type = !empty($this->options['formats']) ? reset($this->options['formats']) : 'json';
        }
        return $this->serializer->serialize($rows, $content_type, ['views_style_plugin' => $this]);
      }    
      else
      {   
        $rows = [];
        $rows['status'] = 204;
        $rows['message'] = "No Records Found";

        return $this->serializer->serialize($rows, 'json', ['views_style_plugin' => $this]);
      }
    }
    else
    {   
      return $this->serializer->serialize($validate_params_res, 'json', ['views_style_plugin' => $this]);
    }    
  }

  /**
   * {@inheritdoc}
   */
  /*
    To check request params is correct
  */
  public function check_request_params($request_uri)
  {   
    $request = explode('/', $request_uri);
    
    if(isset($request[3]) && !empty($request[3]))
    {
      if(strpos($request_uri, "sponsors") !== false){
        if($request[3] == "all")
        {
          return "";
        }
        else
        {
          $groups = Group::loadMultiple(); 	
          foreach($groups as $gid => $group) {
            $id = $group->get('id')->getString();    
            $gids[] = $id;      
          } 
          if(!in_array($request[3],$gids))
          {
            $respons_arr['status'] = 400;
            $respons_arr['message'] = "Request country code is wrong";

            return $respons_arr;
          } 
        }        
      }
      else
      {
        $languages = \Drupal::languageManager()->getLanguages(); // get all enabled languages
        $languages = json_encode($languages);
        $languages = json_decode($languages, true);   
        $languages_arr = array(); 
        foreach($languages as $lang_code => $lang_name)
        {
          $languages_arr[] = $lang_code;
        }
        if(isset($languages_arr) && !empty($languages_arr))
        {
          if(!in_array($request[3],$languages_arr))
          {
            $respons_arr['status'] = 400;
            $respons_arr['message'] = "Request language is wrong";

            return $respons_arr;
          }      
        }
      }      
    }
    return "";  
  }

  /**
   * {@inheritdoc}
   */
  /*
    To convert comma seperated string into array
  */
  public function custom_array_formatter($values) {
    if(!empty($values) && strpos($values, ',') !== false) // if the field have comma
    {          
      $formatted_data = explode(',',$values);
    }   
    elseif(!empty($values))
    {
      $formatted_data = [$values];
    }   
    else
    {            
      $formatted_data = []; 
    } 
    
    return $formatted_data;
  }


  /**
   * {@inheritdoc}
   */
  /*
    To get media files details from db
  */
  public function custom_media_formatter($key, $values, $language_code) {    
    
    if(!empty($values))
    {
      $media_entity = Media::load($values);         
      $media_type = $media_entity->bundle();            
      if ($media_type === 'image') {                
        $mid = $media_entity->get('field_media_image')->target_id;                
        if (!empty($mid)) {
          $mname = $media_entity->get('name')->value; 
          $query = \Drupal::database()->select('media__field_media_image');
          $query->condition('entity_id', $values);
          $query->condition('langcode', $language_code);
          $query->fields('media__field_media_image');
          $result = $query->execute()->fetchAll();      
          if(!empty($result))
          {
            $malt = $result[0]->field_media_image_alt;
          }                 
          else
          {
            $malt = $media_entity->get('field_media_image')->alt;
          }
          /** @var File $image */
          $file = File::load($mid);    
          $url = $file->url();                  
        }
        $media_data = [
          'url'  => $url,
          'name' => $mname,
          'alt'  => $malt,
        ];
      }
      elseif($media_type === "remote_video")
      {
        $url = $media_entity->get('field_media_oembed_video')->value;
        $mname = $media_entity->get('name')->value;
        $site = (stripos($media_entity->get('field_media_oembed_video')->value, 'vimeo') !== FALSE) ? 'vimeo' : 'youtube';
        $media_data = [
          'url'  => $url,
          'name' => $mname,
          'site'  => $site,
        ];

        if($key == "cover_image")
        {
          $tid = $media_entity->get('thumbnail')->target_id;
          if (!empty($tid)) {
            $thumbnail = File::load($tid);
            $thumbnail_url = $thumbnail->url();  
          }              
          $media_data = [
            'url'  => $thumbnail_url,
            'name' => $mname,
            'alt'  => '',
          ];
        }              
      }                        
      elseif($media_type === "video")
      {
        //$url = $media_entity->get('field_media_video_file')->value;
        $mname = $media_entity->get('name')->value;
        $site = (stripos($media_entity->get('field_media_video_file')->value, 'vimeo') !== FALSE) ? 'vimeo' : 'youtube';
        $mid = $media_entity->get('field_media_video_file')->target_id;                     
        if (!empty($mid)) {                
          /** @var File $image */
          $file = File::load($mid);    
          $url = $file->url();                  
        }              

        $media_data = [
          'url'  => $url,
          'name' => $mname,
          'site'  => $site,
        ];                
        
        if($key == "cover_image")
        {
          $tid = $media_entity->get('thumbnail')->target_id;
          if (!empty($tid)) {
            $thumbnail = File::load($tid);
            $thumbnail_url = $thumbnail->url();  
          }              
          $media_data = [
            'url'  => $thumbnail_url,
            'name' => $mname,
            'alt'  => '',
          ];
        }    
      }            
      return $media_data;
    }  
    else
    {
      if($key == "cover_image" || $key == "country_flag" || $key == "country_sponsor_logo" || $key == "country_national_partner")
      {            
        $media_data = [
          'url'  => '',
          'name' => '',
          'alt'  => '',
        ];
      }  
      elseif($key == "cover_video")
      {
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
  /*
    To get taxonomy terms details from db
  */
  public function custom_taxonomy_field_formatter($request_uri, $key, $vocabulary_name, $vocabulary_machine_name, $language_code)
  {
    $taxonomy_vocabulary_machine_name = ["growth_period", "child_age", "growth_introductory", "standard_deviation"];
    //Vocabularies Field formatter
    if(strpos($request_uri, "vocabularies") !== false){    
      $termName = str_replace("&#039;","'", $vocabulary_name);                   
      $vocabulary_data = [    
        $key => $termName
      ];       
      return $vocabulary_data;
    }
    
    //Taxonomies Field formatter
    if(strpos($request_uri, "taxonomies") !== false){   
      $term_data = [];
      $tax_query = \Drupal::database()->select('taxonomy_term_field_data');
      $tax_query->condition('vid', $vocabulary_machine_name);
      $tax_query->condition('langcode', $language_code);
      $tax_query->condition('status', 1);
      $tax_query->fields('taxonomy_term_field_data');
      $tax_result = $tax_query->execute()->fetchAll();
      for($tax = 0; $tax < count($tax_result); $tax++)
      {                            
        if($vocabulary_machine_name === "growth_period")
        {
          $term_obj = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tax_result[$tax]->tid);                                    
          $term_data[] = array(   
            'id' => (int)$tax_result[$tax]->tid,
            'name' => $tax_result[$tax]->name,       
            'vaccination_opens' => (int)$term_obj->get('field_vaccination_opens')->value
          );
        }  
        else if($vocabulary_machine_name === "child_age")
        {
          $term_obj = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tax_result[$tax]->tid);                                              
          $age_bracket = $term_obj->get('field_age_bracket')->getValue();
          $ageBracket = [];
          foreach($age_bracket as $agekey => $agevalue)
          {           
            $ageBracket[] = $agevalue['target_id'];
          }
          if(!empty($ageBracket))
          {
            $age_bracket_arr = array_map(function($elem) { return intval($elem); }, $ageBracket);
          }
          else
          {
            $age_bracket_arr = [];
          }
          $term_data[] = array(   
            'id' => (int)$tax_result[$tax]->tid,
            'name' => $tax_result[$tax]->name,      
            'days_from' => (int)$term_obj->get('field_days_from')->value,
            'days_to' => (int)$term_obj->get('field_days_to')->value,
            'buffers_days' => (int)$term_obj->get('field_buffers_days')->value,
            'age_bracket' => $age_bracket_arr        
          );
        }   
        else if($vocabulary_machine_name === "growth_introductory")
        {
          $term_obj = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tax_result[$tax]->tid);                                    
          $term_data[] = array(   
            'id' => (int)$tax_result[$tax]->tid,
            'name' => $tax_result[$tax]->name,
            'body' => $tax_result[$tax]->description__value,        
            'days_from' => (int)$term_obj->get('field_days_from')->value,
            'days_to' => (int)$term_obj->get('field_days_to')->value             
          );
        } 
        else if($vocabulary_machine_name === "standard_deviation")
        {
          $term_obj = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tax_result[$tax]->tid);                                    
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

          $term_data[] = array(     
            'id' => (int)$tax_result[$tax]->tid,
            'name' => round($term_name, 3),      
            'child_gender' => (int)$term_obj->get('field_child_gender')->target_id,
            'growth_type' => (int)$term_obj->get('field_growth_type')->target_id,
            'sd0' =>  round($sd0, 3),
            'sd1' => round($sd1, 3),
            'sd2' => round($sd2, 3),
            'sd3' => round($sd3, 3),
            'sd4' => round($sd4, 3),
            'sd1neg' => round($sd1neg, 3),
            'sd2neg' => round($sd2neg, 3),
            'sd3neg' => round($sd3neg, 3),
            'sd4neg' => round($sd4neg, 3),
          );
        }
        else
        {
          $term_data[] = array(
            'id' => (int)$tax_result[$tax]->tid,
            'name' => $tax_result[$tax]->name
          ); 
        }        
      }   
      return $term_data;
    }    
  }  
}
