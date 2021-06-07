<?php
namespace Drupal\custom_serialization\Plugin\views\style;

use Drupal\rest\Plugin\views\style\Serializer;
use Drupal\media\Entity\Media;
use Drupal\file\Entity\File;
use Drupal\taxonomy\Entity\Term;

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

    $array_of_multiple_values = ["child_age","keywords","related_articles","related_video_articles","related_activities"];
    $media_fields = ["cover_image", "country_flag", "country_sponsor_logo", "country_national_partner", "cover_video"];    

    $rows = array();
    $data = array();
    $url = '';
    $mname = '';
    $malt = '';
    $site = '';
    $thumbnail_url = '';    
    
    $vocabulary_name = '';
    if(isset($this->view->result) && !empty($this->view->result))
    {    
      foreach ($this->view->result as $row_index => $row) {
        $this->view->row_index = $row_index;
  
        $view_render = $this->view->rowPlugin->render($row);
        $view_render = json_encode($view_render);
        $rendered_data = json_decode($view_render, true);   
        $field_formatter = array();
        foreach($rendered_data as $key => $values)
        {          
          //Custom image & video formattter
          if (in_array($key,$media_fields)) //To check media image field exist
          { 
            if(!empty($values))
            { 
              $media_formatted_data = $this->custom_media_formatter($key, $values);   
              $rendered_data[$key] = $media_formatted_data;
            }
          }
          
          //Custom array formatter
          if (in_array($key,$array_of_multiple_values)) //To check mulitple field 
          {          
            $array_formatted_data = $this->custom_array_formatter($values);   
            $rendered_data[$key] = $array_formatted_data;
          }   
          
          //Vocabularies Field formatter
          if(strpos($request_uri, "vocabularies") !== false){            
            if(!empty($values) && strpos($values, ',') !== false) // if the field have comma
            {          
              $formatted_data = explode(',',$values);
              $field_formatter[$formatted_data[0]] = [
                $key => html_entity_decode($formatted_data[1])
              ];              
            }  
          }
          
          //Taxonomies Field formatter
          if(strpos($request_uri, "taxonomies") !== false){                      
            if(!empty($values) && strpos($values, ',') !== false) // if the field have comma
            {          
              $term_data = [];
              $formatted_data = explode(',',$values);
              $vid = $formatted_data[0]; //vocabulary name
              $terms =\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vid);
              foreach ($terms as $term) {
                $term_data[] = array(
                  'id' => $term->tid,
                  'name' => $term->name
                );
              }    
              $field_formatter['vocabulary_name'] = html_entity_decode($formatted_data[1]);
              $field_formatter[$formatted_data[0]] = $term_data;
            }  
          }
        }                
        
        if(strpos($request_uri, "vocabularies") !== false || strpos($request_uri, "taxonomies") !== false){
          $data[] = $field_formatter;
        }        
        else
        {
          $data[] = $rendered_data;
          // To get total no of records
          $rows['total'] = count($data);
        }
      }    
  
      // To validate request params
      if(isset($request[3]) && !empty($request[3]))
      {
        $rows['langcode'] = $request[3];
      }
      if(isset($request[4]) && !empty($request[4]))
      {
        $rows['country'] = $request[4];
      }

      $rows['data'] = $data;
      unset($this->view->row_index);
      if(strpos($request_uri, "taxonomies") !== false){
       unset($rows['country']);
      }
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
      $rows['message'] = "No Records Found";

      return $this->serializer->serialize($rows, 'json', ['views_style_plugin' => $this]);
    }
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
  public function custom_media_formatter($key, $values) {    
    $media_entity = Media::load($values);
    // get entity type and proceed accordingly
    $media_type = $media_entity->bundle();            
    if ($media_type === 'image') {                
      $mid = $media_entity->get('field_media_image')->target_id;                
      if (!empty($mid)) {
        $mname = $media_entity->get('name')->value;                      
        $malt = $media_entity->get('field_media_image')->alt;          
  
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
}
