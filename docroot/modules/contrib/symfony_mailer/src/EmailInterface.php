<?php

namespace Drupal\symfony_mailer;

use Drupal\Core\Entity\EntityInterface;

/**
 * Defines the interface for an Email.
 */
interface EmailInterface extends BaseEmailInterface {

  /**
   * The default weight for an email processor.
   */
  const DEFAULT_WEIGHT = 500;

  /**
   * Initialisation phase: set the processing that will occur.
   *
   * Set processors and parameters.
   */
  const PHASE_INIT = 0;

  /**
   * Build phase: construct the email.
   *
   * Must not trigger any rendering because cannot yet rely on the correct
   * language, theme, and account. For example, must not cast a translatable
   * string into a plain string, or replace tokens.
   *
   * @see \Drupal\symfony_mailer\Processor\EmailProcessorInterface::build()
   */
  const PHASE_BUILD = 1;

  /**
   * Pre-render phase: preparation for rendering.
   *
   * Not normally needed. Only if there is a rendering step that needs to be
   * done before the main rendering call.
   *
   * @see \Drupal\symfony_mailer\Processor\EmailProcessorInterface::preRender()
   */
  const PHASE_PRE_RENDER = 2;

  /**
   * Post-render phase: adjusting of rendered output.
   *
   * Act on the rendered HTML, or any header.
   *
   * @see \Drupal\symfony_mailer\Processor\EmailProcessorInterface::postRender()
   */
  const PHASE_POST_RENDER = 3;

  /**
   * Post-send phase: no further alterations allowed.
   *
   * @see \Drupal\symfony_mailer\Processor\EmailProcessorInterface::postSend()
   */
  const PHASE_POST_SEND = 4;

  /**
   * Names of the email phases.
   */
  const PHASE_NAMES = [
    self::PHASE_INIT => 'init',
    self::PHASE_BUILD => 'build',
    self::PHASE_PRE_RENDER => 'pre_render',
    self::PHASE_POST_RENDER => 'post_render',
    self::PHASE_POST_SEND => 'post_send',
  ];

  /**
   * Add an email processor.
   *
   * Valid: initialisation.
   *
   * @param callable $function
   *   The function to call.
   * @param int $phase
   *   (Optional) The phase to run in, one of the EmailInterface::PHASE_
   *   constants.
   * @param int $weight
   *   (Optional) The weight, lower values run earlier.
   * @param string $id
   *   (Optional) An ID that can be used to alter or debug.
   *
   * @return $this
   */
  public function addProcessor(callable $function, int $phase = self::PHASE_BUILD, int $weight = self::DEFAULT_WEIGHT, string $id = NULL);

  /**
   * Gets the langcode.
   *
   * The langcode is calculated automatically from the to address.
   *
   * Valid: after rendering.
   *
   * @return string
   *   Language code to use to compose the email.
   */
  public function getLangcode();

  /**
   * Sets parameters used for building the email.
   *
   * Valid: initialisation.
   *
   * @param array $params
   *   (optional) An array of keyed objects or configuration.
   *
   * @return $this
   */
  public function setParams(array $params = []);

  /**
   * Adds a parameter used for building the email.
   *
   * If the value is an entity, then the key should be the entity type ID.
   * Otherwise the value is typically a setting that alters the email build.
   *
   * Valid: initialisation.
   *
   * @param string $key
   *   Parameter key to set.
   * @param mixed $value
   *   Parameter value to set.
   *
   * @return $this
   */
  public function setParam(string $key, $value);

  /**
   * Gets parameters used for building the email.
   *
   * @return array
   *   An array of keyed objects.
   */
  public function getParams();

  /**
   * Gets a parameter used for building the email.
   *
   * @param string $key
   *   Parameter key to get.
   *
   * @return mixed
   *   Parameter value, or NULL if the parameter is not set.
   */
  public function getParam(string $key);

  /**
   * Sends the email.
   *
   * Valid: initialisation.
   *
   * @return bool
   *   Whether successful.
   */
  public function send();

  /**
   * Gets the account associated with the recipient of this email.
   *
   * The account is calculated automatically from the to address.
   *
   * Valid: after rendering.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   The account.
   */
  public function getAccount();

