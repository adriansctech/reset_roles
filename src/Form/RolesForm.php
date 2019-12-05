<?php

namespace Drupal\reset_roles\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\Role;

/**
 * Implements a custom form .
 */
class RolesForm extends FormBase {


  protected $our_service;

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->our_service = \Drupal::service('reset_roles.default');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {

    return 'reset_roles_form';

  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $this->rolesMultiples = Role::loadMultiple();
    $option = [];
    $formRolesList = [];

    foreach ($this->rolesMultiples as $role) {
      if ($role->id() != 'anonymous') {
        $formRolesList[$role->id()] = $role->label();
      }
    }

    $form['roles'] = [
      '#type' => 'radios',
      '#title' => $this->t('Select the role'),
      '#default_value' => 0,
      '#options' => $formRolesList,
      '#required' => TRUE,
    ];

    $form['reset'] = [
      '#type' => 'checkbox',
      '#title' => t('Expulsar automáticamente de la sesión'),
      '#description' => t('Si se selecciona esta opción, los usuarios serán expulsados de la sesión actual'),
    ];

    $form['#theme'] = 'reset_roles_form';
    $form['#attached']['library'][] = 'reset_roles/reset_roles.css';

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reiniciar passwords'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $roleValue = $form_state->getValues()['roles'];
    $resetValue = $form_state->getValues()['reset'];

    // Get list of uids of users.
    $query = \Drupal::entityQuery('user');
    $uids = $query->execute();

    $this->usersToReset = $this->our_service->checkUsers($uids, $roleValue);

    if ($this->usersToReset) {

      foreach ($this->usersToReset as $key => $value) {
        if ($resetValue) {
          $this->our_service->logOutUserAndResetPass($value);
        }
        $operations[] = ['Drupal\reset_roles\DefaultService::sendEmailToReset', ['sendEmailToReset' => $value]];
      }
      $batch = [
        'title' => t('Send email and force logout'),
        'operations' => $operations,
        'finished' => 'All passwords has reset',
        'init_message' => t('Starting to reset passwords.'),
        'progress_message' => t('Processed @current out of @total. Estimated time: @estimate.'),
        'error_message' => t('Something was wrong.'),
      ];
      batch_set($batch);
    }
    else {
      drupal_set_message(t('No existe ningún usuario con el rol seleccionado.'), 'warning');
    }

  }

}
