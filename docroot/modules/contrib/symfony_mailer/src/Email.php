<?php

namespace Drupal\symfony_mailer;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Mime\Email as SymfonyEmail;

/**
 * Defines the email class.
 */
class Email implements InternalEmailInterface {

  use BaseEmailTrait;

  /**
   * The mailer.
   *
   * @var \Drupal\symfony_mailer\MailerInterface
   */
  protected $mailer;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The type.
   *
   * @var string
   */
  protected $type;

  /**
   * The subtype.
   *
   * @var string
   */
  protected $subType;

  /**
   * The config entity.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityInterface
   */
  protected $entity;

  /**
   * Current phase, one of the PHASE_ constants.
   *
   * @var int
   */
  protected $phase = self::PHASE_INIT;

  /**
   * The body array.
   *
   * @var array
   */
  protected $body = [];

  /**
   * The email subject.
   *
   * @var \Drupal\Component\Render\MarkupInterface|string
   */
  protected $subject;

  /**
   * Whether to replace variables in the email subject.
   *
   * @var bool
   */
  protected $subjectReplace;

  /**
   * The processors.
   *
   * @var array
   */
  protected $processors = [];

  /**
   * The language code.
   *
   * @var string
   */
  protected $langcode;

  /**
   * The params.
   *
   * @var string[]
   */
  protected $params = [];

  /**
   * The variables.
   *
   * @var string[]
   */
  protected $variables = [];

  /**
   * The account for the recipient (can be anonymous).
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The theme.
   *
   * @var string
   */
  protected $theme = '';

  /**
   * The libraries.
   *
   * @var array
   */
  protected $libraries = [];

  /**
   * The mail transport DSN.
   *
   * @var string
   */
  protected $transportDsn = '';

  /**
   * The error message from sending.
   *
   * @var string
   */
  protected $errorMessage;

  /**
   * Constructs the Email object.
   *
   * @param \Drupal\symfony_mailer\MailerInterface $mailer
   *   Mailer service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param string $type
   *   Type. @see self::getType()
   * @param string $sub_type
   *   Sub-type. @see self::getSubType()
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface|null $entity
   *   (optional) Entity. @see self::getEntity()
   */
  public function __construct(MailerInterface $mailer, RendererInterface $renderer, EntityTypeManagerInterface $entity_type_manager, ThemeManagerInterface $theme_manager, ConfigFactoryInterface $config_factory, string $type, string $sub_type, ?ConfigEntityInterface $entity) {
    $this->mailer = $mailer;
    $this->renderer = $renderer;
    $this->entityTypeManager = $entity_type_manager;
    $this->themeManager = $theme_manager;
    $this->configFactory = $config_factory;
    $this->type = $type;
    $this->subType = $sub_type;
    $this->entity = $entity;
    $this->inner = new SymfonyEmail();
  }

  /**
   * Creates an email object.
   *
   * Use EmailFactory instead of calling this directly.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The current service container.
   * @param string $type
   *   Type. @see self::getType()
   * @param string $sub_type
   *   Sub-type. @see self::getSubType()
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface|null $entity
   *   (optional) Entity. @see self::getEntity()
   *
   * @return static
   *   A new email object.
   */
  public static function create(ContainerInterface $container, string $type, string $sub_type, ?ConfigEntityInterface $entity = NULL) {
    return new static(
      $container->get('symfony_mailer'),
      $container->get('renderer'),
      $container->get('entity_type.manager'),
      $container->get('theme.manager'),
      $container->get('config.factory'),
      $type,
      $sub_type,
      $entity
    );
  }

