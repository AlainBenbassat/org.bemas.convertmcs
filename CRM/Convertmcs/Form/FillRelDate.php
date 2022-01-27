<?php

use CRM_Convertmcs_ExtensionUtil as E;

class CRM_Convertmcs_Form_FillRelDate extends CRM_Core_Form {
  public function buildQuickForm() {
    $options = ['count' => 'Toon aantal te converteren', 'convert' => 'Converteer'];
    $this->addRadio('selected_action', 'Wat?', $options, [],'<br>', TRUE);
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

  public function postProcess() {
    try {
      $values = $this->exportValues();
      $convertor = new CRM_Convertmcs_Convertor();

      if ($values['selected_action'] == 'count') {
        CRM_Core_Session::setStatus('Aantal te converteren relaties: ' . $convertor->countRelsWithoutStartDate(), '', 'status');
      }
      elseif ($values['selected_action'] == 'convert') {
        $convertor->fillRelDate();
        CRM_Core_Session::setStatus('Done');
      }
      else {
        CRM_Core_Session::setStatus('CANNOT PROCESS ACTION', '', 'error');
      }
    }
    catch (Exception $e) {
      CRM_Core_Session::setStatus($e->getMessage());
    }

    parent::postProcess();
  }

  public function getRenderableElementNames() {
    $elementNames = array();
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
