<?php

namespace Drupal\json_field\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Checks if JSON values are valid.
 */
class ValidJSONConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The serializer which serializes the views result.
   *
   * @var \Symfony\Component\Serializer\Encoder\DecoderInterface
   */
  protected $serializer;

  /**
   * Constructs a ValidJSONConstraintValidator object.
   *
   * @param \Symfony\Component\Serializer\Encoder\DecoderInterface $serializer
   */
  public function __construct(DecoderInterface $serializer) {
    $this->serializer = $serializer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('serializer'));
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    // Empty should be handled by the field settings.
    if (empty($value->value)) {
      return;
    }
    try {
      $this->serializer->decode($value->value, 'json');
    }
    catch (\Exception $e) {
      // Add a constraint violation with the `json_decode` message on failure.
      $this->context->addViolation($constraint->message, ['@error' => $e->getMessage()]);
    }
  }

}
