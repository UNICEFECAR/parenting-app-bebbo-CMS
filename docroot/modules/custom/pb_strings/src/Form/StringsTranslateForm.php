<?php

namespace Drupal\pb_strings\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for editing string translations inline.
 */
class StringsTranslateForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Constructs a StringsTranslateForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LanguageManagerInterface $language_manager,
    Connection $database,
    AccountProxyInterface $current_user,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
    $this->database = $database;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('language_manager'),
      $container->get('database'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'pb_strings_translate_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $filter = StringsFilterForm::getFilterValues();
    $langcode = $filter['langcode'];
    $search = $filter['string'];

    $language = $this->languageManager->getLanguage($langcode);
    $language_name = $language ? $language->getName() : $langcode;

    $query = $this->database->select('taxonomy_term_field_data', 'td');
    $query->leftJoin('taxonomy_term__field_unique_name', 'un', 'td.tid = un.entity_id AND un.deleted = 0');
    $query->leftJoin('taxonomy_term_field_data', 'td_trans', 'td.tid = td_trans.tid AND td_trans.langcode = :td_trans_lang AND td_trans.default_langcode = 0', [':td_trans_lang' => $langcode]);
    $query->fields('td', ['tid', 'name']);
    $query->addField('un', 'field_unique_name_value', 'unique_name');
    $query->condition('td.vid', 'strings');
    $query->condition('td.default_langcode', 1);

    if (!empty($search)) {
      $pattern = '%' . $this->database->escapeLike($search) . '%';
      $query->where('(td.name LIKE :src_pattern OR td_trans.name LIKE :trans_pattern)', [
        ':src_pattern' => $pattern,
        ':trans_pattern' => $pattern,
      ]);
    }

    $query->orderBy('un.field_unique_name_value', 'ASC');

    $pager_query = $query->extend(PagerSelectExtender::class);
    assert($pager_query instanceof PagerSelectExtender);
    $pager_query->limit(30);
    $results = $pager_query->execute()->fetchAllAssoc('tid');

    if (empty($results)) {
      $form['empty'] = [
        '#markup' => '<p>' . $this->t('No strings found.') . '</p>',
      ];
      return $form;
    }

    $tids = array_keys($results);
    $translations = $this->loadTranslations($tids, $langcode);

    $form['langcode'] = [
      '#type' => 'value',
      '#value' => $langcode,
    ];

    $can_delete = $this->currentUser->hasPermission('administer strings');

    $header = [
      'unique_name' => $this->t('Unique name'),
      'source' => $this->t('Source string'),
      'translation' => $this->t('Translation for @language', ['@language' => $language_name]),
    ];
    if ($can_delete) {
      $header['operations'] = $this->t('Operations');
    }

    $form['strings'] = [
      '#type' => 'table',
      '#header' => $header,
    ];

    foreach ($results as $tid => $row) {
      $form['strings'][$tid]['unique_name'] = [
        '#markup' => htmlspecialchars($row->unique_name ?? ''),
      ];

      $form['strings'][$tid]['source'] = [
        '#markup' => htmlspecialchars($row->name),
      ];

      $form['strings'][$tid]['translation'] = [
        '#type' => 'textarea',
        '#default_value' => $translations[$tid] ?? '',
        '#rows' => 2,
      ];

      if ($can_delete) {
        $form['strings'][$tid]['operations'] = [
          '#type' => 'link',
          '#title' => $this->t('Delete'),
          '#url' => Url::fromRoute(
            'entity.taxonomy_term.delete_form',
            ['taxonomy_term' => $tid],
            [
              'query' => [
                'destination' => Url::fromRoute(
                  'pb_strings.strings_list',
                  ['taxonomy_vocabulary' => 'strings']
                )->toString(),
              ],
            ]
          ),
        ];
      }
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save translation'),
    ];

    $form['pager'] = [
      '#type' => 'pager',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $langcode = $form_state->getValue('langcode');
    $strings = $form_state->getValue('strings');
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $saved_count = 0;

    foreach ($strings as $tid => $values) {
      $new_translation = trim($values['translation'] ?? '');
      $original = $form['strings'][$tid]['translation']['#default_value'] ?? '';

      if ($new_translation === $original) {
        continue;
      }

      /** @var \Drupal\taxonomy\TermInterface|null $term */
      $term = $term_storage->load($tid);
      if ($term === NULL) {
        continue;
      }

      if ($term->hasTranslation($langcode)) {
        $translation = $term->getTranslation($langcode);
        if (empty($new_translation)) {
          $term->removeTranslation($langcode);
        }
        else {
          $translation->set('name', $new_translation);
        }
      }
      elseif (!empty($new_translation)) {
        $translation = $term->addTranslation($langcode, ['name' => $new_translation]);
      }
      else {
        continue;
      }

      $term->save();
      $saved_count++;
    }

    if ($saved_count > 0) {
      $this->messenger()->addStatus($this->t('Translations have been saved.'));
    }

    $form_state->setRedirect('pb_strings.strings_list', [
      'taxonomy_vocabulary' => 'strings',
    ]);
  }

  /**
   * Loads existing translations for a set of term IDs.
   *
   * @param array $tids
   *   Array of taxonomy term IDs.
   * @param string $langcode
   *   The language code to load translations for.
   *
   * @return array
   *   Associative array of tid => translated name value.
   */
  protected function loadTranslations(array $tids, string $langcode): array {
    if (empty($tids)) {
      return [];
    }

    $query = $this->database->select('taxonomy_term_field_data', 'td');
    $query->fields('td', ['tid', 'name']);
    $query->condition('td.tid', $tids, 'IN');
    $query->condition('td.langcode', $langcode);
    $query->condition('td.default_langcode', 0);

    return $query->execute()->fetchAllKeyed(0, 1);
  }

}
