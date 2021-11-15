<?php

class CRM_ConvertMCS_Relationship {
  public static function getRelationshipTypePrimaryMemberContact() {
    $params = [
      'name_a_b' => 'primary_member_contact_of',
      'label_a_b' => 'Primary Member Contact of',
      'name_b_a' => 'primary_member_contact_is',
      'label_b_a' => 'Primary Member Contact is',
      'description' => 'BEMAS Primary Member Contact relationship.',
      'contact_type_a' => 'Individual',
      'contact_type_b' => 'Organization',
      'is_reserved' => '0',
      'is_active' => '1',
      'sequential' => '1',
    ];

    return self::getRelationshipType($params);
  }

  public static function getRelationshipTypeMemberContact() {
    $params = [
      'name_a_b' => 'member_contact_of',
      'label_a_b' => 'Member Contact of',
      'name_b_a' => 'member_contact_is',
      'label_b_a' => 'Member Contact is',
      'description' => 'BEMAS Member Contact relationship.',
      'contact_type_a' => 'Individual',
      'contact_type_b' => 'Organization',
      'is_reserved' => '0',
      'is_active' => '1',
      'sequential' => '1',
    ];

    return self::getRelationshipType($params);
  }

  private static function getRelationshipType($params) {
    try {
      $result = civicrm_api3('RelationshipType', 'getsingle', [
        'name_a_b' => $params['name_a_b'],
      ]);

      return $result;
    }
    catch (Exception $e) {
      $result = civicrm_api3('RelationshipType', 'create', $params);

      return $result['values'][0];
    }
  }
}
