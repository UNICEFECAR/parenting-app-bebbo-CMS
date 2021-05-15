<?php

namespace Drupal\json_field\Plugin\views\field;

use Drupal\rest\Plugin\views\display\RestExport;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Field handler to present json data to an entity "data" display.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("json_data")
 */
class JSONDataField extends FieldPluginBase {

  /**
   * The serializer which serializes the views result.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('serializer')
    );
  }

  /**
   * Constructs a Plugin object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, SerializerInterface $serializer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->serializer = $serializer;
  }

  /**
   * {@inheritdoc}
   */
  public function usesGroupBy() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function allowAdvancedRender() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $row) {
    $value = $this->getValue($row);

    $build = parent::render($row);

    // Check to make sure the current display handler is of DATA type.
    // @todo Allow other display handlers that support data as well.
    if ($this->view->display_handler instanceof RestExport) {
      return $this->serializer->decode($value, 'json');
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function advancedRender(ResultRow $values) {
    return $this->render($values);
  }

}
