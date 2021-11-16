<?php

class CRM_Convertmcs_Inspector {
  private $relTypePrimaryMemberContactId;
  private $relTypeMemberContactId;

  public function __construct() {
    $this->relTypePrimaryMemberContactId = CRM_Convertmcs_Relationship::getRelationshipTypePrimaryMemberContact()['id'];
    $this->relTypeMemberContactId = CRM_Convertmcs_Relationship::getRelationshipTypeMemberContact()['id'];
  }

  public function getStats() {
    $output = '<p>Statistieken:</p>';
    $output .= '<ul>';
    $output .= '<li>Aantal Primary Member Contacts zonder relatie: ' . $this->getCountPrimaryMemberContactsToConvert() . '<li>';
    $output .= '<li>Aantal Member Contacts zonder relatie: ' . $this->getCountMemberContactsToConvert() . '<li>';
    $output .= '<li>Aantal Ex-Member Contacts zonder relatie: ' . $this->getCountExMemberContactsToConvert() . '<li>';
    $output .= '</ul>';
    return $output;
  }

  private function getCountPrimaryMemberContactsToConvert() {
    $sql = "
      select
        count(*)
      from
        civicrm_value_individual_details_19 i
      where
        i.types_of_member_contact_60 = 'M1 - Primary member contact'
      and
        not exists (
          select
            *
          from
            civicrm_relationship r
          where
            r.contact_id_a = i.entity_id
          and
            r.relationship_type_id = {$this->relTypePrimaryMemberContactId}
          and
            r.is_active = 1
        )
    ";
    return CRM_Core_DAO::singleValueQuery($sql);
  }

  private function getCountMemberContactsToConvert() {
    $sql = "
      select
        count(*)
      from
        civicrm_value_individual_details_19 i
      where
        i.types_of_member_contact_60 = 'Mc - Member contact'
      and
        not exists (
          select
            *
          from
            civicrm_relationship r
          where
            r.contact_id_a = i.entity_id
          and
            r.relationship_type_id = {$this->relTypeMemberContactId}
          and
            r.is_active = 1
        )
    ";
    return CRM_Core_DAO::singleValueQuery($sql);
  }

  private function getCountExMemberContactsToConvert() {
    $sql = "
      select
        count(*)
      from
        civicrm_value_individual_details_19 i
      where
        i.types_of_member_contact_60 = 'Mx - Ex-member contact'
      and
        not exists (
          select
            *
          from
            civicrm_relationship r
          where
            r.contact_id_a = i.entity_id
          and
            r.relationship_type_id = {$this->relTypeMemberContactId}
          and
            r.is_active = 0
        )
    ";
    return CRM_Core_DAO::singleValueQuery($sql);
  }
}
