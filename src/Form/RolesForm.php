<?php

namespace Drupal\reset_roles\Form;

use Drupal\Component\Utility\Random;
use Drupal\user\Entity\User;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\Role;

/**
 * Implements a custom form .
 */
class RolesForm extends FormBase {

  /**
   * Class constructor.
   */
  public function __construct() {
    $roles = [];
    $rolesMultiples;
    $usersToReset;
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

    // Get list of uids of users.
    $query = \Drupal::entityQuery('user');
    $uids = $query->execute();

    $this->usersToReset = $this->checkUsers($uids, $roleValue);
    if ($this->usersToReset) {
      foreach ($this->usersToReset as $key => $value) {
        $operations[] = ['Drupal\reset_roles\Form\RolesForm::sendEmailToReset', ['sendEmailToReset' => $value]];
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

  /**
   * Check if users has any role.
   *
   * @param $uids
   *   Uids of all users in the system
   * @param $role
   *   Role exist in the system
   *
   * @return
   *   $users list of uids of user has roles
   */
  public function checkUsers($uids, $role) {

    $usersGlobals = array_map(function ($uid) {
      return User::load($uid);
    }, $uids);

    foreach ($usersGlobals as $key => $value) {
      if (in_array($role, $value->getRoles())) {
        $users[] = $value;
      }
    }

    if (sizeof($users) > 0) {
      return $users;
    }
    else {
      return FALSE;
    }

  }

  /**
   *
   */
  public function sendEmailToReset($user) {
    $userObject = user_load($user->id());
    $random = new Random();
    $mailManager = \Drupal::service('plugin.manager.mail');
    $langcode = $userObject->getPreferredLangcode();
    $params['context']['subject'] = "Reset password of " . \Drupal::config('system.site')->get('name');
    $params['context']['message'] = "This is a simply email to reset password. Next you have a url to reset password of site: <br> " . user_pass_reset_url($userObject) . "";
    $to = $userObject->getEmail();
    $field = filter_var($to, FILTER_SANITIZE_EMAIL);
    if (filter_var($field, FILTER_VALIDATE_EMAIL)) {
      $mailManager->mail('system', 'mail', $to, $langcode, $params);
    }
    /*

    if ($secure_check != FALSE) {
    $user_storage = \Drupal::entityManager()->getStorage('user');
    $user = $user_storage->load($uid->id());
    // Reset password of user.
    $string = $random->string();
    $uid->setPassword($string);
    $uid->save();
    // Logout user inmediatly.
    \Drupal::currentUser()->setAccount($uid);
    if (\Drupal::currentUser()->isAuthenticated()) {
    $session_manager = \Drupal::service('session_manager');
    $session_manager->delete(\Drupal::currentUser()->id());
    }
    $mailManager->mail('system', 'mail', $to, $langcode, $params);
    }
     */
  }

}
