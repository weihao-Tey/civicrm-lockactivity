<?php

use CRM_Lockactivity_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_Lockactivity_Form_Lockactivitysettings extends CRM_Core_Form {

  /**
   * @throws \CRM_Core_Exception
   */
  public function buildQuickForm(): void {

    $sql = "SELECT * FROM civicrm_lockactivitysettings ic";
    $result = CRM_Core_DAO::executeQuery($sql, CRM_Core_DAO::$_nullArray);

    $params = array();
    while ($result->fetch()) {
      $params[$result->param_name] = $result->param_value;
    }
    
    // Populate the setting with the saved settings from the db
    $defaults = array(
      'selected_roles' => isset($params['selected_roles']) ? $params['selected_roles'] : '',
    );

    $this->setDefaults($defaults);


    $this->assign('roleOptions', $this->getRoles());

    // add form elements
    $this->add(
      'select', // field type
      'selected_roles', // field name
      'Select Roles that are able to Lock Activities', // field label
      $this->getRoles(), // list of options
      FALSE, // is required
      ['multiple' => 'multiple', 'class' => 'crm-select2', 'placeholder' => ts('- select -')]// additional attributes for multi-select
  );
    $this->addButtons([
      [
        'type' => 'submit',
        'name' => E::ts('Submit'),
        'isDefault' => TRUE,
      ],
    ]);

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  public function postProcess(): void {
    $postedVals = array(
      'selected_roles' => null,
    );

    $values = $this->exportValues();

    $values['selected_roles'] = is_array($values['selected_roles']) 
        ? implode(',', $values['selected_roles']) 
        : (string)$values['selected_roles'];

    $postedVals['selected_roles'] = $values['selected_roles'];

    // $checkFields = [
    //   'selected_roles' => 'Role',
    // ];

    // foreach ($postedVals as $key => $value) {
    //   if (in_array($key, array_keys($checkFields)) && $value == null) {
    //     CRM_Core_Session::setStatus("\"".$checkFields[$key]."\" field is required", ts('Empty field'), 'warning', array('expires' => 5000));
    //     return;
    //   }
    // }

    $sql =  "TRUNCATE TABLE civicrm_lockactivitysettings";
    CRM_Core_DAO::executeQuery($sql, CRM_Core_DAO::$_nullArray);

    foreach ($postedVals as $key => $value) {
      $sql =  "INSERT INTO civicrm_lockactivitysettings(param_name, param_value) VALUES('$key', '$value')";
      CRM_Core_DAO::executeQuery($sql, CRM_Core_DAO::$_nullArray);
    }

    // Notify the user of success
    CRM_Core_Session::setStatus(E::ts('Your settings have been saved.'), '', 'success'); 
    parent::postProcess();
  }

  public function getRoles(): array {
    $wp_roles = wp_roles();
    $roles = [];
    foreach ($wp_roles->roles as $role_name => $role_info) {
        $roles[$role_name] = $role_info['name'];
    }
    return $roles;
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames(): array {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = [];
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }
}
