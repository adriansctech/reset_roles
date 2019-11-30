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

    foreach ($this->rolesMultiples as $role) {
      if ($role->id() != 'anonymous') {
        $form[$role->id()] = [
          '#type' => 'checkbox',
          '#title' => $role->label(),
        ];
      }
    }

    $form['#theme'] = 'reset_roles_form';
    $form['#attached']['library'][] = 'reset_roles/reset_roles.libraries';

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset pasword by role'),
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

    $checkedRolesObjects = array_filter(array_filter($form, function ($e) {
      return $e['#type'] == 'checkbox';
    }), function ($e) {
      return $e['#checked'] == 1;
    });

    $checkedRolesTitles = array_map(function ($value) {
      return $value['#title'];
    }, $checkedRolesObjects);

    // Get machine name of roles.
    $checkedRolesNames = array_map(function ($value) {
      return $value['#name'];
    }, $checkedRolesObjects);

    // Get list of uids of users.
    $query = \Drupal::entityQuery('user');
    $uids = $query->execute();

    $this->usersToReset = $this->checkUsers($uids, $checkedRolesNames);

    /*foreach ($this->usersToReset as $user ) {
    $this->resetPasswrodOfUser($user);
    $operations[] = ['Drupal\reset_roles\Form\RolesForm::resetPasswrodOfUser', ['resetPasswrodOfUser' => [$user]]];
    }*/

    /*
    $batch = array(
    'title' => t('Send email and force logout'),
    'operations' => $operations,
    'finished' => 'All passwords has reset',
    'init_message' => t('Starting to reset passwords.'),
    'progress_message' => t('Processed @current out of @total. Estimated time: @estimate.'),
    'error_message' => t('Something was wrong.'),
    );

    batch_set($batch);
     */
    $this->resetPasswordOfUsers($this->usersToReset);
    drupal_set_message(t('Se han encontrado un total de ' . sizeof($this->usersToReset) . ' usuarios.'), 'status');

  }

  /**
   * Check if users has any role.
   *
   * @param $uids
   *   Uids of all users in the system
   * @param $roles
   *   Roles axist in the system
   *
   * @return
   *   $users list of uids of user has roles
   */
  public function checkUsers($uids, $roles) {

    $users;
    $usersGlobals = array_map(function ($uid) {
      return User::load($uid);
    }, $uids);

    foreach ($usersGlobals as $key => $user) {
      foreach ($roles as $key => $role) {
        if (in_array($role, $user->getRoles())) {
          $users[] = $user;
        }
      }
    }

    return $users;

  }

  /**
   *
   */
  public function resetPasswrodOfUser($uid) {

    $random = new Random();
    $mailManager = \Drupal::service('plugin.manager.mail');
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $params['context']['subject'] = "Reset password of " . \Drupal::config('system.site')->get('name');
    $params['context']['message'] = "This is a simply email to reset password. Next you have a url to reset password of site: <br> " . user_pass_reset_url($uid) . "";
    $to = $uid->getEmail();
    $secure_check = $this->sanitize_my_email($to);
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
  }

  /**
   * Force reset password of determinated users via email, and force to logout inmediatly.
   *
   * @param $users
   *   Array with users objects to reset password
   */
  public function resetPasswordOfUsers($users) {

    $random = new Random();
    array_map(function ($uid) use ($random) {
            $mailManager = \Drupal::service('plugin.manager.mail');
            $langcode = \Drupal::currentUser()->getPreferredLangcode();
            $params['context']['subject'] = "Reset password of " . \Drupal::config('system.site')->get('name');
            $params['context']['message'] = "This is a simply email to reset password. Next you have a url to reset password of site: <br> " . user_pass_reset_url($uid) . "";
            $to = $uid->getEmail();
            $secure_check = $this->sanitize_my_email($to);
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
    }, $users);
  }

  /**
   * To check if any field is secure.
   *
   * @param $field
   *   field to check
   *
   * @return
   *   TRUE if field is sanitize
   *   FALSE if field isn't sanitize
   */
  public function sanitize_my_email($field) {
    $field = filter_var($field, FILTER_SANITIZE_EMAIL);
    if (filter_var($field, FILTER_VALIDATE_EMAIL)) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

}
