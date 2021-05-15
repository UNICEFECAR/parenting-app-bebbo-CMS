<?php

namespace Drupal\json_field\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * JSON Field valid JSON constraint.
 *
 * Verifies that input values are valid JSON.
 *
 * @Constraint(
 *   id = "valid_json",
 *   label = @Translation("Valid deserializable JSON text", context = "Validation")
 * )
 */
class ValidJSONConstraint extends Constraint {

  /**
   * The default violation message.
   *
   * @var string
   */
  public $message = 'The supplied value is not valid JSON data (@error).';

}
