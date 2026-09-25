<?php

declare(strict_types=1);

namespace Drupal\bebbo_custom_general\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tmgmt\ContinuousManager;
use Drupal\tmgmt\Entity\JobItem;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt\JobItemInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Moves the selected translation jobs to a chosen state.
 *
 * TMGMT only offers the transitions its own workflow needs, so a job that was
 * aborted has no way back through the UI. This offers the same targets the
 * State filter on the job overview offers, for the cases that workflow does
 * not cover.
 */
#[Action(
  id: 'bebbo_tmgmt_job_change_state',
  label: new TranslatableMarkup('Change the translation job state'),
  type: 'tmgmt_job',
)]
final class TmgmtJobChangeState extends ViewsBulkOperationsActionBase implements ContainerFactoryPluginInterface, PluginFormInterface {

  use StringTranslationTrait;

  /**
   * Prefix marking a target that is an item state rather than a job state.
   */
  private const ITEM_STATE_PREFIX = 'job_item_';

  /**
   * Constructs a new TmgmtJobChangeState object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\tmgmt\ContinuousManager $continuousManager
   *   The continuous job manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly ContinuousManager $continuousManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    // Neither ActionBase nor the VBO base merges the defaults in, and VBO
    // builds the configuration form from a bare instance.
    $this->configuration += $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('tmgmt.continuous'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['state' => self::ITEM_STATE_PREFIX . JobItemInterface::STATE_ACTIVE];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['state'] = [
      '#type' => 'select',
      '#title' => $this->t('Move the selected jobs to'),
      '#options' => $this->stateOptions(),
      '#default_value' => $this->configuration['state'],
      '#required' => TRUE,
      '#description' => $this->t('These are the targets the State filter offers on this page. An "Items - …" target moves the job to Active and puts every item that is not already accepted into that item state. The other targets set the job state and let TMGMT cascade to the items the way it does anywhere else.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?JobInterface $job = NULL): TranslatableMarkup {
    $options = $this->stateOptions();
    $target = (string) $this->configuration['state'];

    if ($job === NULL || !isset($options[$target])) {
      return $this->t('Skipped: no job, or an unknown target state.');
    }

    if (str_starts_with($target, self::ITEM_STATE_PREFIX)) {
      $item_state = (int) substr($target, strlen(self::ITEM_STATE_PREFIX));
      return $this->moveItems($job, $item_state, $options[$target]);
    }

    return $this->moveJob($job, (int) $target, $options[$target]);
  }

  /**
   * Builds the target list the job overview State filter offers.
   *
   * Mirrors \Drupal\tmgmt\Plugin\views\filter\JobState::getValueOptions(),
   * minus "- Open jobs -", which describes a set of states and so cannot be
   * a target.
   *
   * @return array
   *   Job states keyed by their integer value, item states keyed by the item
   *   state prefixed with self::ITEM_STATE_PREFIX.
   */
  private function stateOptions(): array {
    $options = [JobInterface::STATE_UNPROCESSED => $this->t('Unprocessed')];

    foreach (JobItem::getStateDefinitions() as $state => $definition) {
      // A translator state is held in its own column and owned by the
      // translator plugin, so only the real item states are offered.
      if (!empty($definition['show_job_filter']) && $definition['type'] === 'state') {
        $options[self::ITEM_STATE_PREFIX . $state] = $this->t('Items - @item_state', [
          '@item_state' => $definition['label'],
        ]);
      }
    }

    $options += [
      JobInterface::STATE_REJECTED => $this->t('Rejected'),
      JobInterface::STATE_ABORTED => $this->t('Aborted'),
      JobInterface::STATE_FINISHED => $this->t('Finished'),
    ];

    if ($this->continuousManager->checkIfContinuousTranslatorAvailable()) {
      $options[JobInterface::STATE_CONTINUOUS] = $this->t('Continuous');
    }

    return $options;
  }

  /**
   * Moves a job to an item state, the way the State filter reads one.
   *
   * @param \Drupal\tmgmt\JobInterface $job
   *   The job to move.
   * @param int $item_state
   *   The item state to move the job items to.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The label of the chosen target, for the message log.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The result to report for this job.
   */
  private function moveItems(JobInterface $job, int $item_state, TranslatableMarkup $label): TranslatableMarkup {
    // The filter reads these targets as "an active job holding items in this
    // state", so the job goes to Active along with them.
    if (!$job->isContinuous() && !$job->isActive()) {
      $job->submitted('The job was reopened as @state from the job overview.', ['@state' => $label]);
    }

    $moved = 0;
    foreach ($job->getItems() as $item) {
      // An accepted item has already been written back to its source. Nothing
      // about a job state change should undo that.
      if ($item->isAccepted() || (int) $item->getState() === $item_state) {
        continue;
      }
      $item->setState($item_state, 'The item was moved to @state from the job overview.', ['@state' => $label], 'status');
      $moved++;
    }

    return $this->t('@label: @count item(s) moved.', [
      '@label' => $label,
      '@count' => $moved,
    ]);
  }

  /**
   * Moves a job to a job state.
   *
   * @param \Drupal\tmgmt\JobInterface $job
   *   The job to move.
   * @param int $state
   *   The job state to move to.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The label of the chosen target, for the message log.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The result to report for this job.
   */
  private function moveJob(JobInterface $job, int $state, TranslatableMarkup $label): TranslatableMarkup {
    if ((int) $job->getState() === $state) {
      return $this->t('Skipped: already @state.', ['@state' => $label]);
    }
    // Continuous is a job type, not a stage a normal job can be moved into.
    if ($state === JobInterface::STATE_CONTINUOUS && !$job->isContinuous()) {
      return $this->t('Skipped: only a continuous job can be set to Continuous.');
    }

    $message = 'The job state was changed to @state from the job overview.';
    $variables = ['@state' => $label];

    // Go through the entity's own transitions where it has one, so the side
    // effects TMGMT attaches to them still run. Aborting, for instance,
    // cascades to the job items exactly as the Abort form does.
    if ($state === JobInterface::STATE_REJECTED) {
      $job->rejected($message, $variables, 'status');
    }
    elseif ($state === JobInterface::STATE_ABORTED) {
      $job->aborted($message, $variables);
    }
    elseif ($state === JobInterface::STATE_FINISHED) {
      $job->finished($message, $variables);
    }
    else {
      $job->setState($state, $message, $variables, 'status');
    }

    return $this->t('Moved to @state.', ['@state' => $label]);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    // JobAccessControlHandler grants 'update' to anyone holding "create
    // translation jobs" or "accept translation jobs", which is far too wide
    // for setting the state by hand.
    $access = AccessResult::allowedIfHasPermission($account, 'administer tmgmt')
      ->andIf($object->access('update', $account, TRUE));

    return $return_as_object ? $access : $access->isAllowed();
  }

}
