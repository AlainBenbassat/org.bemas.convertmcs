<?php

class CRM_Convertmcs_Convertor {
  const NO_MEMBERSHIP = 1;
  const INACTIVE_MEMBERSHIP = 2;
  const ACTIVE_MEMBERSHIP = 3;

  private $relTypePrimaryMemberContactId;
  private $relTypeMemberContactId;
  private $issues = [];
  private $numConvertedContacts = 0;

  private $contactIdOrgAsterisk = 0;

  public function __construct() {
    $this->relTypePrimaryMemberContactId = CRM_Convertmcs_Relationship::getRelationshipTypePrimaryMemberContact()['id'];
    $this->relTypeMemberContactId = CRM_Convertmcs_Relationship::getRelationshipTypeMemberContact()['id'];

    $this->contactIdOrgAsterisk = $this->getContactIdOrgAsterisk();
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

  public function countRelsWithoutStartDate() {
    $sql = "
      select
        count(*)
      from
        civicrm_relationship
      where
        relationship_type_id in ({$this->relTypePrimaryMemberContactId}, {$this->relTypeMemberContactId})
      and
        start_date is null
    ";
    return CRM_Core_DAO::singleValueQuery($sql);
  }

  public function fillRelDate() {
    $dao = $this->getRelsWithoutDate();
    while ($dao->fetch()) {
      $startDateMembership = $this->getMembershipStartDate($dao->org_id);
      $this->updateRelationshipStartDate($dao->rel_id, $startDateMembership);
    }
  }

  private function getRelsWithoutDate() {
    $sql = "
      select
        id rel_id
        , contact_id_b org_id
      from
        civicrm_relationship
      where
        relationship_type_id in ({$this->relTypePrimaryMemberContactId}, {$this->relTypeMemberContactId})
      and
        start_date is null
    ";
    return CRM_Core_DAO::executeQuery($sql);
  }

  private function getMembershipStartDate($contactId) {
    $sql = "
      select
        max(start_date)
      from
        civicrm_membership
      where
        contact_id = $contactId
    ";
    return CRM_Core_DAO::singleValueQuery($sql);
  }

  function updateRelationshipStartDate($relId, $startDateMembership) {
    if (!$startDateMembership) {
      return;
    }

    $sql = "update civicrm_relationship set start_date = %1 where id = %2";
    $sqlParams = [
      1 => [$startDateMembership, 'String'],
      2 => [$relId, 'Integer'],
    ];

    CRM_Core_DAO::executeQuery($sql, $sqlParams);
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
      $employerId = $this->getEmployerId($contactId, $mcType);
      $membershipStatus = $this->getMembershipStatus($employerId);

      $this->assertMembershipStatus($contactId, $employerId, $membershipStatus, $mcType);

      $this->addRelationship($contactId, $employerId, $mcType);

      $this->numConvertedContacts++;
    }
    catch (Exception $e) {
      $this->issues[] = $e->getMessage();
    }
  }

  private function assertMembershipStatus($contactId, $employerId, $membershipStatus, $mcType) {
    if ($mcType != 'MX' && empty($employerId)) {
      $contactURL = $this->getContactURL($contactId);
      throw new Exception("Contact $contactURL ($mcType) heeft geen werkgever");
    }

    if ($mcType == 'MX' && empty($employerId)) {
      $contactURL = $this->getContactURL($contactId);
      throw new Exception("Contact $contactURL ($mcType) heeft geen (ex)werkgever met een lidmaatschap");
    }

    if ($mcType != 'MX' && $membershipStatus == self::NO_MEMBERSHIP) {
      $contactURL = $this->getContactURL($contactId);
      $employerURL = $this->getContactURL($employerId);
      throw new Exception("Contact $contactURL ($mcType) met werkgever $employerURL heeft geen lidmaatschap");
    }

    if ($mcType != 'MX' && $membershipStatus == self::INACTIVE_MEMBERSHIP) {
      $contactURL = $this->getContactURL($contactId);
      $employerURL = $this->getContactURL($employerId);
      throw new Exception("Contact $contactURL met werkgever $employerURL is $mcType, maar het lidmaatschap is inactief");
    }
  }

  private function getEmployerId($contactId, $mcType) {
    if ($this->isIndividualMember($contactId)) {
      return $this->contactIdOrgAsterisk;
    }

    if ($mcType == 'MX') {
      return $this->getExEmployerId($contactId);
    }

    return $this->getCurrentEmployerId($contactId);
  }

  private function isIndividualMember($contactId) {
    $sql = "select * from civicrm_membership where contact_id = $contactId and membership_type_id = 8 order by end_date desc";
    $dao = CRM_Core_DAO::executeQuery($sql);
    if ($dao->fetch()) {
      // check if the end date is in the future or the status = new, current, grace
      if ($dao->end_date >= date('Y-m-d') || $dao->status_id == 1 || $dao->status_id == 2 || $dao->status_id == 3) {
        return TRUE;
      }
      else {
        return FALSE;
      }
    }
    else {
      return FALSE;
    }
  }

  private function getContactIdOrgAsterisk() {
    $sql = "
      select
        id
      from
        civicrm_contact
      where
        nick_name = '*'
      and
        is_deleted = 0
      and
        contact_type = 'Organization'
    ";
    return CRM_Core_DAO::singleValueQuery($sql);
  }

  private function getCurrentEmployerId($contactId) {
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
      // no org. id found, return id of org *
      return $this->contactIdOrgAsterisk;
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

  private function addRelationship($contactId, $employerId, $mcType) {
    $params = [
      'contact_id_a' => $contactId,
      'contact_id_b' => $employerId,
    ];

    if ($mcType == 'M1') {
      $params['is_active'] = 1;
      $params['relationship_type_id'] = $this->relTypePrimaryMemberContactId;
    }
    elseif ($mcType == 'MC') {
      $params['is_active'] = 1;
      $params['relationship_type_id'] = $this->relTypeMemberContactId;
    }
    elseif ($mcType == 'MX') {
      $params['is_active'] = 0;
      $params['relationship_type_id'] = $this->relTypeMemberContactId;
    }

    civicrm_api3('Relationship', 'Create', $params);
  }

  private function convertIssuesToLi() {
    $htmlSnippet = '';

    foreach ($this->issues as $issue) {
      $htmlSnippet .= "<li>$issue</li>";
    }

    return $htmlSnippet;
  }

  private function getContactURL($contactId) {
    $contactName = CRM_Core_DAO::singleValueQuery("select display_name from civicrm_contact where id = $contactId");
    return '<a href="contact/view?reset=1&cid=' . $contactId . '">' . $contactName . '</a>';
  }

}
