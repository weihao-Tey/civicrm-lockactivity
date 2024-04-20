<?php

require_once 'lockactivity.civix.php';

use CRM_Lockactivity_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function lockactivity_civicrm_config(&$config): void {
  _lockactivity_civix_civicrm_config($config);
}

/**
 * Lifecycle hook :: install().
 * Implements hook_civicrm_install().
 * 
 * Locked Activity Status will be added if it exists.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function lockactivity_civicrm_install(): void {
  _lockactivity_civix_civicrm_install();
}

/**
 * Lifecycle hook :: uninstall().
 * Implements hook_civicrm_uninstall().
 * 
 * Locked Activity Staus will be deleted if it exists.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_uninstall
 */
function lockactivity_civicrm_uninstall(): void {
  _lockactivity_civix_civicrm_uninstall();
}

/**
 * Lifecycle hook :: enable().
 * Implements hook_civicrm_enable().
 *
 * Locked Status will be enabled.
 * 
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function lockactivity_civicrm_enable(): void {
  _lockactivity_civix_civicrm_enable();
}

/**
 * Lifecycle hook :: disable().
 * Implements hook_civicrm_disable().
 *
 * Locked Status will be disabled.
 * 
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_disable
 */
function lockactivity_civicrm_disable(): void {
  _lockactivity_civix_civicrm_disable();
}

/**
 * 
 * Uses CiviCRM CRM_Core_Session.
 * 
 * Get the CurrentUser's contactID.
 * 
 */
function getCurrentUserID(){
  $contactId = CRM_Core_Session::getLoggedInContactID();
  return $contactId;
}

/**
 * 
 * Uses the WordPress method.
 * 
 * Get the CurrentUser's role.
 * 
 */
function getCurrentUserRole(){
  $current_user = wp_get_current_user();
  $roleArray = $current_user->roles;
  $role = implode(",", $roleArray);
  return $role;
}

/**
 * 
 * get saved settings from db
 * 
 * 
 */
function getSettings(){
  $settings = [];
        
  $sql = "SELECT * FROM civicrm_lockactivitysettings";
  $result = CRM_Core_DAO::executeQuery($sql, CRM_Core_DAO::$_nullArray);

  while ($result->fetch()) {
      // Store each setting in the associative array.
      $settings[$result->param_name] = $result->param_value;
  }

  return $settings ? $settings : ['selected_roles' => 'administrator'];
}

/**
 * 
 * Implements hook_civicrm_buildForm().
 * 
 * Hide the 'Locked' Activity Status for specified users. [Activity Form]
 * 
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_buildForm
 */
function lockactivity_civicrm_buildForm($formName, &$form) {
  $role = getCurrentUserRole();
  $settings = getSettings();
  $settingArray = explode(",", $settings['selected_roles']);

  // Check if the form is for editing an activity
  if ($formName == 'CRM_Activity_Form_Activity' && !in_array($role, $settingArray) || $formName == 'CRM_Case_Form_Activity' && !in_array($role, $settingArray)) {
    // Access the status_id element directly
    $element =& $form->getElement('status_id');
      
    // Remove the "Locked" status option
    foreach ($element->_options as $index => $option) {
      if ($option['text'] == 'Locked') {
        unset($element->_options[$index]);
        // Re-index the options array after removal
        $element->_options = array_values($element->_options);
        // Remove the status_id value if it's "Locked"
        if ($element->_values[0] == $index + 1) {
            $element->_values = array();
        }
        break;
      }
    }
  }
}

/**
 * 
 * Implements hook_civicrm_links().
 * 
 * Removes the ActionLinks if Activity Status is 'Locked'. [Activity Tab]
 * 
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_links/
 */
