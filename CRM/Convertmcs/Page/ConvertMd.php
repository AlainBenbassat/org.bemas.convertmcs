<?php
use CRM_Convertmcs_ExtensionUtil as E;

class CRM_Convertmcs_Page_ConvertMd extends CRM_Core_Page {

  public function run() {
    CRM_Utils_System::setTitle(E::ts('Convert Membership Details'));

    $dao = $this->getOrgsToConvert();
    while ($dao->fetch()) {
      echo "Processing " . $dao->display_name . "...<br>";

      $this->updateField($dao->membership_id, 'custom_161', $dao->membership_type_58);
      $this->updateField($dao->membership_id, 'custom_162', $dao->authorized_number_of_member_cont_73);
      $this->updateField($dao->membership_id, 'custom_163', $dao->number_of_additional_member_cont_15);
      $this->updateField($dao->membership_id, 'custom_164', $dao->total_number_of_member_contacts_16);
      $this->updateField($dao->membership_id, 'custom_166', $dao->reason_end_membership_57);
    }

    parent::run();
  }

  private function updateField($entityId, $customField, $customValue) {
    if (!empty($customValue)) {
      civicrm_api3('CustomValue', 'Create', [
        'entity_id' => $entityId,
        $customField => $customValue,
      ]);
    }
  }

  private function getOrgsToConvert() {
    $sql = "
      SELECT
        c.id,
        c.display_name,
        m.id membership_id,
        cd.number_of_additional_member_cont_15,
        cd.total_number_of_member_contacts_16,
        cd.reason_end_membership_57,
        cd.membership_type_58,
        cd.authorized_number_of_member_cont_73
      from
        civicrm_contact c
      inner join
        civicrm_value_membership_15 cd on cd.entity_id = c.id
      inner join
        civicrm_membership m on m.contact_id = c.id
      left outer join
        civicrm_value_lidmaatschap__35 md on md.entity_id = m.id
      where
        c.is_deleted = 0
      and
        md.membership_type_161 is null
      order by
        c.sort_name
    ";
    $dao = CRM_Core_DAO::executeQuery($sql);

    return $dao;
  }
}
