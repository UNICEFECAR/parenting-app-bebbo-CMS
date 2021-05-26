<?php
namespace Drupal\custom_serialization\Plugin\views\style;

use Drupal\rest\Plugin\views\style\Serializer;
use Drupal\media\Entity\Media;
use Drupal\file\Entity\File;

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
    foreach ($this->view->result as $row_index => $row) {
      $this->view->row_index = $row_index;

      $view_render = $this->view->rowPlugin->render($row);
      $view_render = json_encode($view_render);
      $redered_data = json_decode($view_render, true);
    
      foreach($redered_data as $key => $values)
      {                              
        //Custom image & video formattter
        if (in_array($key,$media_fields)) //To check media image field exist
        { 
          if(!empty($values))
          {
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
              $redered_data[$key] = [
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
              
              $redered_data[$key] = [
                'url'  => $url,
                'name' => $mname,
                'site'  => $site,
              ];
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
              $redered_data[$key] = [
                'url'  => $url,
                'name' => $mname,
                'site'  => $site,
              ];
            }            
          }               
        }
        
        //Custom array formatter
        if (in_array($key,$array_of_multiple_values)) //To check mulitple field 
        {          
          if(!empty($values) && strpos($values, ',') !== false) // if the field have comma
          {          
            $multiple_values_field = explode(',',$values);
            $redered_data[$key] = $multiple_values_field;                          
          }   
          elseif(!empty($values))
          {
            $redered_data[$key] = [$values];
          }   
          else
          {            
            $redered_data[$key] = []; 
          }                             
        }        
      }      
      $data[] = $redered_data;
    }
    $rows['total'] = count($data);
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
    if ((empty($this->view->live_preview))) {
      $content_type = $this->displayHandler->getContentType();
    }
    else {
      $content_type = !empty($this->options['formats']) ? reset($this->options['formats']) : 'json';
    }
    return $this->serializer->serialize($rows, $content_type, ['views_style_plugin' => $this]);
  }
}