function lockactivity_civicrm_links($op, $objectName, $objectId, &$links, &$mask, &$values) {
  // Only modify links for activity listings
  if ($objectName == 'Activity') {

    $activity = civicrm_api4('Activity', 'get', [
      'select' => [
        'status_id',
        'status_id:label',
      ],
      'where' => [
        ['id', '=', $objectId],
      ],
      'checkPermissions' => FALSE,
    ], 0);

    $activity_status = $activity['status_id:label'];
    $role = getCurrentUserRole();
    $settings = getSettings();
    $settingArray = explode(",", $settings['selected_roles']);

    if ($activity_status == 'Locked' && !in_array($role, $settingArray)){
      // Now, to remove the Edit button/link
      // $key -> name of the object
      // $link -> value of the object
      foreach ($links as $key => $link) {
        // Check for the Edit link by title or any other identifiable attribute
        if (isset($link['title']) && $link['title'] == ts('Update Activity')) {
            unset($links[$key]);
        }
        elseif(isset($link['title']) && $link['title'] == ts('Delete Activity')){
          unset($links[$key]);
        }
        elseif(isset($link['title']) && $link['title'] == ts('File on Case')){
          unset($links[$key]);
        }
        elseif(isset($link['title']) && $link['title'] == ts('Edit')){
          unset($links[$key]);
        }
      }
    }
  }
}

/**
 * @param string $formName name of the form
 * @param object $form (reference) form object
 * @param string $context page or form
 * @param string $tplName (reference) change this if required to the altered tpl file
 */
function lockactivity_civicrm_alterTemplateFile($formName, &$form, $context, &$tplName) {
  
  if ($formName == 'CRM_Activity_Form_Activity' && $form->getAction() == CRM_Core_Action::VIEW) {

  $role = getCurrentUserRole();
  $settings = getSettings();
  $allowedRoles = explode(",", $settings['selected_roles']);
  $activitytypeid = $form->getVar('_activityTypeId');

  $extensions = civicrm_api4('Extension', 'get', [
    'where' => [
      ['label', '=', 'civi_zohosign'],
      ['status', '=', 'installed'],
    ],
    'checkPermissions' => FALSE,
    'select' => [
      'status',
    ],
  ]);

  if($extensions->rowCount != 0){
    $installed = TRUE;
  }
  else{
    $installed = FALSE;
  }

  // Assign variables to the template
  CRM_Core_Smarty::singleton()->assign('role', $role);
  CRM_Core_Smarty::singleton()->assign('installed', $installed);
  CRM_Core_Smarty::singleton()->assign('allowedRoles', $allowedRoles);
  CRM_Core_Smarty::singleton()->assign('activitytypeid', $activitytypeid);

    //Get Activity Status 
    $activityStatusArray = $form->getElementValue('status_id');
    $activityStatus = implode(",", $activityStatusArray);

    $optionGroup = civicrm_api4('OptionGroup', 'get', [
      'select' => ['id'],
      'where' => [['title', '=', 'Activity Status']],
      'checkPermissions' => FALSE,
    ], 0);

    $statusgroupid= $optionGroup['id'];

    $optionValue = civicrm_api4('OptionValue', 'get', [
      'select' => [
        'value',
      ],
      'where' => [
        ['option_group_id', '=',  $statusgroupid],
        ['label', '=', 'Locked'],
      ],
      'checkPermissions' => FALSE,
    ], 0);

    $statusid = $optionValue['value'];

    if($activityStatus == $statusid){
      // Get the path to the extension's templates/activity directory
      $extDir = __DIR__ . '/templates' . DIRECTORY_SEPARATOR . 'Activity';
              
      // Set the relative path to the Activity.tpl file
      $relativePath = 'Activity.tpl';

      // Combine the extension's templates directory path with the relative path
      $tplName = $extDir . DIRECTORY_SEPARATOR . $relativePath;
    }
  }
}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 */
function lockactivity_civicrm_navigationMenu(&$menu) {
  _lockactivity_civix_insert_navigation_menu($menu, 'Administer/System Settings', array(
    'label' => ts('Activity Lock Settings'),
    'name' => 'lock_activity',
    'url' => 'civicrm/lockactivitysettings?reset=1',
    'permission' => 'administer CiviCRM',
    'operator' => 'OR',
    'separator' => 0,
  ));
  _lockactivity_civix_navigationMenu($menu);
}
