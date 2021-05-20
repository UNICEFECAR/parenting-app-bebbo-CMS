<?php
namespace Drupal\custom_serialization\Plugin\views\style;

use Drupal\rest\Plugin\views\style\Serializer;

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
    //print_r($current_path);
    //die();
    $request = explode('/', $request_uri);
    //  print_r($request);
    //  echo $request[4];
    // die();
    $rows = [];
    $data = array();
    foreach ($this->view->result as $row_index => $row) {
      $this->view->row_index = $row_index;
      $data[] = $this->view->rowPlugin->render($row);
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
