<?php

namespace Drupal\reset_roles\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\Role;


class RolesForm extends FormBase {

	
	public function __construct() {
		$roles = [];
		$rolesMultiples;
		$usersToReset;
	}

	public function getFormId() {

		return 'reset_roles_form';

	}

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
			
		$form['submit'] = [
			'#type' => 'submit',
			'#value'=> $this->t('Reset pasword by role'),
		];

		return $form;
	}

	public function submitForm(array &$form, FormStateInterface $form_state) {
		
		$checkedRolesObjects = array_filter(array_filter($form, function($e) {
			return $e['#type']=='checkbox';
		}),function($e) {
			return $e['#checked']==1;
		});		

		$checkedRolesTitles = array_map(function($value) { 
			return $value['#title'];
		}, $checkedRolesObjects);

		//Get machine name of roles
		$checkedRolesNames = array_map(function($value) { 
			return $value['#name'];
		}, $checkedRolesObjects);

		
		//Get list of uids of users 
		$query = \Drupal::entityQuery('user');
		$uids = $query->execute();
	
		$this->usersToReset = $this->checkUsers($uids, $checkedRolesNames);
		
		$this->resetPasswordOfUsers($this->usersToReset);
		drupal_set_message(t('Se han encontrado un total de ' . sizeof($this->usersToReset) . ' usuarios.'), 'status');
		
	}



	public function checkUsers($uids, $roles) {
		
		$users;
		$usersGlobals = array_map(function($uid){			
			return \Drupal\user\Entity\User::load($uid);				
		}, $uids);		


		foreach ($usersGlobals as $key => $user) {			
			foreach ($roles as $key => $role) {
				if(in_array($role, $user->getRoles())) {
					$users[] = $user;
				}
			}
		}

		return $users;
		
	}

	public function resetPasswordOfUsers($users) {
		$random = new \Drupal\Component\Utility\Random();
		$string = $random->string();		
		array_map(function($uid){			
		    $mailManager = \Drupal::service('plugin.manager.mail');
		    $langcode = \Drupal::currentUser()->getPreferredLangcode();
		    $params['context']['subject'] = "Reset password of " . \Drupal::config('system.site')->get('name');
		    $params['context']['message'] = "This is a simply email to reset password. Next you have a url to reset password of site: <br> " . user_pass_reset_url($uid) . "";
		    $to = $uid->getEmail();
		    $secure_check = $this->sanitize_my_email($to);
			if ($secure_check != false) {
				$user_storage = \Drupal::entityManager()->getStorage('user');
				$user = $user_storage->load($uid->id());
				$uid->setPassword($string);
				$uid->save();
			    $mailManager->mail('system', 'mail', $to, $langcode, $params);
			}
		}, $users);
	}

	function sanitize_my_email($field) {
	    $field = filter_var($field, FILTER_SANITIZE_EMAIL);
	    if (filter_var($field, FILTER_VALIDATE_EMAIL)) {
	        return true;
	    } else {
	        return false;
	    }
	 }
}
	
