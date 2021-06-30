<?php
/**
 * @file
 * Contains \Drupal\pb_custom_form\Form\CustomForm.
 */
namespace Drupal\pb_custom_form\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Database\Database;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\group\Entity\Group;

/**
 * Implements a Custom Form
 */
class CustomForm extends FormBase
{
  /**
   * @return string
   */
  public function getFormId()
  {
    return 'forcefull_check_update_api';
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   * @return array
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {              
    $groups = Group::loadMultiple(); 	
    foreach($groups as $gid => $group) {
      $id = $group->get('id')->getString(); 
      $label = $group->get('label')->getString();   
      $coutry_group[$id] = $label;      
    } 

    // Dropdown Select
    $form['country_select'] = [
      '#type' => 'select',
      '#title' => $this->t('Country'),
      '#options' => $coutry_group,
    ];

    // CheckBoxes.
    $form['checkbox'] = [
      '#type' => 'checkbox',      
      '#title' => $this->t('Force Update Check'),
      '#return_value' => 1,
      '#default_value' => FALSE
    ];    

    $form['actions'] = [
      '#type' => 'actions',
    ];

    // Add a submit button
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary'
    ];

    return $form;
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    // $title = $form_state->getValue('title');
    // if (strlen($title) < 15) {
    //   $form_state->setErrorByName('title', $this->t('The title must be at least 15 characters long.'));
    // }
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $date = new DrupalDateTime();
    $conn = Database::getConnection();
    $conn->insert('forcefull_check_update_api')->fields(
      array(
        'flag' => $form_state->getValue('checkbox'),
        'country_id' => $form_state->getValue('country_select'),
        'updated_at' => $date->getTimestamp()
      )
    )->execute();

    $checkbox = $form_state->getValue('checkbox');
    $text_msg = "Disabled";
    if($checkbox == 1)
    {
        $text_msg = "Enbled";    
    }
    
    drupal_set_message("Force Check Update API ".$text_msg);
  }

}