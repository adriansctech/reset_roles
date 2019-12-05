<?php

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountInterface;

namespace Drupal\reset_roles\Services;

/**
 * Class CustomService.
 */
class ResetRoleService {

  protected $currentUser;

  /**
   * Constructs a new CustomService object.
   */
  public function __construct(AccountInterface $currentUser) {

    $this->currentUser = $currentUser;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      // Load the service required to construct this class.
      $container->get('current_user')
    );
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
   * Reset password of user and logout.
   *
   * @param $user
   *   user Object
   */
  public function logOutUserAndResetPass($user) {

    $this->currentUser->setAccount($user);
    if ($this->currentUser->isAuthenticated()) {
      $session_manager = \Drupal::service('session_manager');
      $session_manager->delete($this->currentUser->id());
    }
    $random = new Random();
    $string = $random->string();
    $user->setPassword($string);
    $user->save();
  }

  /**
   * Send email with url to reset passwor to user.
   *
   * @param $user
   *   user Object
   */
  public function sendEmailToReset($user) {
    $userObject = user_load($user->id());
    $mailManager = \Drupal::service('plugin.manager.mail');
    $langcode = $userObject->getPreferredLangcode();
    $params['context']['subject'] = "Reset password of " . \Drupal::config('system.site')->get('name');
    $params['context']['message'] = "This is a simply email to reset password. Next you have a url to reset password of site: <br> " . user_pass_reset_url($userObject) . "";
    $to = $userObject->getEmail();
    if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
      $mailManager->mail('system', 'mail', $to, $langcode, $params);
    }
  }

}
