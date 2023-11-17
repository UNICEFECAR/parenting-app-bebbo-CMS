<?php

namespace Drupal\symfony_mailer\Processor;

/**
 * Defines the base class for custom EmailProcessorInterface implementations.
 *
 * This base class is for custom processors that are not plug-ins. Use
 * EmailProcessorBase for plug-ins.
 */
abstract class EmailProcessorCustomBase implements EmailProcessorInterface {

  use EmailProcessorTrait;

}