  /**
   * Sets the unrendered email body array.
   *
   * The email body will be rendered using a template, then used to form both
   * the HTML and plain text body parts. This process can be customised by the
   * email adjusters added to the email.
   *
   * Valid: before rendering.
   *
   * @param mixed $body
   *   Unrendered email body array.
   *
   * @return $this
   */
  public function setBody($body);

  /**
   * Builds the email body array from an entity.
   *
   * Valid: before rendering.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to render.
   * @param string $view_mode
   *   (optional) The view mode that should be used to render the entity.
   *
   * @return $this
   */
  public function setBodyEntity(EntityInterface $entity, $view_mode = 'full');

  /**
   * Gets the unrendered email body array.
   *
   * Valid: before rendering.
   *
   * @return array
   *   Body render array.
   */
  public function getBody();

  /**
   * Sets the email subject.
   *
   * @param \Drupal\Component\Render\MarkupInterface|string $subject
   *   The email subject.
   * @param bool $replace
   *   (Optional) TRUE to replace variables in the email subject.
   *
   * @return $this
   */
  public function setSubject($subject, bool $replace = FALSE);

  /**
   * Gets the email subject.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string
   *   The email subject, or NULL if not set.
   */
  public function getSubject();

  /**
   * Sets variables available in the email template.
   *
   * Valid: build.
   *
   * @param array $variables
   *   An array of keyed variables.
   *
   * @return $this
   */
  public function setVariables(array $variables);

  /**
   * Sets a variable available in the email template.
   *
   * Valid: build.
   *
   * @param string $key
   *   Variable key to set.
   * @param mixed $value
   *   Variable value to set.
   *
   * @return $this
   */
  public function setVariable(string $key, $value);

  /**
   * Gets variables available in the email template.
   *
   * @return array
   *   An array of keyed variables.
   */
  public function getVariables();

  /**
   * Gets the email type.
   *
   * If the email is associated with a config entity, then this is the entity
   * type, else it is the module name.
   *
   * @return string
   *   Email type.
   */
  public function getType();

  /**
   * Gets the email sub-type.
   *
   * The sub-type is a 'key' to distinguish different categories of email with
   * the same type. Emails with the same sub-type are all built in the same
   * way, differently from other sub-types.
   *
   * @return string
   *   Email sub-type.
   */
  public function getSubType();

  /**
   * Gets the associated config entity.
   *
   * The ID of this entity can be used to select a specific template or apply
   * specific policy configuration.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface
   *   Entity, or NULL if there is no associated entity.
   */
  public function getEntity();

  /**
   * Gets an array of 'suggestions'.
   *
   * The suggestions are used to select email builders, templates and policy
   * configuration in based on email type, sub-type and associated entity ID.
   *
   * @param string $initial
   *   The initial suggestion.
   * @param string $join
   *   The 'glue' to join each suggestion part with.
   *
   * @return array
   *   Suggestions, formed by taking the initial value and incrementally adding
   *   the type, sub-type and entity ID.
   */
  public function getSuggestions(string $initial, string $join);

  /**
   * Sets the email theme.
   *
   * Valid: build.
   *
   * @param string $theme_name
   *   The theme name to use for email.
   *
   * @return $this
   */
  public function setTheme(string $theme_name);

  /**
   * Gets the email theme name.
   *
   * @return string
   *   The email theme name.
   */
  public function getTheme();

  /**
   * Adds an asset library to use as mail CSS.
   *
   * Valid: before rendering.
   *
   * @param string $library
   *   Library name, in the form "THEME/LIBRARY".
   *
   * @return $this
   */
  public function addLibrary(string $library);

  /**
   * Gets the libraries to use as mail CSS.
   *
   * @return array
   *   Array of library names, in the form "THEME/LIBRARY".
   */
  public function getLibraries();

  /**
   * Sets the mailer transport DSN to use.
   *
   * @param string $dsn
   *   Symfony mailer transport DSN.
   *
   * @return $this
   */
  public function setTransportDsn(string $dsn);

  /**
   * Gets the mailer transport DSN that will be used.
   *
   * @return string
   *   Transport DSN.
   */
  public function getTransportDsn();

  /**
   * Gets the error message from sending the email.
   *
   * @return string
   *   Error message, or NULL if there is no error.
   */
  public function getError();

}
