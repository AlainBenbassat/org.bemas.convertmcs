<?php

class CRM_Convertmcs_Convertor {
  const NO_MEMBERSHIP = 1;
  const INACTIVE_MEMBERSHIP = 2;
  const ACTIVE_MEMBERSHIP = 3;

  private $relTypePrimaryMemberContactId;
  private $relTypeMemberContactId;
  private $issues = [];
  private $numConvertedContacts = 0;

  public function __construct() {
    $this->relTypePrimaryMemberContactId = CRM_Convertmcs_Relationship::getRelationshipTypePrimaryMemberContact()['id'];
    $this->relTypeMemberContactId = CRM_Convertmcs_Relationship::getRelationshipTypeMemberContact()['id'];
  }

  public function start($batchLimit) {
    $this->convertPrimaryMemberContacts($batchLimit);
    $this->convertMemberContacts($batchLimit);
    $this->convertExMemberContacts($batchLimit);

    $output = "<p>Aantal geconverteerde contacten: {$this->numConvertedContacts}</p>";
    $output .= '<p>Problemen:</p>';
    $output .= '<ul>';
    $output .= $this->convertIssuesToLi();
    $output .= '</ul>';

    return $output;
  }

  private function convertPrimaryMemberContacts($batchLimit) {
    $sql = "
      select
        i.entity_id contact_id
        , 'M1' mc_type
      from
        civicrm_value_individual_details_19 i
      inner join
        civicrm_contact c on c.id = i.entity_id
      where
        i.types_of_member_contact_60 = 'M1 - Primary member contact'
      and
        c.is_deleted = 0
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
      limit 0,$batchLimit
    ";
    $dao = CRM_Core_DAO::executeQuery($sql);
    while ($dao->fetch()) {
      $this->convertContact($dao->contact_id, $dao->mc_type);
    }
  }

  private function convertMemberContacts($batchLimit) {
    $sql = "
      select
        i.entity_id contact_id
        , 'MC' mc_type
      from
        civicrm_value_individual_details_19 i
      inner join
        civicrm_contact c on c.id = i.entity_id
      where
        i.types_of_member_contact_60 = 'Mc - Member contact'
      and
        c.is_deleted = 0
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
      limit 0,$batchLimit
    ";

    $dao = CRM_Core_DAO::executeQuery($sql);
    while ($dao->fetch()) {
      $this->convertContact($dao->contact_id, $dao->mc_type);
    }
  }

  private function convertExMemberContacts($batchLimit) {
    $sql = "
      select
        i.entity_id contact_id
        , 'MX' mc_type
      from
        civicrm_value_individual_details_19 i
      inner join
        civicrm_contact c on c.id = i.entity_id
      where
        i.types_of_member_contact_60 = 'Mx - Ex-member contact'
      and
        c.is_deleted = 0
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
      limit 0,$batchLimit
    ";
    $dao = CRM_Core_DAO::executeQuery($sql);
    while ($dao->fetch()) {
      $this->convertContact($dao->contact_id, $dao->mc_type);
    }
  }

  private function convertContact($contactId, $mcType) {
    try {
      if ($mcType == 'MX') {
        $employerId = $this->getExEmployerId($contactId);
      }
      else {
        $employerId = $this->getEmployerId($contactId);
      }

      $membershipStatus = $this->getMembershipStatus($employerId);

      $this->assertMembershipStatus($contactId, $employerId, $membershipStatus, $mcType);

      // VOOR MX: relaties werkgever checken

      $this->numConvertedContacts++;
      // AFWERKEN

    }
    catch (Exception $e) {
      $this->issues[] = $e->getMessage();
    }
  }

  private function assertMembershipStatus($contactId, $employerId, $membershipStatus, $mcType) {
    if ($mcType != 'MX' && empty($employerId)) {
      $contactURL = $this->getContactURL($contactId);
      throw new Exception("Contact $contactURL heeft geen werkgever");
    }

    if ($mcType == 'MX' && empty($employerId)) {
      $contactURL = $this->getContactURL($contactId);
      throw new Exception("Contact $contactURL heeft geen (ex)werkgever met een lidmaatschap");
    }

    if ($membershipStatus == self::NO_MEMBERSHIP) {
      $contactURL = $this->getContactURL($contactId);
      $employerURL = $this->getContactURL($employerId);
      throw new Exception("Contact $contactURL met werkgever $employerURL heeft geen lidmaatschap");
    }

    if ($membershipStatus == self::INACTIVE_MEMBERSHIP && $mcType != 'MX') {
      $contactURL = $this->getContactURL($contactId);
      $employerURL = $this->getContactURL($employerId);
      throw new Exception("Contact $contactURL met werkgever $employerURL is $mcType, maar het lidmaatschap is inactief");
    }
  }

  private function getEmployerId($contactId) {
    return CRM_Core_DAO::singleValueQuery("
      select employer_id from civicrm_contact where id = $contactId
    ");
  }

  private function getExEmployerId($contactId) {
    $employerEmployeeRelationshipType = 4;
    $highestBemasMembershipId = 11;

    $sql = "
      select
        r.contact_id_b
      from
        civicrm_relationship r
      inner join
        civicrm_membership m on r.contact_id_b = m.contact_id
      where
        r.contact_id_a = $contactId
      and
        r.relationship_type_id = $employerEmployeeRelationshipType
      and
        m.membership_type_id <= $highestBemasMembershipId
      order by
        m.end_date desc
    ";

    $dao = CRM_Core_DAO::executeQuery($sql);
    if ($dao->fetch()) {
      return $dao->contact_id_b;
    }
    else {
      return 0;
    }
  }

  private function getMembershipStatus($employerId) {
    $status = self::NO_MEMBERSHIP;

    if ($employerId) {
      $sql = "select * from civicrm_membership where contact_id = $employerId order by end_date desc";
      $dao = CRM_Core_DAO::executeQuery($sql);
      if ($dao->fetch()) {
        // check if the end date is in the future or the status = new, current, grace
        if ($dao->end_date >= date('Y-m-d') || $dao->status_id == 1 || $dao->status_id == 2 || $dao->status_id == 3) {
          $status = self::ACTIVE_MEMBERSHIP;
        }
        else {
          $status = self::INACTIVE_MEMBERSHIP;
        }
      }
    }

    return $status;
  }

  private function convertIssuesToLi() {
    $htmlSnippet = '';

    foreach ($this->issues as $issue) {
      $htmlSnippet .= "<li>$issue</li>";
    }

    return $htmlSnippet;
  }

  private function getContactURL($contactId) {
    return '<a href="contact/view?reset=1&cid=' . $contactId . '">' . $contactId . '</a>';
  }

}