  /**
   * {@inheritdoc}
   */
  public function addProcessor(callable $function, int $phase = self::PHASE_BUILD, int $weight = self::DEFAULT_WEIGHT, string $id = NULL) {
    $this->valid(self::PHASE_INIT, self::PHASE_INIT);
    $this->processors[$phase][] = [
      'function' => $function,
      'weight' => $weight,
      'id' => $id,
    ];
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLangcode() {
    $this->valid(self::PHASE_POST_SEND, self::PHASE_PRE_RENDER);
    return $this->langcode;
  }

  /**
   * {@inheritdoc}
   */
  public function setParams(array $params = []) {
    $this->valid(self::PHASE_INIT, self::PHASE_INIT);
    $this->params = $params;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setParam(string $key, $value) {
    $this->valid(self::PHASE_INIT, self::PHASE_INIT);
    $this->params[$key] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getParams() {
    return $this->params;
  }

  /**
   * {@inheritdoc}
   */
  public function getParam(string $key) {
    return $this->params[$key] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function send() {
    $this->valid(self::PHASE_BUILD);
    return $this->mailer->send($this);
  }

  /**
   * {@inheritdoc}
   */
  public function getAccount() {
    $this->valid(self::PHASE_POST_SEND, self::PHASE_PRE_RENDER);
    return $this->account;
  }

  /**
   * {@inheritdoc}
   */
  public function setBody($body) {
    $this->valid(self::PHASE_PRE_RENDER);
    $this->body = $body;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setBodyEntity(EntityInterface $entity, $view_mode = 'full') {
    $this->valid(self::PHASE_PRE_RENDER);
    $build = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId())
      ->view($entity, $view_mode);
    $this->setBody($build);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getBody() {
    $this->valid(self::PHASE_PRE_RENDER);
    return $this->body;
  }

  /**
   * {@inheritdoc}
   */
  public function setSubject($subject, bool $replace = FALSE) {
    // We must not force conversion of the subject to a string as this could
    // cause translation before switching to the correct language.
    $this->subject = $subject;
    $this->subjectReplace = $replace;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubject() {
    return $this->subject;
  }

  /**
   * {@inheritdoc}
   */
  public function setVariables(array $variables) {
    $this->valid(self::PHASE_BUILD, self::PHASE_INIT);
    $this->variables = $variables;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setVariable(string $key, $value) {
    $this->valid(self::PHASE_BUILD, self::PHASE_INIT);
    $this->variables[$key] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getVariables() {
    return $this->variables;
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return $this->type;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubType() {
    return $this->subType;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getSuggestions(string $initial, string $join) {
    $part_array = [$this->type, $this->subType];
    if (isset($this->entity)) {
      $part_array[] = $this->entity->id();
    }

    $part = $initial ?: array_shift($part_array);
    $suggestions[] = $part;

    while ($part_array) {
      $part .= $join . array_shift($part_array);
      $suggestions[] = $part;
    }

    return $suggestions;
  }

  /**
   * {@inheritdoc}
   */
  public function setTheme(string $theme_name) {
    $this->valid(self::PHASE_BUILD);
    $this->theme = $theme_name;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTheme() {
    if (!$this->theme) {
      $this->theme = $this->themeManager->getActiveTheme()->getName();
    }
    return $this->theme;
  }

  /**
   * {@inheritdoc}
   */
  public function addLibrary(string $library) {
    $this->libraries[] = $library;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries() {
    return $this->libraries;
  }

  /**
   * {@inheritdoc}
   */
  public function setTransportDsn(string $dsn) {
    $this->transportDsn = $dsn;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTransportDsn() {
    return $this->transportDsn;
  }

  /**
   * {@inheritdoc}
   */
  public function setError(string $error) {
    $this->valid(self::PHASE_POST_SEND, self::PHASE_POST_SEND);
    $this->errorMessage = $error;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getError() {
    return $this->errorMessage;
  }

  /**
   * {@inheritdoc}
   */
  public function process() {
    $processors = $this->processors[$this->phase] ?? [];
    usort($processors, function ($a, $b) {
      return $a['weight'] <=> $b['weight'];
    });

    foreach ($processors as $processor) {
      call_user_func($processor['function'], $this);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function initDone() {
    $this->valid(self::PHASE_INIT, self::PHASE_INIT);
    $this->phase = self::PHASE_BUILD;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function customize(string $langcode, AccountInterface $account) {
    $this->valid(self::PHASE_BUILD);
    $this->langcode = $langcode;
    $this->account = $account;
    $this->phase = self::PHASE_PRE_RENDER;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $this->valid(self::PHASE_PRE_RENDER, self::PHASE_PRE_RENDER);

    // Render subject.
    if ($this->subjectReplace && $this->variables) {
      $subject = [
        '#type' => 'inline_template',
        '#template' => $this->subject,
        '#context' => $this->variables,
      ];
      $this->subject = $this->renderer->renderPlain($subject);
    }

    if ($this->subject instanceof MarkupInterface) {
      $this->subject = PlainTextOutput::renderFromHtml($this->subject);
    }

    // Render body.
    $body = ['#theme' => 'email', '#email' => $this];
    $html = $this->renderer->renderPlain($body);
    $this->phase = self::PHASE_POST_RENDER;
    $this->setHtmlBody($html);
    $this->body = [];

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPhase() {
    return $this->phase;
  }

  /**
   * {@inheritdoc}
   */
  public function getSymfonyEmail() {
    $this->valid(self::PHASE_POST_RENDER, self::PHASE_POST_RENDER);
    $this->phase = self::PHASE_POST_SEND;

    if ($this->subject) {
      $this->inner->subject($this->subject);
    }

    $this->inner->sender($this->sender->getSymfony());
    $headers = $this->getHeaders();
    foreach ($this->addresses as $name => $addresses) {
      $value = [];
      foreach ($addresses as $address) {
        $value[] = $address->getSymfony();
      }
      if ($value) {
        $headers->addMailboxListHeader($name, $value);
      }
    }

    return $this->inner;
  }

  /**
   * Checks that a function was called in the correct phase.
   *
   * @param int $max_phase
   *   Latest allowed phase, one of the PHASE_ constants.
   * @param int $min_phase
   *   (Optional) Earliest allowed phase, one of the PHASE_ constants.
   *
   * @return $this
   */
  protected function valid(int $max_phase, int $min_phase = self::PHASE_BUILD) {
    $valid = ($this->phase <= $max_phase) && ($this->phase >= $min_phase);

    if (!$valid) {
      $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];
      throw new \LogicException("$caller function is only valid in phases $min_phase-$max_phase, called in $this->phase.");
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * Serialization is intended only for testing.
   *
   * @internal
   */
  public function __serialize() {
    // Exclude $this->params, $this->variables as they may not serialize.
    return [$this->type, $this->subType,
      $this->entity ? $this->entity->id() : '',
      $this->phase, $this->subject, $this->langcode,
      $this->account ? $this->account->id() : '', $this->theme,
      $this->libraries, $this->transportDsn, $this->inner,
      $this->addresses, $this->sender,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function __unserialize(array $data) {
    [$this->type, $this->subType, $entity_id, $this->phase,
      $this->subject, $this->langcode, $account_id, $this->theme,
      $this->libraries, $this->transportDsn, $this->inner,
      $this->addresses, $this->sender,
    ] = $data;

    if ($entity_id) {
      $this->entity = $this->configFactory->get($entity_id);
    }
    if ($account_id) {
      $this->account = User::load($account_id);
    }
  }

}
