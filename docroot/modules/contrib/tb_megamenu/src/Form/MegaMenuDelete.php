<?php

namespace Drupal\tb_megamenu\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;

/**
 * Builds the form to delete a megamenu.
 */
class MegaMenuDelete extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup|string {
    if (isset($this->entity->menu)) {
      return $this->t('Are you sure you want to delete %name?', ['%name' => $this->entity->menu]);
    }

    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('entity.tb_megamenu.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (isset($this->entity->menu)) {
      $this->entity->delete();
      $this
        ->messenger()
        ->addStatus(
          $this
            ->t(
              'MegaMenu %label has been deleted.',
              [
                '%label' => $this->entity->menu,
              ]
            )
        );

      $form_state->setRedirectUrl($this->getCancelUrl());
    }
  }

}
