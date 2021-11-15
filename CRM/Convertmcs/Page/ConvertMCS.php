<?php
use CRM_Convertmcs_ExtensionUtil as E;

class CRM_Convertmcs_Page_ConvertMCS extends CRM_Core_Page {
  private $relTypePrimaryMemberContactId;
  private $relTypeMemberContactId;

  public function __construct($title = NULL, $mode = NULL) {
    $this->relTypePrimaryMemberContactId = CRM_Convertmcs_Relationship::getRelationshipTypePrimaryMemberContact()['id'];
    $this->relTypeMemberContactId = CRM_Convertmcs_Relationship::getRelationshipTypeMemberContact()['id'];

    parent::__construct($title, $mode);
  }

  public function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(E::ts('ConvertMCS'));

    // Example: Assign a variable for use in a template
    $this->assign('currentTime', date('Y-m-d H:i:s'));

    parent::run();
  }

}
