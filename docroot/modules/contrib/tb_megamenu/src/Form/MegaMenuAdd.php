<?php

namespace Drupal\tb_megamenu\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\tb_megamenu\Entity\MegaMenuConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Serialization\Json;

/**
 * Form handler for adding MegaMenuConfig entities.
 */
class MegaMenuAdd extends EntityForm {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $config;

  /**
   * The theme handler service.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected ThemeHandlerInterface $themeHandler;

  /**
   * Constructs a MegaMenuAdd object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory service.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ThemeHandlerInterface $theme_handler) {
    $this->config = $config_factory;
    $this->themeHandler = $theme_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): MegaMenuAdd|static {
    return new static(
      $container->get('config.factory'),
      $container->get('theme_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    /** @var \Drupal\system\Entity\Menu $menuStorage */
    $menuStorage = $this->entityTypeManager->getStorage('menu');
    $form = parent::form($form, $form_state);
    $menu_list = array_map(
      function ($menu) {
        return $menu->label();
      },
      $menuStorage->loadMultiple()
    );

    $menus = $menu_list;
    asort($menu_list);

    $info = $this->themeHandler->listInfo();
    $themes = [];
    foreach ($info as $name => $theme) {
      if (!isset($theme->info['hidden'])) {
        $themes[$name] = $theme->info['name'];
      }
    }
    $default = $this->config->get('system.theme')->get('default');

    $form['menu'] = [
      '#type' => 'select',
      '#options' => $menus,
      '#title' => $this->t('Menu'),
      '#maxlength' => 255,
      '#default_value' => NULL,
      '#description' => $this->t("Drupal Menu to use for the Mega Menu."),
      '#required' => TRUE,
    ];
    $form['theme'] = [
      '#type' => 'select',
      '#options' => $themes,
      '#title' => $this->t('Theme'),
      '#maxlength' => 255,
      '#default_value' => $default,
      '#description' => $this->t("Drupal Theme associated with this Mega Menu."),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'value',
      '#value' => '',
    ];
    $form['block_config'] = [
      '#type' => 'value',
      '#value' => Json::encode([]),
    ];
    $form['menu_config'] = [
      '#type' => 'value',
      '#value' => Json::encode([]),
    ];

    // You will need additional form elements for your custom properties.
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\Core\Form\FormBase::validateForm()
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    if (MegaMenuConfig::loadMenu($form_state->getValue('menu'), $form_state->getValue('theme')) !== NULL) {
      $form_state->setErrorByName('menu', $this->t("A Mega Menu has already been created for @menu / @theme", [
        '@menu' => $form_state->getValue('menu'),
        '@theme' => $form_state->getValue('theme'),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\Core\Entity\EntityForm::submitForm()
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $id = $form_state->getValue('menu') . '__' . $form_state->getValue('theme');
    $form_state->setValue('id', $id);

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function save(array $form, FormStateInterface $form_state): void {
    $megamenu = $this->entity;
    $status = $megamenu->save();

    if (isset($megamenu->menu)) {
      if ($status) {
        $this->messenger()
          ->addStatus($this->t('Created the %label Mega Menu, edit it to configure.', [
            '%label' => $megamenu->menu,
          ]));
      }
      else {
        $this->messenger()
          ->addStatus($this->t('The %label Example was not saved.', [
            '%label' => $megamenu->menu,
          ]));
      }
    }

    $form_state->setRedirect('entity.tb_megamenu.edit_form', ['tb_megamenu' => $megamenu->id()]);
  }

  /**
   * Helper function to check whether an Example configuration entity exists.
   */
  public function exist($id): bool {
    if (isset($this->entityQuery)) {
      $entity = $this->entityQuery->get('example')
        ->condition('id', $id)
        ->execute();

      return (bool) $entity;
    }
    else {
      return FALSE;
    }
  }

}
