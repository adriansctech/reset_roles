<?php

namespace Drupal\reset_roles\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for return the content of the pages request .
 */
class ResetRolesController extends ControllerBase {

  /*public function list() {
  $rolesMultiples = Role::loadMultiple();
  $roles = [];
  foreach ($rolesMultiples as $role) {
  $roles[]= [
  'label' => $role->label(),
  'id' => $role->id()
  ];
  }

  return array(
  '#theme' => 'list-roles',
  '#items' => $roles,
  );
  }*/
}
