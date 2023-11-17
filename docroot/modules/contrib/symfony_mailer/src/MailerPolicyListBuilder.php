<?php

namespace Drupal\symfony_mailer;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\symfony_mailer\Entity\MailerPolicy;

/**
 * Defines a class to build a listing of mailer policy entities.
 *
 * @todo Add filters by type and by adjuster.
 */
class MailerPolicyListBuilder extends ConfigEntityListBuilder implements MailerPolicyListBuilderInterface {

  /**
   * Overridden list of entities.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected $overrideEntities;

  /**
   * The type to filter results by.
   *
   * @var string
   */
  protected $filterType;

  /**
   * The columns to hide.
   *
   * @var string[]
   */
  protected $hideColumns = [];

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'type' => $this->t('Type'),
      'sub_type' => $this->t('Sub-type'),
      'entity' => $this->t('Entity'),
      'summary' => $this->t('Summary'),
    ];
    return array_diff_key($header, $this->hideColumns) + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $summary['data']['#markup'] = $entity->getSummary(!empty($this->overrideEntities));
    $row = [
      'type' => $entity->getTypeLabel(),
      'sub_type' => $entity->getSubTypeLabel(),
      'entity' => $entity->getEntityLabel(),
      'summary' => $summary,
    ];
    return array_diff_key($row, $this->hideColumns) + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    return $this->overrideEntities ?? parent::load();
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    if ($entity->isNew()) {
      $operations['create'] = [
        'title' => $this->t('Create'),
        'weight' => -10,
        'url' => $this->ensureDestination(Url::fromRoute('entity.mailer_policy.add_id_form', ['policy_id' => $entity->id()])),
      ];
    }
    else {
      $operations = parent::getDefaultOperations($entity);
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    $query = $this->getStorage()->getQuery()->accessCheck(FALSE);

    if ($this->filterType) {
      $query->condition('id', "$this->filterType.", 'STARTS_WITH');
    }

    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function overrideEntities(array $entity_ids) {
    foreach ($entity_ids as $policy_id) {
      $this->overrideEntities[] = MailerPolicy::loadOrCreate($policy_id);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function filterType(string $type) {
    $this->filterType = $type;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hideColumns(array $columns) {
    $this->hideColumns = array_flip($columns);
    return $this;
  }

}
